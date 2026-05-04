<?php

/*
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);


namespace OCP\Sharing\Recipient;

final readonly class ShareRecipientWithInternalDetails extends ShareRecipient {
	public function __construct(
		/** @var non-empty-string $id */
		public string $id,
		/** @var ?non-empty-string $parentId */
		public ?string $parentId,
		/** @var class-string<IShareRecipientType> $class */
		public string $class,
		/** @var non-empty-string $value */
		public string $value,
		/** @var ?non-empty-string $instance */
		public ?string $instance,
		/** @var non-empty-string $secret */
		public string $secret,
	) {
	}
}
