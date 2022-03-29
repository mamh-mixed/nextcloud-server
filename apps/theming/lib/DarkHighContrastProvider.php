<?php

namespace OCA\Accessibility;

class DarkHighContrastProvider extends DefaultProvider {

	public function getMediaQuery(): string {
		return '(prefers-color-scheme: dark) and (prefers-contrast: more)';
	}

	public function getCSSVariables(): array {
		$variables = parent::getCSSVariables();

		// FIXME …
		$variables = $variables;

		return $variables;
	}
}
