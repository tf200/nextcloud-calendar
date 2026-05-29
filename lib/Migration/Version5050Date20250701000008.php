<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Calendar\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version5050Date20250701000008 extends SimpleMigrationStep {
	#[\Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();

		if ($schema->hasTable('cal_ms_auth')) {
			$table = $schema->getTable('cal_ms_auth');
			if (!$table->hasColumn('microsoft_calendar_id')) {
				$table->addColumn('microsoft_calendar_id', Types::STRING, [
					'notnull' => false,
					'length' => 255,
				]);
			}
			if (!$table->hasColumn('last_sync_at')) {
				$table->addColumn('last_sync_at', Types::BIGINT, [
					'notnull' => false,
				]);
			}
		} else {
			$table = $schema->createTable('cal_ms_auth');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('user_id', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('microsoft_email', Types::STRING, [
				'notnull' => true,
				'length' => 255,
			]);
			$table->addColumn('microsoft_id', Types::STRING, [
				'notnull' => false,
				'length' => 255,
			]);
			$table->addColumn('access_token', Types::TEXT, [
				'notnull' => false,
			]);
			$table->addColumn('refresh_token', Types::TEXT, [
				'notnull' => false,
			]);
			$table->addColumn('access_token_expires_at', Types::BIGINT, [
				'notnull' => false,
			]);
			$table->addColumn('scope', Types::TEXT, [
				'notnull' => false,
			]);
			$table->addColumn('token_type', Types::STRING, [
				'notnull' => false,
				'length' => 32,
			]);
			$table->addColumn('microsoft_calendar_id', Types::STRING, [
				'notnull' => false,
				'length' => 255,
			]);
			$table->addColumn('last_sync_at', Types::BIGINT, [
				'notnull' => false,
			]);
			$table->addColumn('created_at', Types::BIGINT, [
				'notnull' => true,
			]);
			$table->addColumn('updated_at', Types::BIGINT, [
				'notnull' => true,
			]);

			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['user_id'], 'cal_ms_auth_uid');
			$table->addIndex(['microsoft_email'], 'cal_ms_auth_email');
		}

		if ($schema->hasTable('cal_ms_map')) {
			return $schema;
		}

		$table = $schema->createTable('cal_ms_map');
		$table->addColumn('id', Types::BIGINT, [
			'autoincrement' => true,
			'notnull' => true,
			'unsigned' => true,
		]);
		$table->addColumn('user_id', Types::STRING, [
			'notnull' => true,
			'length' => 64,
		]);
		$table->addColumn('nextcloud_calendar_uri', Types::STRING, [
			'notnull' => true,
			'length' => 255,
		]);
		$table->addColumn('nextcloud_object_uri', Types::STRING, [
			'notnull' => true,
			'length' => 255,
		]);
		$table->addColumn('nextcloud_uid', Types::STRING, [
			'notnull' => true,
			'length' => 255,
		]);
		$table->addColumn('microsoft_calendar_id', Types::STRING, [
			'notnull' => true,
			'length' => 255,
		]);
		$table->addColumn('microsoft_event_id', Types::STRING, [
			'notnull' => true,
			'length' => 255,
		]);
		$table->addColumn('event_hash', Types::STRING, [
			'notnull' => false,
			'length' => 64,
		]);
		$table->addColumn('last_error', Types::TEXT, [
			'notnull' => false,
		]);
		$table->addColumn('last_synced_at', Types::BIGINT, [
			'notnull' => false,
		]);
		$table->addColumn('created_at', Types::BIGINT, [
			'notnull' => true,
		]);
		$table->addColumn('updated_at', Types::BIGINT, [
			'notnull' => true,
		]);

		$table->setPrimaryKey(['id']);
		$table->addUniqueIndex(['user_id', 'nextcloud_calendar_uri', 'nextcloud_object_uri'], 'cal_msmap_obj');
		$table->addIndex(['user_id'], 'cal_msmap_user');

		return $schema;
	}
}
