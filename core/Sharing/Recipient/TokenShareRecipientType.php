<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OC\Core\Sharing\Recipient;

use OC\Core\AppInfo\Application;
use OCP\IUser;
use OCP\L10N\IFactory;
use OCP\Security\ISecureRandom;
use OCP\Server;
use OCP\Share\IManager;
use OCP\Sharing\Icon\ShareIconSVG;
use OCP\Sharing\Icon\ShareIconURL;
use OCP\Sharing\Recipient\IShareRecipientType;
use OCP\Sharing\Recipient\IShareRecipientTypeSearch;
use OCP\Sharing\Recipient\IShareRecipientTypeUpdatableSecret;
use OCP\Sharing\Recipient\ShareRecipient;
use OCP\Sharing\ShareAccessContext;

final class TokenShareRecipientType implements IShareRecipientType, IShareRecipientTypeSearch, IShareRecipientTypeUpdatableSecret {
	private const VALUE_LENGTH = 8;

	#[\Override]
	public function getDisplayName(): string {
		return Server::get(IFactory::class)->get(Application::APP_ID)->t('Public link');
	}

	#[\Override]
	public function validateRecipient(IUser $owner, string $recipient): bool {
		if (strlen($recipient) !== self::VALUE_LENGTH) {
			return false;
		}

		return Server::get(IManager::class)->shareApiAllowLinks($owner);
	}

	#[\Override]
	public function getRecipients(?IUser $currentUser, mixed $arguments): array {
		return [];
	}

	#[\Override]
	public function getRecipientDisplayName(string $recipient): ?string {
		return null;
	}

	#[\Override]
	public function getRecipientIcon(string $recipient): null|ShareIconSVG|ShareIconURL {
		return null;
	}

	#[\Override]
	public function searchRecipients(ShareAccessContext $accessContext, string $query, int $limit, int $offset): array {
		// TODO: Create a custom interface for this use case?
		if ($query === '' && $offset === 0) {
			return [
				new ShareRecipient(
					self::class,
					// This is not a secret value. It is only used to work around the unique constraint, so it's possible to have multiple token recipients per share.
					Server::get(ISecureRandom::class)->generate(self::VALUE_LENGTH, ISecureRandom::CHAR_HUMAN_READABLE),
					null,
				),
			];
		}

		return [];
	}

	#[\Override]
	public function isSecretUpdatable(string $recipient): bool {
		return Server::get(IManager::class)->allowCustomTokens();
	}
}
