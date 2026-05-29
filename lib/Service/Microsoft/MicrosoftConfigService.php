<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Calendar\Service\Microsoft;

use OCP\IConfig;
use OCP\IURLGenerator;

class MicrosoftConfigService {
	public const CLIENT_ID_KEY = 'microsoft_client_id';
	public const CLIENT_SECRET_KEY = 'microsoft_client_secret';

	public function __construct(
		private string $appName,
		private IConfig $config,
		private IURLGenerator $urlGenerator,
	) {
	}

	public function getClientId(): string {
		return trim($this->config->getAppValue($this->appName, self::CLIENT_ID_KEY, ''));
	}

	public function getClientSecret(): string {
		return trim($this->config->getAppValue($this->appName, self::CLIENT_SECRET_KEY, ''));
	}

	public function isConfigured(): bool {
		return $this->getClientId() !== '' && $this->getClientSecret() !== '';
	}

	/**
	 * @return string[]
	 */
	public function getMissingConfigKeys(): array {
		$missing = [];
		if ($this->getClientId() === '') {
			$missing[] = self::CLIENT_ID_KEY;
		}
		if ($this->getClientSecret() === '') {
			$missing[] = self::CLIENT_SECRET_KEY;
		}

		return $missing;
	}

	public function getCallbackUrl(): string {
		return $this->urlGenerator->linkToRouteAbsolute('calendar.microsoft.callback');
	}

	public function getCalendarUrl(string $status): string {
		return $this->urlGenerator->linkToRouteAbsolute('calendar.view.index.view.timerange', [
			'view' => 'dayGridMonth',
			'timeRange' => 'now',
			'microsoftSync' => $status,
		]);
	}
}
