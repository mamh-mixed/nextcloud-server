<!--
  - @copyright Copyright (c) 2019 John Molakvoæ <skjnldsv@protonmail.com>
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
	<li class="sharing-entry">
		<NcAvatar class="sharing-entry__avatar"
			:is-no-user="share.type !== SHARE_TYPES.SHARE_TYPE_USER"
			:user="share.shareWith"
			:display-name="share.shareWithDisplayName"
			:menu-position="'left'"
			:url="share.shareWithAvatar" />

		<div class="sharing-entry__summary" @click="openQuickShareSelect">
			<component :is="share.shareWithLink ? 'a' : 'div'"
				:title="tooltip"
				:aria-label="tooltip"
				:href="share.shareWithLink"
				class="sharing-entry__desc">
				<span>{{ title }}<span v-if="!isUnique" class="sharing-entry__desc-unique"> ({{ share.shareWithDisplayNameUnique }})</span></span>
				<p v-if="hasStatus">
					<span>{{ share.status.icon || '' }}</span>
					<span>{{ share.status.message || '' }}</span>
				</p>
			</component>
			<select class="share-select" ref="shareSelect">
				<option>Can edit</option>
			</select>
		</div>
		<NcButton class="sharing-entry__action" 
		:aria-label="t('files_sharing', 'Open Sharing Details')" 
		type="tertiary-no-background"
		@click="openSharingDetails">
				<template #icon>
					<DotsHorizontalIcon :size="20" />
				</template>
		</NcButton>
	</li>
</template>

<script>
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcSelect from '@nextcloud/vue/dist/Components/NcSelect.js'
import NcAvatar from '@nextcloud/vue/dist/Components/NcAvatar.js'
import DotsHorizontalIcon from 'vue-material-design-icons/DotsHorizontal.vue'


import SharesMixin from '../mixins/SharesMixin.js'

