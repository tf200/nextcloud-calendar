<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Calendar\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception as DbException;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<GoogleSyncMap>
 */
class GoogleSyncMapMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'calendar_google_map', GoogleSyncMap::class);
	}

	/**
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws DbException
	 */
	public function findByObject(string $userId, string $calendarUri, string $objectUri): GoogleSyncMap {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('nextcloud_calendar_uri', $qb->createNamedParameter($calendarUri, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('nextcloud_object_uri', $qb->createNamedParameter($objectUri, IQueryBuilder::PARAM_STR)));

		return $this->findEntity($qb);
	}

	/**
	 * @return GoogleSyncMap[]
	 * @throws DbException
	 */
	public function findFailed(int $limit = 50): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->isNotNull('last_error'))
			->setMaxResults($limit);

		return $this->findEntities($qb);
	}

	/**
	 * @throws DbException
	 */
	public function deleteByUserId(string $userId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)));

		return $qb->executeStatement();
	}
}
