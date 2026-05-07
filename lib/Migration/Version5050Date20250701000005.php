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

class Version5050Date20250701000005 extends SimpleMigrationStep {
	#[\Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();

		if ($schema->hasTable('calendar_google_auth')) {
			return $schema;
		}

		$table = $schema->createTable('calendar_google_auth');
		$table->addColumn('id', Types::BIGINT, [
			'autoincrement' => true,
			'notnull' => true,
			'unsigned' => true,
		]);
		$table->addColumn('user_id', Types::STRING, [
			'notnull' => true,
			'length' => 64,
		]);
		$table->addColumn('google_email', Types::STRING, [
			'notnull' => true,
			'length' => 255,
		]);
		$table->addColumn('google_sub', Types::STRING, [
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
		$table->addColumn('created_at', Types::BIGINT, [
			'notnull' => true,
		]);
		$table->addColumn('updated_at', Types::BIGINT, [
			'notnull' => true,
		]);

		$table->setPrimaryKey(['id']);
		$table->addUniqueIndex(['user_id'], 'cal_google_auth_uid');
		$table->addIndex(['google_email'], 'cal_google_auth_email');

		return $schema;
	}
}
