<?php

namespace OCA\Accessibility;

use OCA\Theming\Util;

class DefaultProvider {
	public Util $util;
	public string $primaryColor;

	public function __construct(Util $util, string $primaryColor) {
		$this->util = $util;
		$this->primaryColor = $primaryColor;
	}

	public function getMediaQuery(): string {
		return '';
	}

	public function getPrimaryColor(): string {
		return $this->primaryColor;
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
