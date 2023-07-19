<template>
	<div class="sharingTabDetailsView">
		<div class="sharingTabDetailsView__header">
			<span>
				<FolderIcon :size="40" />
			</span>
			<span>
				<h1>{{ t('file_sharing', 'Edit Share Link') }}</h1>
				<small>Dummy: Important documents</small>
			</span>
		</div>
		<div class="sharingTabDetailsView__quick-permissions">
			<div>
				<NcCheckboxRadioSwitch :button-variant="true"
					:checked.sync="sharingPermission"
					value="r"
					name="sharing_permission_radio"
					type="radio"
					button-variant-grouped="vertical">
					View only (Default)
					<template #icon>
						<ViewIcon :size="20" />
					</template>
				</NcCheckboxRadioSwitch>
				<NcCheckboxRadioSwitch :button-variant="true"
					:checked.sync="sharingPermission"
					value="rw"
					name="sharing_permission_radio"
					type="radio"
					button-variant-grouped="vertical">
					Edit
					<template #icon>
						<EditIcon :size="20" />
					</template>
				</NcCheckboxRadioSwitch>
				<NcCheckboxRadioSwitch :button-variant="true"
					:checked.sync="sharingPermission"
					value="rw+"
					name="sharing_permission_radio"
					type="radio"
					button-variant-grouped="vertical">
					File Drop
					<template #icon>
						<UploadIcon :size="20" />
					</template>
				</NcCheckboxRadioSwitch>
			</div>
		</div>
		<div class="sharingTabDetailsView__advanced-control">
			<NcButton type="tetiary"
				alignment="end-reverse"
				@click="advancedSectionAccordionExpanded = !advancedSectionAccordionExpanded">
				Advanced Settings
				<template #icon>
					<MenuDownIcon />
				</template>
			</NcButton>
		</div>
		<div v-if="advancedSectionAccordionExpanded" class="sharingTabDetailsView__advanced">
			<section>
				<template v-if="canHaveNote">
					<label>{{ t('file_sharing', 'Note to recipient') }}</label>
					<textarea :model="noteToRecipient" />
				</template>
				<NcCheckboxRadioSwitch :checked.sync="setPassword">
					{{ t('file_sharing', 'Set password') }}
				</NcCheckboxRadioSwitch>
				<NcInputField v-if="setPassword" :value="''" :label="t('file_sharing', 'Password')" />

				<NcCheckboxRadioSwitch :checked.sync="hasExpirationDate">
					{{ t('file_sharing', 'Set expiration date') }}
				</NcCheckboxRadioSwitch>
				<NcInputField v-if="hasExpirationDate"
					:value.sync="expirationDate"
					type="date"
					:min="dateTomorrow"
					:max="dateMaxEnforced"
					:label="t('file_sharing', 'Expiration date')"
					@input="onExpirationChange" />
				<NcCheckboxRadioSwitch :checked.sync="hideDownload">
					{{ t('file_sharing', 'Hide download') }}
				</NcCheckboxRadioSwitch>
				<NcInputField :value.sync="shareLabel" :label-visible="true" :label="t('file_sharing', 'Share label')" />
				<span style="display: none;"><label /></span>
			</section>

			<hr>
			<h2>Old stuff to be transferred up</h2>

			<NcActions menu-align="right" class="sharing-entry__actions" @close="onMenuClose">
				<template v-if="share.canEdit">
					<!-- edit permission -->
					<NcActionCheckbox ref="canEdit"
						:checked.sync="canEdit"
						:value="permissionsEdit"
						:disabled="saving || !canSetEdit">
						{{ t('files_sharing', 'Allow editing') }}
					</NcActionCheckbox>

					<!-- create permission -->
					<NcActionCheckbox v-if="isFolder"
						ref="canCreate"
						:checked.sync="canCreate"
						:value="permissionsCreate"
						:disabled="saving || !canSetCreate">
						{{ t('files_sharing', 'Allow creating') }}
					</NcActionCheckbox>

					<!-- delete permission -->
					<NcActionCheckbox v-if="isFolder"
						ref="canDelete"
						:checked.sync="canDelete"
						:value="permissionsDelete"
						:disabled="saving || !canSetDelete">
						{{ t('files_sharing', 'Allow deleting') }}
					</NcActionCheckbox>

					<!-- reshare permission -->
					<NcActionCheckbox v-if="config.isResharingAllowed"
						ref="canReshare"
						:checked.sync="canReshare"
						:value="permissionsShare"
						:disabled="saving || !canSetReshare">
						{{ t('files_sharing', 'Allow resharing') }}
					</NcActionCheckbox>

					<NcActionCheckbox v-if="isSetDownloadButtonVisible"
						ref="canDownload"
						:checked.sync="canDownload"
						:disabled="saving || !canSetDownload">
						{{ allowDownloadText }}
					</NcActionCheckbox>

					<!-- expiration date -->
					<NcActionCheckbox :checked.sync="hasExpirationDate"
						:disabled="config.isDefaultInternalExpireDateEnforced || saving"
						@uncheck="onExpirationDisable">
						{{ config.isDefaultInternalExpireDateEnforced
							? t('files_sharing', 'Expiration date enforced')
							: t('files_sharing', 'Set expiration date') }}
					</NcActionCheckbox>
					<NcActionInput v-if="hasExpirationDate"
						ref="expireDate"
						:is-native-picker="true"
						:hide-label="true"
						:class="{ error: errors.expireDate }"
						:disabled="saving"
						:value="new Date(share.expireDate)"
						type="date"
						:min="dateTomorrow"
						:max="dateMaxEnforced"
						@input="onExpirationChange">
						{{ t('files_sharing', 'Enter a date') }}
					</NcActionInput>

					<!-- note -->
					<template v-if="canHaveNote">
						<NcActionCheckbox :checked.sync="hasNote" :disabled="saving" @uncheck="queueUpdate('note')">
							{{ t('files_sharing', 'Note to recipient') }}
						</NcActionCheckbox>
						<NcActionTextEditable v-if="hasNote"
							ref="note"
							:class="{ error: errors.note }"
							:disabled="saving"
							:value="share.newNote || share.note"
							icon="icon-edit"
							@update:value="onNoteChange"
							@submit="onNoteSubmit" />
					</template>
				</template>

				<NcActionButton v-if="share.canDelete"
					icon="icon-close"
					:disabled="saving"
					@click.prevent="onDelete">
					{{ t('files_sharing', 'Unshare') }}
				</NcActionButton>
			</NcActions>
		</div>

		<div class="sharingTabDetailsView__footer">
			<a href="">
				<CloseIcon />
				Delete link
			</a>
			<div class="button-group">
				<NcButton @click="$emit('close-sharing-details')">
					{{ t('file_sharing', 'Cancel') }}
				</NcButton>
				<NcButton type="primary">
					{{ t('file_sharing', 'Share & copy link') }}
				</NcButton>
			</div>
		</div>
	</div>
