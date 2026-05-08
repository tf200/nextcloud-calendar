<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Calendar\Service\Google;

use OCA\Calendar\Db\GoogleAuth;
use OCA\Calendar\Db\GoogleAuthMapper;
use OCP\Http\Client\IClientService;
use OCP\Security\ICrypto;
use RuntimeException;
use function is_array;
use function json_decode;
use function time;

class GoogleTokenService {
	private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

	public function __construct(
		private GoogleConfigService $configService,
		private GoogleAuthMapper $authMapper,
		private IClientService $clientService,
		private ICrypto $crypto,
	) {
	}

	public function getAccessToken(GoogleAuth $auth): string {
		$accessToken = $auth->getAccessToken();
		$expiresAt = $auth->getAccessTokenExpiresAt();
		if ($accessToken !== null && $expiresAt !== null && $expiresAt > time() + 60) {
			return $this->crypto->decrypt($accessToken);
		}

		return $this->refreshAccessToken($auth);
	}

	private function refreshAccessToken(GoogleAuth $auth): string {
		$refreshToken = $auth->getRefreshToken();
		if ($refreshToken === null) {
			throw new RuntimeException('Google refresh token is missing.');
		}

		$response = $this->clientService->newClient()->post(self::TOKEN_URL, [
			'body' => [
				'client_id' => $this->configService->getClientId(),
				'client_secret' => $this->configService->getClientSecret(),
				'refresh_token' => $this->crypto->decrypt($refreshToken),
				'grant_type' => 'refresh_token',
			],
		]);

		$data = json_decode((string)$response->getBody(), true);
		if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300 || !is_array($data)) {
			throw new RuntimeException('Google token refresh failed.');
		}
		if (empty($data['access_token']) || !is_string($data['access_token'])) {
			throw new RuntimeException('Google token refresh did not return an access token.');
		}

		$auth->setAccessToken($this->crypto->encrypt($data['access_token']));
		$auth->setAccessTokenExpiresAt(time() + (isset($data['expires_in']) ? (int)$data['expires_in'] : 3600));
		$auth->setUpdatedAt(time());
		$this->authMapper->update($auth);

		return $data['access_token'];
	}
}
