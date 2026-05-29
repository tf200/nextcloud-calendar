<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Calendar\Listener;

use OCA\Calendar\Service\Microsoft\MicrosoftSyncService;
use OCP\Calendar\Events\CalendarObjectCreatedEvent;
use OCP\Calendar\Events\CalendarObjectDeletedEvent;
use OCP\Calendar\Events\CalendarObjectUpdatedEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * @template-implements IEventListener<CalendarObjectCreatedEvent|CalendarObjectUpdatedEvent|CalendarObjectDeletedEvent>
 */
class MicrosoftSyncListener implements IEventListener {
	public function __construct(
		private MicrosoftSyncService $syncService,
		private LoggerInterface $logger,
	) {
	}

	#[\Override]
	public function handle(Event $event): void {
		if (!($event instanceof CalendarObjectCreatedEvent)
			&& !($event instanceof CalendarObjectUpdatedEvent)
			&& !($event instanceof CalendarObjectDeletedEvent)) {
			return;
		}

		$userId = $this->extractUserId($event->getCalendarData());
		if ($userId === null) {
			return;
		}

		try {
			if ($event instanceof CalendarObjectDeletedEvent) {
				$this->syncService->deleteCalendarObject($userId, $event->getCalendarData(), $event->getObjectData());
				return;
			}

			$this->syncService->syncCalendarObject($userId, $event->getCalendarData(), $event->getObjectData());
		} catch (Throwable $e) {
			$this->logger->warning('Microsoft Calendar event sync failed: ' . $e->getMessage(), [
				'app' => 'calendar',
				'exception' => $e,
			]);
		}
	}

	private function extractUserId(array $calendarData): ?string {
		$principalUri = $calendarData['principaluri'] ?? null;
		if (!is_string($principalUri)) {
			return null;
		}

		$prefix = 'principals/users/';
		if (!str_starts_with($principalUri, $prefix)) {
			return null;
		}

		return substr($principalUri, strlen($prefix));
	}
}
