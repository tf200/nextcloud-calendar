<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Calendar\Service\Google;

use OCP\Http\Client\IClientService;
use RuntimeException;
use function http_build_query;
use function is_array;
use function json_decode;
use function sprintf;
use function time;

class GoogleOAuthService {
	private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
	private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
	private const USERINFO_URL = 'https://openidconnect.googleapis.com/v1/userinfo';
	private const SCOPES = [
		'openid',
		'email',
		'https://www.googleapis.com/auth/calendar',
	];

	public function __construct(
		private GoogleConfigService $configService,
		private IClientService $clientService,
	) {
	}

	public function getAuthorizationUrl(string $state): string {
		return self::AUTH_URL . '?' . http_build_query([
			'client_id' => $this->configService->getClientId(),
			'redirect_uri' => $this->configService->getCallbackUrl(),
			'response_type' => 'code',
			'scope' => implode(' ', self::SCOPES),
			'access_type' => 'offline',
			'prompt' => 'consent',
			'include_granted_scopes' => 'true',
			'state' => $state,
		]);
	}

	/**
	 * @return array{access_token:string, expires_in?:int, refresh_token?:string, scope?:string, token_type?:string}
	 */
	public function exchangeCode(string $code): array {
		$client = $this->clientService->newClient();
		$response = $client->post(self::TOKEN_URL, [
			'body' => [
				'code' => $code,
				'client_id' => $this->configService->getClientId(),
				'client_secret' => $this->configService->getClientSecret(),
				'redirect_uri' => $this->configService->getCallbackUrl(),
				'grant_type' => 'authorization_code',
			],
		]);

		$data = $this->decodeJsonResponse((string)$response->getBody(), 'token exchange');
		if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
			throw new RuntimeException('Google token exchange failed.');
		}
		if (empty($data['access_token']) || !is_string($data['access_token'])) {
			throw new RuntimeException('Google did not return an access token.');
		}
		if (empty($data['refresh_token']) || !is_string($data['refresh_token'])) {
			throw new RuntimeException('Google did not return a refresh token. Revoke app access in Google and connect again.');
		}

		return $data;
	}

	/**
	 * @return array{email:string, sub?:string}
	 */
	public function fetchUserInfo(string $accessToken): array {
		$client = $this->clientService->newClient();
		$response = $client->get(self::USERINFO_URL, [
			'headers' => [
				'Authorization' => 'Bearer ' . $accessToken,
			],
		]);

		$data = $this->decodeJsonResponse((string)$response->getBody(), 'userinfo request');
		if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
			throw new RuntimeException('Google userinfo request failed.');
		}
		if (empty($data['email']) || !is_string($data['email'])) {
			throw new RuntimeException('Google did not return an account email.');
		}

		return $data;
	}

	/**
	 * @param array{access_token:string, expires_in?:int} $token
	 */
	public function getAccessTokenExpiresAt(array $token): int {
		$expiresIn = isset($token['expires_in']) && is_numeric($token['expires_in']) ? (int)$token['expires_in'] : 3600;

		return time() + $expiresIn;
	}

	/**
	 * @return array<string,mixed>
	 */
	private function decodeJsonResponse(string $body, string $context): array {
		$data = json_decode($body, true);
		if (!is_array($data)) {
			throw new RuntimeException(sprintf('Invalid Google %s response.', $context));
		}

		return $data;
	}
}
