<!--
  - @copyright 2023 Christopher Ng <chrng8@gmail.com>
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
	<NcHeaderMenu id="contactsmenu"
		:aria-label="t('core', 'Search contacts')"
		@open="handleOpen">
		<template #trigger>
			<Contacts :size="20" />
		</template>
		<div id="contactsmenu-menu">
			<label for="contactsmenu-search">{{ t('core', 'Search contacts') }}</label>
			<input id="contactsmenu-search"
				v-model="searchTerm"
				type="search"
				:placeholder="t('core', 'Search contacts …')">
			<div v-if="error">
				<div class="icon-search" />
				<h2>{{ t('core', 'Could not load your contacts') }}</h2>
			</div>
			<div v-else-if="loadingText" class="emptycontent">
				<div class="icon-loading" />
				<h2>{{ loadingText }}</h2>
			</div>
			<div v-else-if="contacts.length === 0" class="emptycontent">
				<div class="icon-search" />
				<h2>{{ t('core', 'No contacts found') }}</h2>
			</div>
			<div v-else class="content">
				<div id="contactsmenu-contacts">
					<ul>
						<Contact v-for="contact in contacts" :key="contact.id" :contact="contact" />
					</ul>
				</div>
				<div v-if="contactsAppEnabled" class="footer">
					<a :href="contactsAppURL">{{ t('core', 'Show all contacts …') }}</a>
				</div>
				<div v-else-if="canInstallApp" class="footer">
					<a :href="contactsAppMgmtURL">{{ t('core', 'Install the Contacts app') }}</a>
				</div>
			</div>
		</div>
	</NcHeaderMenu>
</template>

<script>
import axios from '@nextcloud/axios'
import Contacts from 'vue-material-design-icons/Contacts.vue'
import { getCurrentUser } from '@nextcloud/auth'
import { generateUrl } from '@nextcloud/router'
import NcHeaderMenu from '@nextcloud/vue/dist/Components/NcHeaderMenu.js'
import { translate as t } from '@nextcloud/l10n'

import Contact from '../components/ContactsMenu/Contact.vue'
import logger from '../logger.js'

export default {
	name: 'ContactsMenu',

	components: {
		Contact,
		Contacts,
		NcHeaderMenu,
	},

	data() {
		const user = getCurrentUser()
		return {
			contactsAppEnabled: true,
			contactsAppURL: generateUrl('/apps/contacts'),
			contactsAppMgmtURL: generateUrl('/settings/apps/social/contacts'),
			canInstallApp: user.isAdmin,
			contacts: [],
			loadingText: false,
			error: false,
			searchTerm: '',
		}
	},

	methods: {
		async handleOpen() {
			await this.getContacts('')
		},
		async getContacts(searchTerm) {
			this.loadingText = t('core', 'Loading your contacts …')
			try {
				const { data: { contacts } } = await axios.post(generateUrl('/contactsmenu/contacts'), {
					filter: searchTerm,
				})
				this.contacts = contacts
				this.loadingText = false
			} catch (error) {
				logger.error('could not load contacts', {
					error,
					searchTerm,
				})
				this.error = true
			}
		},
	},
}
</script>

<style lang="scss" scoped>
#contactsmenu-menu {
	/* show 2.5 to 4.5 entries depending on the screen height */
	height: calc(100vh - 50px * 3);
	max-height: calc(50px * 6 + 2px + 26px);
	min-height: calc(50px * 3.5);
	width: 350px;

	&:deep {
		.emptycontent {
			margin-top: 5vh !important;
			margin-bottom: 1.5vh;

			.icon-loading,
			.icon-search {
				display: inline-block;
			}
		}

		label[for="contactsmenu-search"] {
			font-weight: bold;
			font-size: 19px;
			margin-left: 13px;
		}

		#contactsmenu-search {
			width: 100%;
			height: 34px;
			margin: 8px 0;
		}

		.content {
			/* fixed max height of the parent container without the search input */
			height: calc(100vh - 50px * 3 - 50px);
			max-height: calc(50px * 5);
			min-height: calc(50px * 3.5 - 50px);
			overflow-y: auto;

			.footer {
				text-align: center;

				a {
					display: block;
					width: 100%;
					padding: 12px 0;
					opacity: .5;
				}
			}
		}

		a {
			padding: 2px;

			&:focus-visible {
				box-shadow: inset 0 0 0 2px var(--color-main-text) !important; // override rule in core/css/headers.scss #header a:focus-visible
			}
		}
	}
}
</style>