export default {
	name: 'SharingEntry',

	components: {
		NcButton,
		NcAvatar,
		DotsHorizontalIcon,
	},

	mixins: [SharesMixin],

	data() {
		return {
			permissionsEdit: OC.PERMISSION_UPDATE,
			permissionsCreate: OC.PERMISSION_CREATE,
			permissionsDelete: OC.PERMISSION_DELETE,
			permissionsRead: OC.PERMISSION_READ,
			permissionsShare: OC.PERMISSION_SHARE,
		}
	},

	computed: {
		title() {
			let title = this.share.shareWithDisplayName
			if (this.share.type === this.SHARE_TYPES.SHARE_TYPE_GROUP) {
				title += ` (${t('files_sharing', 'group')})`
			} else if (this.share.type === this.SHARE_TYPES.SHARE_TYPE_ROOM) {
				title += ` (${t('files_sharing', 'conversation')})`
			} else if (this.share.type === this.SHARE_TYPES.SHARE_TYPE_REMOTE) {
				title += ` (${t('files_sharing', 'remote')})`
			} else if (this.share.type === this.SHARE_TYPES.SHARE_TYPE_REMOTE_GROUP) {
				title += ` (${t('files_sharing', 'remote group')})`
			} else if (this.share.type === this.SHARE_TYPES.SHARE_TYPE_GUEST) {
				title += ` (${t('files_sharing', 'guest')})`
			}
			return title
		},

		tooltip() {
			if (this.share.owner !== this.share.uidFileOwner) {
				const data = {
					// todo: strong or italic?
					// but the t function escape any html from the data :/
					user: this.share.shareWithDisplayName,
					owner: this.share.ownerDisplayName,
				}
				if (this.share.type === this.SHARE_TYPES.SHARE_TYPE_GROUP) {
					return t('files_sharing', 'Shared with the group {user} by {owner}', data)
				} else if (this.share.type === this.SHARE_TYPES.SHARE_TYPE_ROOM) {
					return t('files_sharing', 'Shared with the conversation {user} by {owner}', data)
				}

				return t('files_sharing', 'Shared with {user} by {owner}', data)
			}
			return null
		},

		canHaveNote() {
			return !this.isRemote
		},

		isRemote() {
			return this.share.type === this.SHARE_TYPES.SHARE_TYPE_REMOTE
				|| this.share.type === this.SHARE_TYPES.SHARE_TYPE_REMOTE_GROUP
		},

		/**
		 * Can the sharer set whether the sharee can edit the file ?
		 *
		 * @return {boolean}
		 */
		canSetEdit() {
			// If the owner revoked the permission after the resharer granted it
			// the share still has the permission, and the resharer is still
			// allowed to revoke it too (but not to grant it again).
			return (this.fileInfo.sharePermissions & OC.PERMISSION_UPDATE) || this.canEdit
		},

		/**
		 * Can the sharer set whether the sharee can create the file ?
		 *
		 * @return {boolean}
		 */
		canSetCreate() {
			// If the owner revoked the permission after the resharer granted it
			// the share still has the permission, and the resharer is still
			// allowed to revoke it too (but not to grant it again).
			return (this.fileInfo.sharePermissions & OC.PERMISSION_CREATE) || this.canCreate
		},

		/**
		 * Can the sharer set whether the sharee can delete the file ?
		 *
		 * @return {boolean}
		 */
		canSetDelete() {
			// If the owner revoked the permission after the resharer granted it
			// the share still has the permission, and the resharer is still
			// allowed to revoke it too (but not to grant it again).
			return (this.fileInfo.sharePermissions & OC.PERMISSION_DELETE) || this.canDelete
		},

		/**
		 * Can the sharer set whether the sharee can reshare the file ?
		 *
		 * @return {boolean}
		 */
		canSetReshare() {
			// If the owner revoked the permission after the resharer granted it
			// the share still has the permission, and the resharer is still
			// allowed to revoke it too (but not to grant it again).
			return (this.fileInfo.sharePermissions & OC.PERMISSION_SHARE) || this.canReshare
		},

		/**
		 * Can the sharer set whether the sharee can download the file ?
		 *
		 * @return {boolean}
		 */
		canSetDownload() {
			// If the owner revoked the permission after the resharer granted it
			// the share still has the permission, and the resharer is still
			// allowed to revoke it too (but not to grant it again).
			return (this.fileInfo.canDownload() || this.canDownload)
		},

		/**
		 * Can the sharee edit the shared file ?
		 */
		canEdit: {
			get() {
				return this.share.hasUpdatePermission
			},
			set(checked) {
				this.updatePermissions({ isEditChecked: checked })
			},
		},

		/**
		 * Can the sharee create the shared file ?
		 */
		canCreate: {
			get() {
				return this.share.hasCreatePermission
			},
			set(checked) {
				this.updatePermissions({ isCreateChecked: checked })
			},
		},

		/**
		 * Can the sharee delete the shared file ?
		 */
		canDelete: {
			get() {
				return this.share.hasDeletePermission
			},
			set(checked) {
				this.updatePermissions({ isDeleteChecked: checked })
			},
		},

		/**
		 * Can the sharee reshare the file ?
		 */
		canReshare: {
			get() {
				return this.share.hasSharePermission
			},
			set(checked) {
				this.updatePermissions({ isReshareChecked: checked })
			},
		},

		/**
		 * Can the sharee download files or only view them ?
		 */
		canDownload: {
			get() {
				return this.share.hasDownloadPermission
			},
			set(checked) {
				this.updatePermissions({ isDownloadChecked: checked })
			},
		},

		/**
		 * Is this share readable
		 * Needed for some federated shares that might have been added from file drop links
		 */
		hasRead: {
			get() {
				return this.share.hasReadPermission
			},
		},

		/**
		 * Is the current share a folder ?
		 *
		 * @return {boolean}
		 */
		isFolder() {
			return this.fileInfo.type === 'dir'
		},

		/**
		 * Does the current share have an expiration date
		 *
		 * @return {boolean}
		 */
		hasExpirationDate: {
			get() {
				return this.config.isDefaultInternalExpireDateEnforced || !!this.share.expireDate
			},
			set(enabled) {
				const defaultExpirationDate = this.config.defaultInternalExpirationDate
					|| new Date(new Date().setDate(new Date().getDate() + 1))
				this.share.expireDate = enabled
					? this.formatDateToString(defaultExpirationDate)
					: ''
				console.debug('Expiration date status', enabled, this.share.expireDate)
			},
		},

		dateMaxEnforced() {
			if (!this.isRemote && this.config.isDefaultInternalExpireDateEnforced) {
				return new Date(new Date().setDate(new Date().getDate() + 1 + this.config.defaultInternalExpireDate))
			} else if (this.config.isDefaultRemoteExpireDateEnforced) {
				return new Date(new Date().setDate(new Date().getDate() + 1 + this.config.defaultRemoteExpireDate))
			}
			return null
		},

		/**
		 * @return {boolean}
		 */
		hasStatus() {
			if (this.share.type !== this.SHARE_TYPES.SHARE_TYPE_USER) {
				return false
			}

			return (typeof this.share.status === 'object' && !Array.isArray(this.share.status))
		},

		/**
		 * @return {string}
		 */
		allowDownloadText() {
			return t('files_sharing', 'Allow download')
		},

		/**
		 * @return {boolean}
		 */
		isSetDownloadButtonVisible() {
			// TODO: Implement download permission for circle shares instead of hiding the option.
			//       https://github.com/nextcloud/server/issues/39161
			if (this.share && this.share.type === this.SHARE_TYPES.SHARE_TYPE_CIRCLE) {
				return false
			}

			const allowedMimetypes = [
				// Office documents
				'application/msword',
				'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
				'application/vnd.ms-powerpoint',
				'application/vnd.openxmlformats-officedocument.presentationml.presentation',
				'application/vnd.ms-excel',
				'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
				'application/vnd.oasis.opendocument.text',
				'application/vnd.oasis.opendocument.spreadsheet',
				'application/vnd.oasis.opendocument.presentation',
			]

			return this.isFolder || allowedMimetypes.includes(this.fileInfo.mimetype)
		},
	},

	methods: {
		updatePermissions({
			isEditChecked = this.canEdit,
			isCreateChecked = this.canCreate,
			isDeleteChecked = this.canDelete,
			isReshareChecked = this.canReshare,
			isDownloadChecked = this.canDownload,
		} = {}) {
			// calc permissions if checked
			const permissions = 0
				| (this.hasRead ? this.permissionsRead : 0)
				| (isCreateChecked ? this.permissionsCreate : 0)
				| (isDeleteChecked ? this.permissionsDelete : 0)
				| (isEditChecked ? this.permissionsEdit : 0)
				| (isReshareChecked ? this.permissionsShare : 0)

			this.share.permissions = permissions
			if (this.share.hasDownloadPermission !== isDownloadChecked) {
				this.share.hasDownloadPermission = isDownloadChecked
			}
			this.queueUpdate('permissions', 'attributes')
		},

		/**
		 * Save potential changed data on menu close
		 */
		onMenuClose() {
			this.onNoteSubmit()
		},
		openSharingDetails(event) {
			// Do not open sharing details if user is click on quick permissions dropdown
			if (event.target.tagName === 'SELECT' && event.target.classList.contains('share-select')) {
				return
			}
			this.$emit('open-sharing-details')
		},
		openQuickShareSelect() {
            this.$refs.shareSelect.click();
		},
	},
}
</script>

<style lang="scss" scoped>
.sharing-entry {
	display: flex;
	align-items: center;
	height: 44px;
	&__desc {
		display: flex;
		flex-direction: column;
		justify-content: space-between;
		// border: 1px solid red;
		padding: 8px;
		padding-bottom: 0;
		line-height: 1.2em;
		p {
			color: var(--color-text-maxcontrast);
		}
		&-unique {
			color: var(--color-text-maxcontrast);
		}
	}
	&__actions {
		margin-left: auto;
	}

	&__summary {
		padding: 0 !important;
		display: flex;
        flex-direction: column;
		width: 100%;
		.share-select {
			appearance: none;
	        -webkit-appearance: none;
            -moz-appearance: none;
			border: none;
			margin: 0;
			outline: none;
			font-size: 12px;
			height: auto;
			width: max-content;
			min-height: initial;
			font-weight: bold;
			cursor: pointer;
			color: var(--color-primary-element);
		}
	}

}

</style>
