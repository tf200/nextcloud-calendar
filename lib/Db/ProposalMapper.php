<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Calendar\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<ProposalDetailsEntry>
 */
class ProposalMapper extends QBMapper {

	public function __construct(
		IDBConnection $db,
	) {
		$this->tableName = 'calendar_proposal_dts';
		parent::__construct($db, $this->tableName, ProposalDetailsEntry::class);
	}

	public function fetchById(string $userId, int $id): ?ProposalDetailsEntry {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where(
				$qb->expr()->eq('uid', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)),
				$qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			);
		return $this->findEntity($qb);
	}

	public function fetchByUserId(string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where(
				$qb->expr()->eq('uid', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
			);
		return $this->findEntities($qb);
	}

	/**
	 * Fetch proposals by project ID, sorted by most recent, with pagination.
	 *
	 * @param string $userId
	 * @param int $projectId
	 * @param int $limit
	 * @param int $offset
	 * @return array<ProposalDetailsEntry>
	 */
	public function fetchByProjectId(string $userId, int $projectId, int $limit = 20, int $offset = 0): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where(
				$qb->expr()->eq('uid', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)),
				$qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_INT))
			)
			->orderBy('id', 'DESC')
			->setMaxResults($limit)
			->setFirstResult($offset);
		return $this->findEntities($qb);
	}

	/**
	 * Fetch confirmed meetings by project ID from calendar objects.
	 *
	 * @param string $userId
	 * @param int $projectId
	 * @return array
	 */
	public function fetchConfirmedMeetingsByProjectId(string $userId, int $projectId): array {
		$principalUri = 'principals/users/' . $userId;
		$pattern = '%X-NC-PROJECT-ID:' . $projectId . '%';

		$qb = $this->db->getQueryBuilder();
		$qb->select('c.id', 'c.calendardata', 'c.uid', 'c.uri')
			->from('calendarobjects', 'c')
			->innerJoin('c', 'calendars', 'cal', $qb->expr()->eq('c.calendarid', 'cal.id'))
			->where(
				$qb->expr()->eq('cal.principaluri', $qb->createNamedParameter($principalUri, IQueryBuilder::PARAM_STR)),
				$qb->expr()->like('c.calendardata', $qb->createNamedParameter($pattern, IQueryBuilder::PARAM_STR)),
				$qb->expr()->isNull('c.deleted_at')
			)
			->orderBy('c.id', 'DESC');

		$result = $qb->executeQuery();
		$rows = [];
		while ($row = $result->fetch()) {
			$rows[] = $row;
		}

		return $rows;
	}

	public function deleteById(string $userId, int $id): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->tableName)
			->where(
				$qb->expr()->eq('uid', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)),
				$qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT))
			);
		$qb->executeStatement();
	}

	public function deleteByUserId(string $userId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->tableName)
			->where(
				$qb->expr()->eq('uid', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR))
			);
		$qb->executeStatement();
	}

}
