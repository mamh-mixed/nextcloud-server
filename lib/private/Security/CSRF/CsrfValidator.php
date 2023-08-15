<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2023 Daniel Kesselberg <mail@danielkesselberg.de>
 *
 * @author Daniel Kesselberg <mail@danielkesselberg.de>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OC\Security\CSRF;

use OCP\IRequest;
use OCP\Security\CSRF\ICsrfValidator;

class CsrfValidator implements ICsrfValidator {
	public function __construct(
		private CsrfTokenManager $csrfTokenManager) {
	}

	public function validate(IRequest $request): bool {
		if (!$request->passesStrictCookieCheck()) {
			return false;
		}

		$token = $request->getParam('requesttoken', '');
		if ($token === '') {
			$token = $request->getHeader('REQUESTTOKEN');
		}
		if ($token === '') {
			return false;
		}

		$token = new CsrfToken($token);

		return $this->csrfTokenManager->isTokenValid($token);
	}
}
