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

class Version5050Date20250701000006 extends SimpleMigrationStep {
	#[\Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();

		if ($schema->hasTable('calendar_google_auth')) {
			$table = $schema->getTable('calendar_google_auth');
			if (!$table->hasColumn('google_calendar_id')) {
				$table->addColumn('google_calendar_id', Types::STRING, [
					'notnull' => false,
					'length' => 255,
				]);
			}
			if (!$table->hasColumn('last_sync_at')) {
				$table->addColumn('last_sync_at', Types::BIGINT, [
					'notnull' => false,
				]);
			}
		}

		if ($schema->hasTable('calendar_google_map')) {
			return $schema;
		}

		$table = $schema->createTable('calendar_google_map');
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
		$table->addColumn('google_calendar_id', Types::STRING, [
			'notnull' => true,
			'length' => 255,
		]);
		$table->addColumn('google_event_id', Types::STRING, [
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
		$table->addUniqueIndex(['user_id', 'nextcloud_calendar_uri', 'nextcloud_object_uri'], 'cal_gmap_obj');
		$table->addIndex(['user_id'], 'cal_gmap_user');

		return $schema;
	}
}
