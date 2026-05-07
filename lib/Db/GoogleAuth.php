<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Calendar\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;
use ReturnTypeWillChange;

/**
 * @method int getId()
 * @method void setId(int $id)
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method string getGoogleEmail()
 * @method void setGoogleEmail(string $googleEmail)
 * @method string|null getGoogleSub()
 * @method void setGoogleSub(?string $googleSub)
 * @method string|null getAccessToken()
 * @method void setAccessToken(?string $accessToken)
 * @method string|null getRefreshToken()
 * @method void setRefreshToken(?string $refreshToken)
 * @method int|null getAccessTokenExpiresAt()
 * @method void setAccessTokenExpiresAt(?int $accessTokenExpiresAt)
 * @method string|null getScope()
 * @method void setScope(?string $scope)
 * @method string|null getTokenType()
 * @method void setTokenType(?string $tokenType)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $updatedAt)
 */
class GoogleAuth extends Entity implements JsonSerializable {
	protected $userId = '';
	protected $googleEmail = '';
	protected $googleSub;
	protected $accessToken;
	protected $refreshToken;
	protected $accessTokenExpiresAt;
	protected $scope;
	protected $tokenType;
	protected $createdAt;
	protected $updatedAt;

	public function __construct() {
		$this->addType('accessTokenExpiresAt', Types::BIGINT);
		$this->addType('createdAt', Types::BIGINT);
		$this->addType('updatedAt', Types::BIGINT);
	}

	#[\Override]
	#[ReturnTypeWillChange]
	public function jsonSerialize() {
		return [
			'id' => $this->id,
			'userId' => $this->getUserId(),
			'googleEmail' => $this->getGoogleEmail(),
			'googleSub' => $this->getGoogleSub(),
			'accessTokenExpiresAt' => $this->getAccessTokenExpiresAt(),
			'scope' => $this->getScope(),
			'tokenType' => $this->getTokenType(),
			'createdAt' => $this->getCreatedAt(),
			'updatedAt' => $this->getUpdatedAt(),
		];
	}
}
