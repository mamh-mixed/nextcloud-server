<?php

namespace OCA\Accessibility;

class HighContrastProvider extends DefaultProvider {

	public function getMediaQuery(): string {
		return '(prefers-contrast: more)';
	}

	public function getCSSVariables(): array {
		$variables = parent::getCSSVariables();

		// FIXME …
		$variables = $variables;

		return $variables;
	}
}
