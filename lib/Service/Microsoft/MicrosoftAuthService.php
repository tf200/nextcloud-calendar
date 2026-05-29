<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Calendar\Service\Microsoft;

use OCA\Calendar\Db\MicrosoftAuth;
use OCA\Calendar\Db\MicrosoftAuthMapper;
use OCA\Calendar\Db\MicrosoftSyncMapMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\DB\Exception as DbException;
use OCP\Security\ICrypto;
use function time;

class MicrosoftAuthService {
	public function __construct(
		private MicrosoftAuthMapper $mapper,
		private MicrosoftSyncMapMapper $syncMapMapper,
		private MicrosoftConfigService $configService,
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
			$status['email'] = $auth->getMicrosoftEmail();
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
	public function saveConnection(string $userId, array $token, array $userInfo, int $expiresAt): MicrosoftAuth {
		$now = time();
		try {
			$auth = $this->mapper->findByUserId($userId);
		} catch (DoesNotExistException) {
			$auth = new MicrosoftAuth();
			$auth->setUserId($userId);
			$auth->setCreatedAt($now);
		}

		$auth->setMicrosoftEmail((string)$userInfo['mail']);
		$auth->setMicrosoftId(isset($userInfo['id']) ? (string)$userInfo['id'] : null);
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
