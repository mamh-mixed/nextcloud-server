<?php
/**
 * @copyright Copyright (c) 2022 John Molakvoæ <skjnldsv@protonmail.com>
 *
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

use OCP\Util;

class ThemeInjectionService {

	private ThemesService $themesService;
	private DefaultTheme $defaultTheme;

	public function __construct(ThemesService $themesService,
								DefaultTheme $defaultTheme) {
		$this->themesService = $themesService;
		$this->defaultTheme = $defaultTheme;
	}

	public function injectHeaders() {
		$themes = $this->themesService->getThemes();
		$defaultTheme = $themes[$this->defaultTheme->id];
		$mediaThemes = array_filter($themes, function($theme) {
			// Check if the theme provides a media query
			return !!$theme->getMediaQuery();
		});

		// Default theme fallback
		$this->addThemeHeader($defaultTheme->id);
		
		// Themes applied by media queries
		foreach($mediaThemes as $theme) {
			$this->addThemeHeader($theme->id, true, $theme->getMediaQuery());
		}

		// Themes 
		foreach($this->themesService->getThemes() as $theme) {
			$this->addThemeHeader($theme->id, false);
		}
	}

	private function addThemeHeader(string $themeId, bool $plain = false, string $media = null) {
		$linkToCSS = $this->urlGenerator->linkToRoute('theming.Theming.getThemeVariables', [
			'themeId' => $themeId,
			'plain' => $plain,
		]);
		Util::addHeader('link', [
			'rel' => 'stylesheet',
			'media' => $media,
			'href' => $linkToCSS,
		]);
	}
}
