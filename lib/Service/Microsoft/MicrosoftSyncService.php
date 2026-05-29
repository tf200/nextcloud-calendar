<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Calendar\Service\Microsoft;

use DateInterval;
use DateTimeImmutable;
use OCA\Calendar\Db\MicrosoftAuth;
use OCA\Calendar\Db\MicrosoftAuthMapper;
use OCA\Calendar\Db\MicrosoftSyncMap;
use OCA\Calendar\Db\MicrosoftSyncMapMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Calendar\ICalendar;
use OCP\Calendar\ICalendarIsEnabled;
use OCP\Calendar\IManager;
use Psr\Log\LoggerInterface;
use Throwable;
use function is_resource;
use function is_string;
use function method_exists;
use function stream_get_contents;
use function time;

class MicrosoftSyncService {
	public function __construct(
		private MicrosoftAuthMapper $authMapper,
		private MicrosoftSyncMapMapper $syncMapMapper,
		private MicrosoftTokenService $tokenService,
		private MicrosoftCalendarApiService $apiService,
		private MicrosoftEventMapper $eventMapper,
		private IManager $calendarManager,
		private LoggerInterface $logger,
	) {
	}

	public function ensureSyncCalendar(MicrosoftAuth $auth): string {
		$accessToken = $this->tokenService->getAccessToken($auth);
		$calendarId = $this->apiService->getOrCreateSyncCalendar($accessToken, $auth->getMicrosoftCalendarId());
		if ($auth->getMicrosoftCalendarId() !== $calendarId) {
			$auth->setMicrosoftCalendarId($calendarId);
			$auth->setUpdatedAt(time());
			$this->authMapper->update($auth);
		}

		return $calendarId;
	}

	public function syncInitial(string $userId): void {
		$auth = $this->authMapper->findByUserId($userId);
		$calendar = $this->getPrimaryCalendar($userId);
		if ($calendar === null) {
			return;
		}

		$start = (new DateTimeImmutable())->sub(new DateInterval('P30D'));
		$end = (new DateTimeImmutable())->add(new DateInterval('P365D'));
		foreach ($calendar->search('', [], ['timerange' => ['start' => $start, 'end' => $end], 'types' => ['VEVENT']], 1000) as $event) {
			try {
				$mapped = $this->eventMapper->mapSearchResult($event, $calendar->getUri());
				$this->upsertMappedEvent($auth, $calendar->getUri(), (string)$event['uri'], $mapped);
			} catch (Throwable $e) {
				$this->logger->warning('Microsoft Calendar sync failed for one event: ' . $e->getMessage(), ['app' => 'calendar', 'exception' => $e]);
			}
		}

		$auth->setLastSyncAt(time());
		$auth->setUpdatedAt(time());
		$this->authMapper->update($auth);
	}

	public function syncCalendarObject(string $userId, array $calendarData, array $objectData): void {
		$calendarUri = $this->extractCalendarUri($calendarData);
		if ($calendarUri === null || !$this->isPrimaryCalendarUri($userId, $calendarUri)) {
			return;
		}

		$objectUri = isset($objectData['uri']) ? (string)$objectData['uri'] : null;
		$calendarObjectData = $this->extractCalendarObjectData($objectData);
		if ($objectUri === null || $calendarObjectData === null) {
			return;
		}

		$auth = $this->authMapper->findByUserId($userId);
		$mapped = $this->eventMapper->map($calendarObjectData, $calendarUri, $objectUri);
		$this->upsertMappedEvent($auth, $calendarUri, $objectUri, $mapped);
	}

	public function deleteCalendarObject(string $userId, array $calendarData, array $objectData): void {
		$calendarUri = $this->extractCalendarUri($calendarData);
		$objectUri = isset($objectData['uri']) ? (string)$objectData['uri'] : null;
		if ($calendarUri === null || $objectUri === null || !$this->isPrimaryCalendarUri($userId, $calendarUri)) {
			return;
		}

		try {
			$syncMap = $this->syncMapMapper->findByObject($userId, $calendarUri, $objectUri);
		} catch (DoesNotExistException) {
			return;
		}

		$auth = $this->authMapper->findByUserId($userId);
		$accessToken = $this->tokenService->getAccessToken($auth);
		$this->apiService->deleteEvent($accessToken, $syncMap->getMicrosoftCalendarId(), $syncMap->getMicrosoftEventId());
		$this->syncMapMapper->delete($syncMap);
	}

	public function retryFailed(): void {
		foreach ($this->syncMapMapper->findFailed() as $syncMap) {
			try {
				$this->syncInitial($syncMap->getUserId());
			} catch (Throwable $e) {
				$this->logger->warning('Microsoft Calendar sync retry failed: ' . $e->getMessage(), ['app' => 'calendar', 'exception' => $e]);
			}
		}
	}

	private function upsertMappedEvent(MicrosoftAuth $auth, string $calendarUri, string $objectUri, array $mapped): void {
		$microsoftCalendarId = $this->ensureSyncCalendar($auth);
		$accessToken = $this->tokenService->getAccessToken($auth);

		try {
			$syncMap = $this->syncMapMapper->findByObject($auth->getUserId(), $calendarUri, $objectUri);
			if ($syncMap->getEventHash() === $mapped['hash']) {
				return;
			}
			$this->apiService->updateEvent($accessToken, $syncMap->getMicrosoftCalendarId(), $syncMap->getMicrosoftEventId(), $mapped['payload']);
		} catch (DoesNotExistException) {
			$syncMap = new MicrosoftSyncMap();
			$syncMap->setUserId($auth->getUserId());
			$syncMap->setNextcloudCalendarUri($calendarUri);
			$syncMap->setNextcloudObjectUri($objectUri);
			$syncMap->setNextcloudUid($mapped['uid']);
			$syncMap->setMicrosoftCalendarId($microsoftCalendarId);
			$syncMap->setMicrosoftEventId($this->apiService->insertEvent($accessToken, $microsoftCalendarId, $mapped['payload']));
			$syncMap->setCreatedAt(time());
		}

		$syncMap->setEventHash($mapped['hash']);
		$syncMap->setLastError(null);
		$syncMap->setLastSyncedAt(time());
		$syncMap->setUpdatedAt(time());
		$syncMap->getId() === null ? $this->syncMapMapper->insert($syncMap) : $this->syncMapMapper->update($syncMap);
	}

	private function getPrimaryCalendar(string $userId): ?ICalendar {
		if (method_exists($this->calendarManager, 'getPrimaryCalendar')) {
			$calendar = $this->calendarManager->getPrimaryCalendar($userId);
			if ($calendar instanceof ICalendar && !$calendar->isDeleted()) {
				return $calendar;
			}
		}

		foreach ($this->calendarManager->getCalendarsForPrincipal('principals/users/' . $userId) as $calendar) {
			if ($calendar->isDeleted()) {
				continue;
			}
			if ($calendar instanceof ICalendarIsEnabled && !$calendar->isEnabled()) {
				continue;
			}
			return $calendar;
		}

		return null;
	}

	private function isPrimaryCalendarUri(string $userId, string $calendarUri): bool {
		$primaryCalendar = $this->getPrimaryCalendar($userId);

		return $primaryCalendar !== null && $primaryCalendar->getUri() === $calendarUri;
	}

	private function extractCalendarUri(array $calendarData): ?string {
		return isset($calendarData['uri']) ? (string)$calendarData['uri'] : null;
	}

	private function extractCalendarObjectData(array $objectData): ?string {
		$data = $objectData['calendardata'] ?? null;
		if (is_resource($data)) {
			$data = stream_get_contents($data);
		}

		return is_string($data) ? $data : null;
	}
}
