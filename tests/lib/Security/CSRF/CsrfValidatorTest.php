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

namespace Test\Security\CSRF;

use OC\Security\CSRF\CsrfTokenManager;
use OC\Security\CSRF\CsrfValidator;
use OCP\IRequest;
use Test\TestCase;

class CsrfValidatorTest extends TestCase {
	private CsrfTokenManager $csrfTokenManager;
	private CsrfValidator $csrfValidator;

	protected function setUp(): void {
		parent::setUp();

		$this->csrfTokenManager = $this->createMock(CsrfTokenManager::class);
		$this->csrfValidator = new CsrfValidator($this->csrfTokenManager);
	}

	public function testFailStrictCookieCheck(): void {
		$request = $this->createMock(IRequest::class);
		$request->method('passesStrictCookieCheck')
			->willReturn(false);

		$this->assertFalse($this->csrfValidator->validate($request));
	}

	public function testFailMissingToken(): void {
		$request = $this->createMock(IRequest::class);
		$request->method('passesStrictCookieCheck')
			->willReturn(true);
		$request->method('getParam')
			->with('requesttoken', '')
			->willReturn('');
		$request->method('getHeader')
			->with('REQUESTTOKEN')
			->willReturn('');

		$this->assertFalse($this->csrfValidator->validate($request));
	}

	public function testFailInvalidToken(): void {
		$request = $this->createMock(IRequest::class);
		$request->method('passesStrictCookieCheck')
			->willReturn(true);
		$request->method('getParam')
			->with('requesttoken', '')
			->willReturn('token123');
		$request->method('getHeader')
			->with('REQUESTTOKEN')
			->willReturn('');

		$this->csrfTokenManager
			->method('isTokenValid')
			->willReturn(false);

		$this->assertFalse($this->csrfValidator->validate($request));
	}

	public function testPass(): void {
		$request = $this->createMock(IRequest::class);
		$request->method('passesStrictCookieCheck')
			->willReturn(true);
		$request->method('getParam')
			->with('requesttoken', '')
			->willReturn('token123');
		$request->method('getHeader')
			->with('REQUESTTOKEN')
			->willReturn('');

		$this->csrfTokenManager
			->method('isTokenValid')
			->willReturn(true);

		$this->assertTrue($this->csrfValidator->validate($request));
	}
}
