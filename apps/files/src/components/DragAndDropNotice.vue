<!--
	- @copyright Copyright (c) 2023 John Molakvoæ <skjnldsv@protonmail.com>
	-
	- @author John Molakvoæ <skjnldsv@protonmail.com>
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
	<div v-if="isVisible" class="files-list-drag-drop-notice" :class="{'files-list-drag-drop-notice--dragover': dragover}">
		<TrayArrowDownIcon :size="48" />
		<h3 class="files-list-drag-drop-notice__title">{{ t('files', 'Drag and drop files here to upload') }}</h3>

		<!-- Close button -->
		<NcButton class="files-list-drag-drop-notice__close"
			:aria-label="t('files', 'Hide the drop zone')"
			:title="t('files', 'Hide the drop zone')"
			type="tertiary"
			@click="onClose">
			<template #icon>
				<CloseIcon />
			</template>
		</NcButton>
	</div>
</template>

<script lang="ts">
import type { UserConfig } from '../types.ts'

import { translate as t } from '@nextcloud/l10n'
import Vue from 'vue'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import TrayArrowDownIcon from 'vue-material-design-icons/TrayArrowDown.vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'

import { useUserConfigStore } from '../store/userconfig.ts'

export default Vue.extend({
	name: 'DragAndDropNotice',

	components: {
		CloseIcon,
		NcButton,
		TrayArrowDownIcon,
	},

	props: {
		dragover: {
			type: Boolean,
			default: false,
		},
	},

	setup() {
		const userConfigStore = useUserConfigStore()
		return {
			userConfigStore,
		}
	},

	computed: {
		userConfig(): UserConfig {
			return this.userConfigStore.userConfig
		},
		isVisible() {
			return this.userConfig.show_dropzone
		},
	},

	methods: {
		onClose() {
			this.userConfigStore.update('show_dropzone', false)
		},

		t,
	},
})
</script>

<style lang="scss" scoped>
.files-list-drag-drop-notice {
	display: flex;
	align-items: center;
	justify-content: center;
	width: 80vw;
	max-width: 400px;
	min-height: 100px;
	margin: 5vh auto;
	user-select: none;
	white-space: pre-wrap;
	color: var(--color-text-maxcontrast);
	border: 2px dashed transparent;
	border-radius: var(--border-radius-pill);

	&--dragover {
		border-color: black;
	}

	h3 {
		margin-left: 16px;
		color: inherit;
	}

	&__close {
		position: absolute !important;
		top: 10px;
		right: 10px;
	}
}

</style>
