<!--
  - @copyright Copyright (c) 2018 Roeland Jago Douma <roeland@famdouma.nl>
  -
  - @author Roeland Jago Douma <roeland@famdouma.nl>
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
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
  -
  -->
<template>
	<NcSettingsSection :name="t('oauth2', 'OAuth 2.0 clients')"
		:description="t('oauth2', 'OAuth 2.0 allows external services to request access to {instanceName}.', { instanceName })"
		:doc-url="oauthDocLink">
		<table v-if="clients.length > 0" class="grid">
			<thead>
				<tr>
					<th id="headerContent" />
					<th id="headerRemove">
&nbsp;
					</th>
				</tr>
			</thead>
			<tbody>
				<OAuthItem v-for="client in clients"
					:key="client.id"
					:client="client"
					@delete="deleteClient" />
			</tbody>
		</table>

		<br>
		<h3>{{ t('oauth2', 'Add client') }}</h3>
		<span v-if="newClient.error" class="msg error">{{ newClient.errorMsg }}</span>
		<form class="oauth2-form" @submit.prevent="addClient">
			<NcTextField id="name"
				:value.sync="newClient.name"
				type="text"
				class="oauth2-form--input"
				name="name"
				:label="t('oauth2', 'Name')"
				:placeholder="t('oauth2', 'Name')" />
			<NcTextField id="redirectUri"
				:value.sync="newClient.redirectUri"
				type="url"
				class="oauth2-form--input"
				name="redirectUri"
				:label="t('oauth2', 'Redirection URI')"
				:placeholder="t('oauth2', 'Redirection URI')" />
			<NcButton native-type="submit" class="inline-button">
				{{ t('oauth2', 'Add') }}
			</NcButton>
		</form>
	</NcSettingsSection>
</template>

<script>
import axios from '@nextcloud/axios'
import OAuthItem from './components/OAuthItem.vue'
import { generateUrl } from '@nextcloud/router'
import { getCapabilities } from '@nextcloud/capabilities'
import NcSettingsSection from '@nextcloud/vue/dist/Components/NcSettingsSection.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import { loadState } from '@nextcloud/initial-state'
import NcTextField from '@nextcloud/vue/dist/Components/NcTextField.js'

export default {
	name: 'App',
	components: {
		OAuthItem,
		NcSettingsSection,
		NcButton,
		NcTextField,
	},
	props: {
		clients: {
			type: Array,
			required: true,
		},
	},
	data() {
		return {
			newClient: {
				name: '',
				redirectUri: '',
				errorMsg: '',
				error: false,
			},
			oauthDocLink: loadState('oauth2', 'oauth2-doc-link'),
		}
	},
	computed: {
		instanceName() {
			return getCapabilities().theming.name
		},
	},
	methods: {
		deleteClient(id) {
			axios.delete(generateUrl('apps/oauth2/clients/{id}', { id }))
				.then((response) => {
					// eslint-disable-next-line vue/no-mutating-props
					this.clients = this.clients.filter(client => client.id !== id)
				})
		},
		addClient() {
			this.newClient.error = false

			axios.post(
				generateUrl('apps/oauth2/clients'),
				{
					name: this.newClient.name,
					redirectUri: this.newClient.redirectUri,
				}
			).then(response => {
				// eslint-disable-next-line vue/no-mutating-props
				this.clients.push(response.data)

				this.newClient.name = ''
				this.newClient.redirectUri = ''
			}).catch(reason => {
				this.newClient.error = true
				this.newClient.errorMsg = reason.response.data.message
			})
		},
	},
}
</script>
<style scoped>
	table {
		max-width: 800px;
	}

	/** Overwrite button height and position to be aligned with the text input */
	.inline-button {
		min-height: 34px !important;
		display: inline-flex !important;
	}
	.oauth2-form {
		display: flex;
		flex-direction: row;
	}
	.oauth2-form--input {
		max-width: 200px;
		margin-right: 10px;
	}
</style>
