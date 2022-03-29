<?php

namespace OCA\Accessibility;

class DarkThemeProvider extends DefaultProvider {

	public function getMediaQuery(): string {
		return '(prefers-color-scheme: dark)';
	}

	public function getCSSVariables(): array {
		$variables = parent::getCSSVariables();

		// FIXME …
		$variables = $variables;

		return $variables;
	}
}
