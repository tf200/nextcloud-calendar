<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Calendar\BackgroundJob;

use OCA\Calendar\Db\MicrosoftAuthMapper;
use OCA\Calendar\Service\Microsoft\MicrosoftSyncService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;
use Throwable;
use function method_exists;

class MicrosoftSyncJob extends TimedJob {
	public function __construct(
		ITimeFactory $time,
		private MicrosoftAuthMapper $authMapper,
		private MicrosoftSyncService $syncService,
		private LoggerInterface $logger,
	) {
		parent::__construct($time);
		$this->setInterval(30 * 60);
		if (method_exists($this, 'setTimeSensitivity')) {
			$this->setTimeSensitivity(self::TIME_INSENSITIVE);
		}
	}

	#[\Override]
	protected function run($argument): void {
		foreach ($this->authMapper->findAllConnected() as $auth) {
			try {
				$this->syncService->syncInitial($auth->getUserId());
			} catch (Throwable $e) {
				$this->logger->warning('Microsoft Calendar background sync failed: ' . $e->getMessage(), [
					'app' => 'calendar',
					'exception' => $e,
				]);
			}
		}

		$this->syncService->retryFailed();
	}
}
