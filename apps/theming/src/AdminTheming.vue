<!--
  - @copyright 2022 Christopher Ng <chrng8@gmail.com>
  -
  - @author Christopher Ng <chrng8@gmail.com>
  -
  - @license AGPL-3.0-or-later
  -
  - This program is free software: you can redistribute it and/or modify
  - it under the terms of the GNU Affero General Public License as
  - published by the Free Software Foundation, either version 3 of the
  - License, or (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
  -
-->

<template>
	<section>
		<NcSettingsSection :title="t('theming', 'Theming')"
			:description="t('theming', 'Theming makes it possible to easily customize the look and feel of your instance and supported clients. This will be visible for all users.')"
			:doc-url="docUrl">
			<div class="admin-theming">
				<NcNoteCard v-if="!isThemable"
					type="error"
					:show-alert="true">
					<p>{{ notThemableErrorMessage }}</p>
				</NcNoteCard>
				<TextField v-for="setting in textFieldSettings"
					:key="setting.name"
					:name="setting.name"
					:value.sync="setting.value"
					:default-value="setting.defaultValue"
					:type="setting.type"
					:display-name="setting.displayName"
					:placeholder="setting.placeholder"
					:maxlength="setting.maxlength"
					@update:theming="$emit('update:theming')" />
				<ColorPickerField :name="colorPickerSetting.name"
					:value.sync="colorPickerSetting.value"
					:default-value="colorPickerSetting.defaultValue"
					:display-name="colorPickerSetting.displayName"
					@update:has-custom-primary-color="value => hasCustomPrimaryColor = value"
					@update:theming="$emit('update:theming')" />
				<FileInputField v-for="setting in fileInputSettings"
					:key="setting.name"
					:name="setting.name"
					:mime-name="setting.mimeName"
					:mime-value.sync="setting.mimeValue"
					:default-mime-value="setting.defaultMimeValue"
					:display-name="setting.displayName"
					:aria-label="setting.ariaLabel"
					:has-custom-primary-color="hasCustomPrimaryColor"
					@update:theming="$emit('update:theming')" />
				<div class="admin-theming__preview">
					<div class="admin-theming__preview-logo" />
				</div>
			</div>
		</NcSettingsSection>
		<NcSettingsSection :title="t('theming', 'Advanced options')">
			<div class="admin-theming-advanced">
				<TextField v-for="setting in advancedTextFieldSettings"
					:key="setting.name"
					:name="setting.name"
					:value.sync="setting.value"
					:default-value="setting.defaultValue"
					:type="setting.type"
					:display-name="setting.displayName"
					:placeholder="setting.placeholder"
					:maxlength="setting.maxlength"
					@update:theming="$emit('update:theming')" />
				<FileInputField v-for="setting in advancedFileInputSettings"
					:key="setting.name"
					:name="setting.name"
					:mime-name="setting.mimeName"
					:mime-value.sync="setting.mimeValue"
					:default-mime-value="setting.defaultMimeValue"
					:display-name="setting.displayName"
					:aria-label="setting.ariaLabel"
					@update:theming="$emit('update:theming')" />
				<a v-if="!canThemeIcons"
					:href="docUrlIcons"
					rel="noreferrer noopener">
					<em>{{ t('theming', 'Install the ImageMagick PHP extension with support for SVG images to automatically generate favicons based on the uploaded logo and color.') }}</em>
				</a>
			</div>
		</NcSettingsSection>
	</section>
</template>

<script>
import { loadState } from '@nextcloud/initial-state'

import {
	NcNoteCard,
	NcSettingsSection,
} from '@nextcloud/vue'
import ColorPickerField from './components/admin/ColorPickerField.vue'
import FileInputField from './components/admin/FileInputField.vue'
import TextField from './components/admin/TextField.vue'

const {
	backgroundMime,
	canThemeIcons,
	color,
	docUrl,
	docUrlIcons,
	faviconMime,
	hasCustomPrimaryColor,
	isThemable,
	legalNoticeUrl,
	logoheaderMime,
	logoMime,
	name,
	notThemableErrorMessage,
	privacyPolicyUrl,
	slogan,
	url,
} = loadState('theming', 'adminThemingParameters')

const textFieldSettings = [
	{
		name: 'name',
		value: name,
		defaultValue: 'Nextcloud',
		type: 'text',
		displayName: t('theming', 'Name'),
		placeholder: t('theming', 'Name'),
		maxlength: 250,
	},
	{
		name: 'url',
		value: url,
		defaultValue: 'https://nextcloud.com',
		type: 'url',
		displayName: t('theming', 'Web link'),
		placeholder: 'https://…',
		maxlength: 500,
	},
	{
		name: 'slogan',
		value: slogan,
		defaultValue: t('theming', 'a safe home for all your data'),
		type: 'text',
		displayName: t('theming', 'Slogan'),
		placeholder: t('theming', 'Slogan'),
		maxlength: 500,
	},
]

const colorPickerSetting = {
	name: 'color',
	value: color,
	defaultValue: '#0082c9',
	displayName: t('theming', 'Color'),
}

const fileInputSettings = [
	{
		name: 'logo',
		mimeName: 'logoMime',
		mimeValue: logoMime,
		defaultMimeValue: '',
		displayName: t('theming', 'Logo'),
		ariaLabel: t('theming', 'Upload new logo'),
	},
	{
		name: 'background',
		mimeName: 'backgroundMime',
		mimeValue: backgroundMime,
		defaultMimeValue: '',
		displayName: t('theming', 'Login image'),
		ariaLabel: t('theming', 'Upload new login background'),
	},
]

const advancedTextFieldSettings = [
	{
		name: 'imprintUrl',
		value: legalNoticeUrl,
		defaultValue: '',
		type: 'url',
		displayName: t('theming', 'Legal notice link'),
		placeholder: 'https://…',
		maxlength: 500,
	},
	{
		name: 'privacyUrl',
		value: privacyPolicyUrl,
		defaultValue: '',
		type: 'url',
		displayName: t('theming', 'Privacy policy link'),
		placeholder: 'https://…',
		maxlength: 500,
	},
]

const advancedFileInputSettings = [
	{
		name: 'logoheader',
		mimeName: 'logoheaderMime',
		mimeValue: logoheaderMime,
		defaultMimeValue: '',
		displayName: t('theming', 'Header logo'),
		ariaLabel: t('theming', 'Upload new header logo'),
	},
	{
		name: 'favicon',
		mimeName: 'faviconMime',
		mimeValue: faviconMime,
		defaultMimeValue: '',
		displayName: t('theming', 'Favicon'),
		ariaLabel: t('theming', 'Upload new favicon'),
	},
]

export default {
	name: 'AdminTheming',

	components: {
		NcNoteCard,
		NcSettingsSection,
		ColorPickerField,
		FileInputField,
		TextField,
	},

	emits: [
		'update:theming',
	],

	data() {
		return {
			textFieldSettings,
			colorPickerSetting,
			fileInputSettings,
			advancedTextFieldSettings,
			advancedFileInputSettings,

			canThemeIcons,
			docUrl,
			docUrlIcons,
			hasCustomPrimaryColor,
			isThemable,
			notThemableErrorMessage,
		}
	},
}
</script>

<style lang="scss" scoped>
.admin-theming,
.admin-theming-advanced {
	display: flex;
	flex-direction: column;
	gap: 8px 0;
}

.admin-theming {
	&__preview {
		width: 230px;
		height: 140px;
		background-size: cover;
		background-position: center;
		text-align: center;
		margin-top: 10px;
		background-color: var(--color-primary-default);
		background-image: var(--image-background, var(--image-background-plain, url('../../../core/img/app-background.jpg'), linear-gradient(40deg, #0082c9 0%, #30b6ff 100%)));

		&-logo {
			width: 20%;
			height: 20%;
			margin-top: 20px;
			display: inline-block;
			background-size: contain;
			background-position: center;
			background-repeat: no-repeat;
			background-image: var(--image-logo, url('../../../core/img/logo/logo.svg'));
		}
	}
}
</style>
