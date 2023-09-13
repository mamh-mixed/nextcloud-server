<template>
	<div class="files-list-drag-image">
		<span class="files-list-drag-image__icon">
			<span ref="previewImg" />
			<FolderIcon v-if="isSingleFolder" />
			<FileMultipleIcon v-else />
		</span>
		<span class="files-list-drag-image__name">
			{{ name }}
		</span>
	</div>
</template>

<script lang="ts">
import { FileType, Node, formatFileSize } from '@nextcloud/files'
import Vue from 'vue'

import FileMultipleIcon from 'vue-material-design-icons/FileMultiple.vue'
import FolderIcon from 'vue-material-design-icons/Folder.vue'

import { getSummaryFor } from '../utils/fileUtils.ts'

export default Vue.extend({
	name: 'DragAndDropPreview',
	components: {
		FileMultipleIcon,
		FolderIcon,
	},

	data() {
		return {
			nodes: [],
		}
	},

	computed: {
		isSingleNode() {
			return this.nodes.length === 1
		},
		isSingleFolder() {
			return this.isSingleNode
				&& this.nodes[0].type === FileType.Folder
		},

		name() {
			if (!this.size) {
				return this.summary
			}
			return `${this.summary} – ${this.size}`
		},
		size() {
			const totalSize = this.nodes.reduce((total, node) => total + node.size || 0, 0)
			const size = parseInt(totalSize, 10) || 0
			if (typeof size !== 'number' || size < 0) {
				return null
			}
			return formatFileSize(size, true)
		},
		summary() {
			if (this.isSingleNode) {
				const node = this.nodes[0] as Node
				return node.attributes.displayName || node.basename
			}
			return getSummaryFor(this.nodes)
		},
	},

	methods: {
		update(nodes: Node[]) {
			this.nodes = nodes
			this.$refs.previewImg.replaceChildren()

			// Clone icon node from the list
			nodes.slice(0, 3).forEach(function(node) {
				const preview = document.querySelector(`[data-cy-files-list-row-fileid="${node.fileid}"] .files-list__row-icon img`)
				if (preview) {
					const previewElmt = this.$refs.previewImg
					previewElmt.appendChild(preview.parentNode.cloneNode(true))
				}
			})
			// this.$nextTick(() => {
			this.$emit('loaded', this.$el)
			console.debug('Drag and drop preview rendered', this)
			// })
		},
	},
})
</script>

<style lang="scss">
.files-list-drag-image {
	position: absolute;
	top: -9999px;
	left: -9999px;
	display: flex;
	overflow: hidden;
	align-items: center;
	height: 44px;
	padding: 6px 12px;
	background: var(--color-main-background);

	&__icon,
	.files-list__row-icon {
		display: flex;
		overflow: hidden;
		align-items: center;
		justify-content: center;
		width: 32px;
		height: 32px;
		border-radius: var(--border-radius);
	}

	&__icon {
		overflow: visible;
		margin-right: 12px;

		img {
			max-width: 100%;
			max-height: 100%;
		}

		&.material-design-icon {
			color: var(--color-text-maxcontrast);
			.folder-icon {
				color: var(--color-primary-element);
			}
		}

		& > span {
			display: flex;
			.files-list__row-icon + .files-list__row-icon {
				margin-top: 6px;
				margin-left: -26px;
				& + .files-list__row-icon {
					margin-top: 12px;
				}
			}
			&:not(:empty) + * {
				display: none;
			}
		}
	}
	&__name {
		overflow: hidden;
		white-space: nowrap;
		text-overflow: ellipsis;
	}
}
</style>
