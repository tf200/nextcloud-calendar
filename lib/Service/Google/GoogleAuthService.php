<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Calendar\Service\Google;

use OCA\Calendar\Db\GoogleAuth;
use OCA\Calendar\Db\GoogleAuthMapper;
use OCA\Calendar\Db\GoogleSyncMapMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\DB\Exception as DbException;
use OCP\Security\ICrypto;
use function time;

class GoogleAuthService {
	public function __construct(
		private GoogleAuthMapper $mapper,
		private GoogleSyncMapMapper $syncMapMapper,
		private GoogleConfigService $configService,
		private ICrypto $crypto,
	) {
	}

	/**
	 * @return array<string,mixed>
	 */
	public function getStatus(string $userId): array {
		$status = [
			'configured' => $this->configService->isConfigured(),
			'connected' => false,
			'callbackUrl' => $this->configService->getCallbackUrl(),
			'missing' => $this->configService->getMissingConfigKeys(),
		];

		try {
			$auth = $this->mapper->findByUserId($userId);
			$status['connected'] = true;
			$status['email'] = $auth->getGoogleEmail();
			$status['connectedAt'] = $auth->getCreatedAt();
			$status['updatedAt'] = $auth->getUpdatedAt();
		} catch (DoesNotExistException|MultipleObjectsReturnedException|DbException) {
		}

		return $status;
	}

	/**
	 * @param array<string,mixed> $token
	 * @param array<string,mixed> $userInfo
	 * @throws DbException
	 * @throws MultipleObjectsReturnedException
	 */
	public function saveConnection(string $userId, array $token, array $userInfo, int $expiresAt): GoogleAuth {
		$now = time();
		try {
			$auth = $this->mapper->findByUserId($userId);
		} catch (DoesNotExistException) {
			$auth = new GoogleAuth();
			$auth->setUserId($userId);
			$auth->setCreatedAt($now);
		}

		$auth->setGoogleEmail((string)$userInfo['email']);
		$auth->setGoogleSub(isset($userInfo['sub']) ? (string)$userInfo['sub'] : null);
		$auth->setAccessToken($this->crypto->encrypt((string)$token['access_token']));
		$auth->setRefreshToken($this->crypto->encrypt((string)$token['refresh_token']));
		$auth->setAccessTokenExpiresAt($expiresAt);
		$auth->setScope(isset($token['scope']) ? (string)$token['scope'] : null);
		$auth->setTokenType(isset($token['token_type']) ? (string)$token['token_type'] : null);
		$auth->setUpdatedAt($now);

		if ($auth->getId() === null) {
			return $this->mapper->insert($auth);
		}

		return $this->mapper->update($auth);
	}

	/**
	 * @throws DbException
	 */
	public function disconnect(string $userId): void {
		$this->syncMapMapper->deleteByUserId($userId);
		$this->mapper->deleteByUserId($userId);
	}
}
