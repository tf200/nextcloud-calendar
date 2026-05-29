<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Calendar\Controller;

use OCA\Calendar\Http\JsonResponse;
use OCA\Calendar\Service\Microsoft\MicrosoftAuthService;
use OCA\Calendar\Service\Microsoft\MicrosoftConfigService;
use OCA\Calendar\Service\Microsoft\MicrosoftOAuthService;
use OCA\Calendar\Service\Microsoft\MicrosoftSyncService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\IRequest;
use OCP\ISession;
use OCP\Security\ISecureRandom;
use Psr\Log\LoggerInterface;
use Throwable;

class MicrosoftController extends Controller {
	private const SESSION_STATE_KEY = 'calendar_microsoft_oauth_state';

	public function __construct(
		string $appName,
		IRequest $request,
		private ?string $userId,
		private MicrosoftConfigService $configService,
		private MicrosoftOAuthService $oauthService,
		private MicrosoftAuthService $authService,
		private MicrosoftSyncService $syncService,
		private ISession $session,
		private ISecureRandom $random,
		private LoggerInterface $logger,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * @NoAdminRequired
	 */
	public function status(): JsonResponse {
		if ($this->userId === null) {
			return JsonResponse::fail(['message' => 'User is not logged in.'], Http::STATUS_UNAUTHORIZED);
		}

		return JsonResponse::success($this->authService->getStatus($this->userId));
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function connect(): RedirectResponse {
		if ($this->userId === null) {
			return new RedirectResponse($this->configService->getCalendarUrl('error'));
		}
		if (!$this->configService->isConfigured()) {
			return new RedirectResponse($this->configService->getCalendarUrl('not-configured'));
		}

		$state = $this->random->generate(48, ISecureRandom::CHAR_ALPHANUMERIC);
		$this->session->set(self::SESSION_STATE_KEY, [
			'userId' => $this->userId,
			'state' => $state,
			'createdAt' => time(),
		]);

		return new RedirectResponse($this->oauthService->getAuthorizationUrl($state));
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function callback(?string $code = null, ?string $state = null, ?string $error = null): RedirectResponse {
		if ($this->userId === null || $error !== null || $code === null || $state === null) {
			return new RedirectResponse($this->configService->getCalendarUrl('error'));
		}

		$sessionState = $this->session->get(self::SESSION_STATE_KEY);
		$this->session->remove(self::SESSION_STATE_KEY);
		if (!is_array($sessionState)
			|| ($sessionState['userId'] ?? null) !== $this->userId
			|| ($sessionState['state'] ?? null) !== $state) {
			return new RedirectResponse($this->configService->getCalendarUrl('invalid-state'));
		}

		try {
			$token = $this->oauthService->exchangeCode($code);
			$userInfo = $this->oauthService->fetchUserInfo($token['access_token']);
			$this->authService->saveConnection(
				$this->userId,
				$token,
				$userInfo,
				$this->oauthService->getAccessTokenExpiresAt($token),
			);
		} catch (Throwable $e) {
			$this->logger->warning('Microsoft Calendar OAuth connection failed: ' . $e->getMessage(), [
				'app' => $this->appName,
				'exception' => $e,
			]);

			return new RedirectResponse($this->configService->getCalendarUrl('error'));
		}

		try {
			$this->syncService->syncInitial($this->userId);
		} catch (Throwable $e) {
			$this->logger->warning('Initial Microsoft Calendar sync failed: ' . $e->getMessage(), [
				'app' => $this->appName,
				'exception' => $e,
			]);
		}

		return new RedirectResponse($this->configService->getCalendarUrl('connected'));
	}

	/**
	 * @NoAdminRequired
	 */
	public function disconnect(): JsonResponse {
		if ($this->userId === null) {
			return JsonResponse::fail(['message' => 'User is not logged in.'], Http::STATUS_UNAUTHORIZED);
		}

		try {
			$this->authService->disconnect($this->userId);
		} catch (Throwable $e) {
			$this->logger->warning('Microsoft Calendar OAuth disconnect failed: ' . $e->getMessage(), [
				'app' => $this->appName,
				'exception' => $e,
			]);

			return JsonResponse::error('Could not disconnect Microsoft account.');
		}

		return JsonResponse::success();
	}
}
