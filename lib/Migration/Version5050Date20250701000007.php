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

class Version5050Date20250701000007 extends SimpleMigrationStep {
	#[\Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();

		if (!$schema->hasTable('calendar_proposal_dts')) {
			return $schema;
		}

		$table = $schema->getTable('calendar_proposal_dts');
		if (!$table->hasColumn('project_id')) {
			$table->addColumn('project_id', Types::BIGINT, [
				'notnull' => false,
				'unsigned' => true,
			]);
		}

		return $schema;
	}
}
