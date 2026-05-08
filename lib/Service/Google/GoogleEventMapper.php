<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Calendar\Service\Google;

use DateTimeInterface;
use RuntimeException;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Component\VEvent;
use Sabre\VObject\Reader;
use function hash;

class GoogleEventMapper {
	/**
	 * @return array{uid:string,payload:array<string,mixed>,hash:string}
	 */
	public function mapSearchResult(array $event, string $calendarUri): array {
		$object = $event['objects'][0] ?? null;
		if (!is_array($object) || empty($event['uri'])) {
			throw new RuntimeException('Calendar search result does not contain a VEVENT.');
		}

		$uid = (string)($event['uid'] ?? $event['uri']);
		$objectUri = (string)$event['uri'];
		$payload = [
			'summary' => (string)($object['SUMMARY'][0] ?? 'Untitled event'),
			'extendedProperties' => [
				'private' => [
					'nextcloudCalendarUri' => $calendarUri,
					'nextcloudObjectUri' => $objectUri,
					'nextcloudUid' => $uid,
				],
			],
		];

		if (!empty($object['DESCRIPTION'][0])) {
			$payload['description'] = (string)$object['DESCRIPTION'][0];
		}
		if (!empty($object['LOCATION'][0])) {
			$payload['location'] = (string)$object['LOCATION'][0];
		}
		if (!empty($object['STATUS'][0])) {
			$payload['status'] = strtolower((string)$object['STATUS'][0]) === 'cancelled' ? 'cancelled' : 'confirmed';
		}

		$start = $object['DTSTART'][0] ?? null;
		$end = $object['DTEND'][0] ?? null;
		if (!$start instanceof DateTimeInterface) {
			throw new RuntimeException('Calendar search result does not contain a DTSTART.');
		}
		$payload['start'] = $this->mapDateTime($start, $this->searchDateHasTime($object, 'DTSTART'));
		$payload['end'] = $end instanceof DateTimeInterface
			? $this->mapDateTime($end, $this->searchDateHasTime($object, 'DTEND'))
			: $payload['start'];

		return [
			'uid' => $uid,
			'payload' => $payload,
			'hash' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)),
		];
	}

	/**
	 * @return array{uid:string,payload:array<string,mixed>,hash:string}
	 */
	public function map(string $calendarData, string $calendarUri, string $objectUri): array {
		$vCalendar = Reader::read($calendarData);
		if (!$vCalendar instanceof VCalendar || !isset($vCalendar->VEVENT)) {
			throw new RuntimeException('Calendar object does not contain a VEVENT.');
		}

		/** @var VEvent $event */
		$event = $vCalendar->VEVENT;
		$uid = isset($event->UID) ? (string)$event->UID : $objectUri;
		$payload = [
			'summary' => isset($event->SUMMARY) ? (string)$event->SUMMARY : 'Untitled event',
			'extendedProperties' => [
				'private' => [
					'nextcloudCalendarUri' => $calendarUri,
					'nextcloudObjectUri' => $objectUri,
					'nextcloudUid' => $uid,
				],
			],
		];

		if (isset($event->DESCRIPTION)) {
			$payload['description'] = (string)$event->DESCRIPTION;
		}
		if (isset($event->LOCATION)) {
			$payload['location'] = (string)$event->LOCATION;
		}
		if (isset($event->STATUS)) {
			$payload['status'] = strtolower((string)$event->STATUS) === 'cancelled' ? 'cancelled' : 'confirmed';
		}

		$payload['start'] = $this->mapDateProperty($event->DTSTART);
		if (isset($event->DTEND)) {
			$payload['end'] = $this->mapDateProperty($event->DTEND);
		} elseif (isset($event->DURATION)) {
			$end = clone $event->DTSTART->getDateTime();
			$end = $end->add($event->DURATION->getDateInterval());
			$payload['end'] = $this->mapDateTime($end, $event->DTSTART->hasTime());
		} else {
			$payload['end'] = $payload['start'];
		}

		$recurrence = [];
		foreach (['RRULE', 'RDATE', 'EXDATE'] as $propertyName) {
			foreach ($event->select($propertyName) as $property) {
				$recurrence[] = $propertyName . ':' . (string)$property;
			}
		}
		if ($recurrence !== []) {
			$payload['recurrence'] = $recurrence;
		}

		return [
			'uid' => $uid,
			'payload' => $payload,
			'hash' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)),
		];
	}

	/**
	 * @return array<string,string>
	 */
	private function mapDateProperty($property): array {
		return $this->mapDateTime($property->getDateTime(), $property->hasTime());
	}

	/**
	 * @return array<string,string>
	 */
	private function mapDateTime(DateTimeInterface $dateTime, bool $hasTime): array {
		if (!$hasTime) {
			return ['date' => $dateTime->format('Y-m-d')];
		}

		return [
			'dateTime' => $dateTime->format(DateTimeInterface::RFC3339),
			'timeZone' => $dateTime->getTimezone()->getName(),
		];
	}

	private function searchDateHasTime(array $object, string $propertyName): bool {
		return !isset($object[$propertyName]['type']) || $object[$propertyName]['type'] !== 'DATE';
	}
}
