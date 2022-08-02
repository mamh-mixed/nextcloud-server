<!--
  - @copyright 2022 Carl Schwan <carl@carlschwan.eu>
  -
  - @license GNU AGPL version 3 or any later version
  -
  - This program is free software: you can redistribute it and/or modify
  - it under the terms of the GNU Affero General Public License as
  - published by the Free Software Foundation, either version 3 of the
  - License, or (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program.  If not, see <http://www.gnu.org/licenses/>.
  -->
<template>
	<SettingsSection :title="t('settings', 'Password')">
		<form id="passwordform" method="POST">
			<div class="control-group">
				<label for="old-pass">{{ t('settings', 'Current password') }}</label>
				<PasswordField
					id="old-pass"
					name="oldpassword"
					:value="oldPass"
					autocomplete="current-password"
					autocapitalize="none"
					autocorrect="off" />
			</div>

			<div class="control-group">
				<label for="new-pass">{{ t('settings', 'New password') }}</label>
				<PasswordField
					id="new-pass"
					:value="newPass"
					maxlength="469"
					autocomplete="new-password"
					autocapitalize="none"
					autocorrect="off"
					:label-outside="true" />
			</div>

			<Button type="primary" native-type="submit">
				{{ t('settings', 'Change password') }}
			</Button>
		</form>
	</SettingsSection>
</template>

<script>
import SettingsSection from '@nextcloud/vue/dist/Components/SettingsSection'
import Button from '@nextcloud/vue/dist/Components/Button'
import PasswordField from './PasswordField'

export default {
	name: 'PasswordSection',
	components: {
		SettingsSection,
		Button,
		PasswordField,
	},
	data() {
		return {
			oldPass: '',
			newPass: '',
		}
	},
}
</script>

<style>
	.control-group {
		margin-bottom: 16px;
		max-width: 400px;
	}
</style>
