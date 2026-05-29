<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Calendar\Service\Microsoft;

use DateTimeInterface;
use RuntimeException;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Component\VEvent;
use Sabre\VObject\Reader;
use function hash;
use function is_string;

class MicrosoftEventMapper {
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
			'subject' => (string)($object['SUMMARY'][0] ?? 'Untitled event'),
			'body' => [
				'contentType' => 'text',
				'content' => !empty($object['DESCRIPTION'][0]) ? (string)$object['DESCRIPTION'][0] : '',
			],
			'singleValueExtendedProperties' => [
				[
					'id' => 'String {ca1242da-c60a-4be2-a7da-4e82e8e23a01} Name nextcloudCalendarUri',
					'value' => $calendarUri,
				],
				[
					'id' => 'String {ca1242da-c60a-4be2-a7da-4e82e8e23a01} Name nextcloudObjectUri',
					'value' => $objectUri,
				],
				[
					'id' => 'String {ca1242da-c60a-4be2-a7da-4e82e8e23a01} Name nextcloudUid',
					'value' => $uid,
				],
			],
		];

		if (!empty($object['LOCATION'][0])) {
			$payload['location'] = [
				'displayName' => (string)$object['LOCATION'][0],
			];
		}
		if (!empty($object['STATUS'][0])) {
			$isCancelled = strtolower((string)$object['STATUS'][0]) === 'cancelled';
			$payload['isCancelled'] = $isCancelled;
		}

		$start = $object['DTSTART'][0] ?? null;
		$end = $object['DTEND'][0] ?? null;
		if (!$start instanceof DateTimeInterface) {
			throw new RuntimeException('Calendar search result does not contain a DTSTART.');
		}
		$hasTime = $this->searchDateHasTime($object, 'DTSTART');
		$payload['start'] = $this->mapDateTime($start, $hasTime);
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
			'subject' => isset($event->SUMMARY) ? (string)$event->SUMMARY : 'Untitled event',
			'body' => [
				'contentType' => 'text',
				'content' => isset($event->DESCRIPTION) ? (string)$event->DESCRIPTION : '',
			],
			'singleValueExtendedProperties' => [
				[
					'id' => 'String {ca1242da-c60a-4be2-a7da-4e82e8e23a01} Name nextcloudCalendarUri',
					'value' => $calendarUri,
				],
				[
					'id' => 'String {ca1242da-c60a-4be2-a7da-4e82e8e23a01} Name nextcloudObjectUri',
					'value' => $objectUri,
				],
				[
					'id' => 'String {ca1242da-c60a-4be2-a7da-4e82e8e23a01} Name nextcloudUid',
					'value' => $uid,
				],
			],
		];

		if (isset($event->LOCATION)) {
			$payload['location'] = [
				'displayName' => (string)$event->LOCATION,
			];
		}
		if (isset($event->STATUS)) {
			$payload['isCancelled'] = strtolower((string)$event->STATUS) === 'cancelled';
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
			$payload['recurrence'] = [
				'pattern' => $this->mapRecurrencePattern($recurrence),
				'range' => $this->mapRecurrenceRange($event),
			];
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
			return [
				'date' => $dateTime->format('Y-m-d'),
				'timeZone' => $dateTime->getTimezone()->getName(),
			];
		}

		return [
			'dateTime' => $dateTime->format(DateTimeInterface::RFC3339),
			'timeZone' => $dateTime->getTimezone()->getName(),
		];
	}

	private function searchDateHasTime(array $object, string $propertyName): bool {
		return !isset($object[$propertyName]['type']) || $object[$propertyName]['type'] !== 'DATE';
	}

	/**
	 * @param string[] $recurrence
	 */
	private function mapRecurrencePattern(array $recurrence): array {
		$pattern = ['type' => 'daily'];
		foreach ($recurrence as $rule) {
			if (str_starts_with($rule, 'RRULE:')) {
				$rrule = substr($rule, 6);
				if (str_starts_with($rrule, 'FREQ=DAILY')) {
					$pattern['type'] = 'daily';
				} elseif (str_starts_with($rrule, 'FREQ=WEEKLY')) {
					$pattern['type'] = 'weekly';
				} elseif (str_starts_with($rrule, 'FREQ=MONTHLY')) {
					$pattern['type'] = 'monthly';
				} elseif (str_starts_with($rrule, 'FREQ=YEARLY')) {
					$pattern['type'] = 'yearly';
				}
				$intervalMatch = [];
				if (preg_match('/INTERVAL=(\d+)/', $rrule, $intervalMatch)) {
					$pattern['interval'] = (int)$intervalMatch[1];
				}
			}
		}

		return $pattern;
	}

	/**
	 * @param string[] $recurrence
	 */
	private function mapRecurrenceRange(VEvent $event): array {
		$range = [
			'startDate' => [
				'date' => $event->DTSTART->getDateTime()->format('Y-m-d'),
			],
			'type' => 'noEnd',
		];
		if (isset($event->DTEND)) {
			$range['endDate'] = [
				'date' => $event->DTEND->getDateTime()->format('Y-m-d'),
			];
			$range['type'] = 'endDate';
		}

		return $range;
	}
}
