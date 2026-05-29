<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Calendar\Service\Microsoft;

use OCP\Http\Client\IClientService;
use RuntimeException;
use function is_array;
use function json_decode;
use function rawurlencode;

class MicrosoftCalendarApiService {
	private const API_URL = 'https://graph.microsoft.com/v1.0';
	private const SYNC_CALENDAR_SUMMARY = 'Nextcloud Calendar';

	public function __construct(
		private IClientService $clientService,
	) {
	}

	public function getOrCreateSyncCalendar(string $accessToken, ?string $calendarId): string {
		if ($calendarId !== null && $this->calendarExists($accessToken, $calendarId)) {
			return $calendarId;
		}

		foreach ($this->listCalendars($accessToken) as $calendar) {
			if (($calendar['name'] ?? null) === self::SYNC_CALENDAR_SUMMARY) {
				return (string)$calendar['id'];
			}
		}

		return $this->createCalendar($accessToken, self::SYNC_CALENDAR_SUMMARY);
	}

	public function insertEvent(string $accessToken, string $calendarId, array $event): string {
		$data = $this->request('POST', '/calendars/' . rawurlencode($calendarId) . '/events', $accessToken, $event);
		if (empty($data['id']) || !is_string($data['id'])) {
			throw new RuntimeException('Microsoft did not return an event id.');
		}

		return $data['id'];
	}

	public function updateEvent(string $accessToken, string $calendarId, string $eventId, array $event): void {
		$this->request('PATCH', '/calendars/' . rawurlencode($calendarId) . '/events/' . rawurlencode($eventId), $accessToken, $event);
	}

	public function deleteEvent(string $accessToken, string $calendarId, string $eventId): void {
		$this->request('DELETE', '/calendars/' . rawurlencode($calendarId) . '/events/' . rawurlencode($eventId), $accessToken);
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	private function listCalendars(string $accessToken): array {
		$data = $this->request('GET', '/me/calendars', $accessToken);

		return isset($data['value']) && is_array($data['value']) ? $data['value'] : [];
	}

	private function calendarExists(string $accessToken, string $calendarId): bool {
		try {
			$this->request('GET', '/calendars/' . rawurlencode($calendarId), $accessToken);
			return true;
		} catch (RuntimeException) {
			return false;
		}
	}

	private function createCalendar(string $accessToken, string $name): string {
		$data = $this->request('POST', '/me/calendars', $accessToken, ['name' => $name]);
		if (empty($data['id']) || !is_string($data['id'])) {
			throw new RuntimeException('Microsoft did not return a calendar id.');
		}

		return $data['id'];
	}

	/**
	 * @return array<string,mixed>
	 */
	private function request(string $method, string $path, string $accessToken, ?array $body = null): array {
		$options = [
			'headers' => [
				'Authorization' => 'Bearer ' . $accessToken,
				'Accept' => 'application/json',
			],
		];
		if ($body !== null) {
			$options['headers']['Content-Type'] = 'application/json';
			$options['body'] = json_encode($body, JSON_THROW_ON_ERROR);
		}

		$client = $this->clientService->newClient();
		$response = match ($method) {
			'GET' => $client->get(self::API_URL . $path, $options),
			'POST' => $client->post(self::API_URL . $path, $options),
			'PATCH' => $client->patch(self::API_URL . $path, $options),
			'DELETE' => $client->delete(self::API_URL . $path, $options),
			default => throw new RuntimeException('Unsupported Microsoft API method.'),
		};

		if ($response->getStatusCode() === 204) {
			return [];
		}

		$data = json_decode((string)$response->getBody(), true);
		if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300 || !is_array($data)) {
			throw new RuntimeException('Microsoft Graph API request failed.');
		}

		return $data;
	}
}