</template>

<script>
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcInputField from '@nextcloud/vue/dist/Components/NcInputField.js'
import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'
import FolderIcon from 'vue-material-design-icons/Folder.vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'
import EditIcon from 'vue-material-design-icons/Pencil.vue'
import ViewIcon from 'vue-material-design-icons/Eye.vue'
import UploadIcon from 'vue-material-design-icons/Upload.vue'
import MenuDownIcon from 'vue-material-design-icons/MenuDown.vue'

/// Temp

import NcActions from '@nextcloud/vue/dist/Components/NcActions.js'
import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import NcActionCheckbox from '@nextcloud/vue/dist/Components/NcActionCheckbox.js'
import NcActionInput from '@nextcloud/vue/dist/Components/NcActionInput.js'
import NcActionTextEditable from '@nextcloud/vue/dist/Components/NcActionTextEditable.js'

/// End temp

import SharesMixin from '../mixins/SharesMixin.js'

export default {
	name: 'SharingDetailsTab',
	components: {
		NcButton,
		NcInputField,
		NcCheckboxRadioSwitch,
		FolderIcon,
		CloseIcon,
		EditIcon,
		ViewIcon,
		UploadIcon,
		MenuDownIcon,
		NcActions, // To be removed
		NcActionButton, // To be removed
		NcActionCheckbox, // To be removed
		NcActionInput, // To be removed
		NcActionTextEditable, // To be removed
	},
	mixins: [SharesMixin],
	data() {
		return {
			setPassword: false,
			password: '',
			noteToRecipient: '',
			sharingPermission: 'r',
			expirationDate: new Date(this.share.expireDate),
			hideDownload: false,
			shareLabel: '',
			advancedSectionAccordionExpanded: false,
			permissionsEdit: OC.PERMISSION_UPDATE, /// These variables would probably replace the ones above
			permissionsCreate: OC.PERMISSION_CREATE, /// These variables would probably replace the ones above
			permissionsDelete: OC.PERMISSION_DELETE, /// These variables would probably replace the ones above
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
	mounted() {
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
	},
}
</script>

<style lang="scss" scoped>
.sharingTabDetailsView {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    width: 96%;
    margin: 0 auto;

    &__header {
        display: flex;
        align-items: center;
        justify-content: left;
        width: 100%;
        margin-bottom: 0.2em;
        box-sizing: border-box;

        span:nth-child(1) {
            align-items: center;
            justify-content: center;
            color: var(--color-primary-element);
            padding: 0.1em;
        }

        span:nth-child(2) {
            display: flex;
            flex-direction: column;
            justify-content: left;
            align-items: flex-start;
            text-align: left;

            h1 {
                margin: 0;
                font-size: 16px;
            }

            small {
                font-size: 12px;
                margin-top: 0;
                color: rgb(65, 62, 62);
            }
        }
    }

    &__quick-permissions {
        display: flex;
        justify-content: center;
        margin-bottom: 0.2em;
        width: 100%;
        margin: 0 auto;

        div {
            width: 100%;

            span {
                width: 100%;

                span:nth-child(1) {
                    align-items: center;
                    justify-content: center;
                    color: var(--color-primary-element);
                    padding: 0.1em;
                }
            }
        }
    }

    &__advanced-control {
        width: 100%;

        button {
            margin-top: 0.5em;
        }

    }

    &__advanced {
        width: 100%;
        margin-bottom: 0.5em;
        text-align: left;
        padding-left: 0;

        section {

            textarea {
                width: 100%;
            }

            /*
              The following style is applied out of the component's scope
              to remove padding from the label.checkbox-radio-switch__label,
              which is used to group radio checkbox items. The use of ::v-deep
              ensures that the padding is modified without being affected by
              the component's scoping.
              Without this achieving left alignment for the checkboxes would not
              be possible.
            */
            span {
                ::v-deep label {
                    padding-left: 0 !important;
                    background-color: initial !important;
                    border: none !important;
                }
            }
        }
    }

    &__footer {
        width: 100%;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        align-items: flex-start;

        a {
            display: flex;
            align-items: center;
            color: rgb(223, 7, 7);
            text-decoration: underline;
            font-size: 16px;
        }

        .button-group {
            display: flex;
            justify-content: space-between;
            width: 100%;
            margin-top: 16px;

            button {
                flex: 1;
                margin-left: 16px;

                &:first-child {
                    margin-left: 0;
                }
            }
        }
    }
}
</style>
