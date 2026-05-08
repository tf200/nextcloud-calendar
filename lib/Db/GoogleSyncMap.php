<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Calendar\Db;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * @method int getId()
 * @method void setId(int $id)
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method string getNextcloudCalendarUri()
 * @method void setNextcloudCalendarUri(string $nextcloudCalendarUri)
 * @method string getNextcloudObjectUri()
 * @method void setNextcloudObjectUri(string $nextcloudObjectUri)
 * @method string getNextcloudUid()
 * @method void setNextcloudUid(string $nextcloudUid)
 * @method string getGoogleCalendarId()
 * @method void setGoogleCalendarId(string $googleCalendarId)
 * @method string getGoogleEventId()
 * @method void setGoogleEventId(string $googleEventId)
 * @method string|null getEventHash()
 * @method void setEventHash(?string $eventHash)
 * @method string|null getLastError()
 * @method void setLastError(?string $lastError)
 * @method int|null getLastSyncedAt()
 * @method void setLastSyncedAt(?int $lastSyncedAt)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $updatedAt)
 */
class GoogleSyncMap extends Entity {
	protected $userId = '';
	protected $nextcloudCalendarUri = '';
	protected $nextcloudObjectUri = '';
	protected $nextcloudUid = '';
	protected $googleCalendarId = '';
	protected $googleEventId = '';
	protected $eventHash;
	protected $lastError;
	protected $lastSyncedAt;
	protected $createdAt;
	protected $updatedAt;

	public function __construct() {
		$this->addType('lastSyncedAt', Types::BIGINT);
		$this->addType('createdAt', Types::BIGINT);
		$this->addType('updatedAt', Types::BIGINT);
	}
}
