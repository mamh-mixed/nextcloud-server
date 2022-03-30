<?php
declare(strict_types=1);
/**
 * @copyright Copyright (c) 2022 Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ <skjnldsv@protonmail.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */
namespace OCA\Theming\Themes;

use OCA\Theming\ThemingDefaults;
use OCA\Theming\Util;
use OCP\Theming\ITheme;

class DefaultTheme implements ITheme {
	public Util $util;
	public ThemingDefaults $themingDefaults;
	public string $primaryColor;

	public function __construct(Util $util, ThemingDefaults $themingDefaults) {
		$this->util = $util;
		$this->themingDefaults = $themingDefaults;

		$this->primaryColor = $this->themingDefaults->getColorPrimary();
	}

	public function getId(): string {
		return 'default';
	}

	public function getMediaQuery(): string {
		return '';
	}

	public function getCSSVariables(): array {
		$colorMainText = '#222222';
		$colorMainBackground = '#ffffff';

		return [
			'--color-main-text' => $colorMainText,
			'--color-main-background' => $colorMainBackground,
			'--color-main-background-translucent' => 'rgba(var(--color-main-background), .97)',

			// To use like this: background-image: linear-gradient(0, var('--gradient-main-background));
			'--gradient-main-background' => 'var(--color-main-background) 0%, var(--color-main-background-translucent) 85%, transparent 100%',

			'--color-background-hover' => $this->util->darken($colorMainBackground, .04),
			'--color-background-dark' => $this->util->darken($colorMainBackground, .07),
			'--color-background-darker' => $this->util->darken($colorMainBackground, .14),

			'--color-placeholder-light' => $this->util->darken($colorMainBackground, .1),
			'--color-placeholder-dark' => $this->util->darken($colorMainBackground, .2),

			'--color-primary' => $this->primaryColor,
			'--color-primary-text' => $this->util->invertTextColor($this->primaryColor) ? '#000000' : '#ffffff',
			'--color-primary-hover' => $this->util->mix($this->primaryColor, $colorMainBackground, 0.8),
			'--color-primary-light' => $this->util->mix($this->primaryColor, $colorMainBackground, 0.1),
			'--color-primary-light-text' => $this->primaryColor,
			'--color-primary-light-hover' => $this->util->mix($this->primaryColor, $colorMainText, 0.1),
			'--color-primary-text-dark' => $this->util->darken($this->util->invertTextColor($this->primaryColor) ? '#000000' : '#ffffff', .07),
			'--color-primary-element' => $this->util->elementColor($this->primaryColor),
			'--color-primary-element-hover' => $this->util->mix($this->util->elementColor($this->primaryColor), $colorMainBackground, 0.8),
			'--color-primary-element-light' => $this->util->lighten($this->util->elementColor($this->primaryColor), .15),
			'--color-primary-element-lighter' => $this->util->mix($this->util->elementColor($this->primaryColor), $colorMainBackground, 0.15),

			'--color-error' => '#e9322d',
			'--color-error-hover' => $this->util->mix('#e9322d', $colorMainBackground, 0.8),
			'--color-warning' => '#eca700',
			'--color-warning-hover' => $this->util->mix('#eca700', $colorMainBackground, 0.8),
			'--color-success' => '#46ba61',
			'--color-success-hover' => $this->util->mix('#46ba61', $colorMainBackground, 0.8),

			'--color-text-maxcontrast' => $this->util->lighten($colorMainText, .33),
			'--color-text-light' => $colorMainText,
			'--color-text-lighter' => $this->util->lighten($colorMainText, .33),

			'--color-loading-light' => '#cccccc',
			'--color-loading-dark' => '#444444',

			'--color-box-shadow' => 'transparentize(nc-darken($color-main-background, 70%), 0.5)',

			'--color-border' => $this->util->darken($colorMainBackground, .07),
			'--color-border-dark' => $this->util->darken($colorMainBackground, .14),

			// FIXME Add once we start supporting "(prefers-reduced-motion)"
			// '--animation-quick' => '$animation-quick',
			// '--animation-slow' => '$animation-slow',
		];
	}
}
