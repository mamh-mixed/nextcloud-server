/**
 * @copyright Copyright (c) 2023 John Molakvoæ <skjnldsv@protonmail.com>
 *
 * @author John Molakvoæ <skjnldsv@protonmail.com>
 *
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

import '@nextcloud/dialogs/style.css'
import { AxiosError } from 'axios'
import { getFilePickerBuilder, showError, type IFilePickerButton } from '@nextcloud/dialogs'
import { Permission, type Node, type View, registerFileAction, FileAction, FileType } from '@nextcloud/files'
import { translate as t } from '@nextcloud/l10n'
import axios from '@nextcloud/axios'

import CopyIcon from 'vue-material-design-icons/FileMultiple.vue'
import FolderMoveSvg from '@mdi/svg/svg/folder-move.svg?raw'
import MoveIcon from 'vue-material-design-icons/FolderMove.vue'

import { basename } from 'path'
import { generateRemoteUrl } from '@nextcloud/router'
import { getCurrentUser } from '@nextcloud/auth'
import logger from '../logger'

type ShareAttribute = {
	enabled: boolean
	key: string
	scope: string
}

enum MoveCopyAction {
	MOVE = 'Move',
	COPY = 'Copy',
	MOVE_OR_COPY = 'move-or-copy',
}

const canMove = (nodes: Node[]) => {
	const minPermission = nodes.reduce((min, node) => Math.min(min, node.permissions), Permission.ALL)
	return (minPermission & Permission.UPDATE) !== 0
}

const canDownload = (nodes: Node[]) => {
	return nodes.every(node => {
		const shareAttributes = JSON.parse(node.attributes?.['share-attributes'] ?? '[]') as Array<ShareAttribute>
		return shareAttributes.every(attribute => !(attribute.scope === 'permissions' && attribute.enabled === false && attribute.key === 'download'))

	})
}

const canCopy = (nodes: Node[]) => {
	// For now the only restriction is that a shared file
	// cannot be copied if the download is disabled
	return canDownload(nodes)
}

/**
 * Return the action that is possible for the given nodes
 * @param {Node[]} nodes The nodes to check against
 * @return {MoveCopyAction} The action that is possible for the given nodes
 */
const getActionForNodes = (nodes: Node[]): MoveCopyAction => {
	if (canMove(nodes)) {
		if (canDownload(nodes)) {
			return MoveCopyAction.MOVE_OR_COPY
		}
		return MoveCopyAction.MOVE
	}

	// Assuming we can copy as the enabled checks for download permissions
	return MoveCopyAction.COPY
}

export const handleCopyMoveNodeTo = async (node: Node, destination: Node, method: MoveCopyAction.COPY | MoveCopyAction.MOVE) => {
	if (!destination) {
		return
	}

	if (destination.type !== FileType.Folder) {
		throw new Error(t('files', 'Destination is not a folder'))
	}

	if (node.dirname === destination.path) {
		throw new Error(t('files', 'This file/folder is already in that directory'))
	}

	if (node.path === destination.path) {
		throw new Error(t('files', 'You cannot move a file/folder onto itself'))
	}

	const relativePath = `${destination.path}/${node.basename}`.replace(/\/\//, '/')
	const destinationUrl = generateRemoteUrl(`dav/files/${getCurrentUser()?.uid}${relativePath}`)
	logger.debug(`${method} ${node.basename} to ${destinationUrl}`)

	try {
		await axios({
			method: method === MoveCopyAction.COPY ? 'COPY' : 'MOVE',
			url: node.source,
			headers: {
				Destination: destinationUrl,
				Overwrite: 'F', // Do not overwrite
			},
		})
	} catch (error) {
		if (error instanceof AxiosError) {
			if (error?.response?.status === 412) {
				throw new Error(t('files', 'A file or folder with that name already exists in this folder'))
			} else if (error.message) {
				throw new Error(error.message)
			}
		}
		throw new Error()
	}
}

/**
 * Open a file picker for the given action
 * @param {MoveCopyAction} action The action to open the file picker for
 * @param {string} dir The directory to start the file picker in
 * @param {Node} node The node to move/copy
 * @return {Promise<boolean>} A promise that resolves to true if the action was successful
 */
const openFilePickerForAction = async (action: MoveCopyAction, dir = '/', node: Node): Promise<boolean> => {
	const filePicker = getFilePickerBuilder(t('files', 'Chose destination'))
		.allowDirectories(true)
		.setFilter((n: Node) => {
			// We only want to show folders that we can create nodes in
			return (n.permissions & Permission.CREATE) !== 0
				// We don't want to show the current node in the file picker
				&& node.fileid !== n.fileid
		})
		.setMimeTypeFilter([])
		.setMultiSelect(false)
		.startAt(dir)

	return new Promise((resolve, reject) => {
		filePicker.setButtonFactory((nodes: Node[], path: string) => {
			const buttons: IFilePickerButton[] = []
			const target = basename(path)

			if (node.dirname === path) {
				// This file/folder is already in that directory
				return buttons
			}

			if (node.path === path) {
				// You cannot move a file/folder onto itself
				return buttons
			}

			if (action === MoveCopyAction.COPY || action === MoveCopyAction.MOVE_OR_COPY) {
				buttons.push({
					label: target ? t('files', 'Copy to {target}', { target }) : t('files', 'Copy'),
					type: 'primary',
					icon: CopyIcon,
					async callback(destination: Node[]) {
						try {
							await handleCopyMoveNodeTo(node, destination[0], MoveCopyAction.COPY)
							resolve(true)
						} catch (error) {
							reject(error)
						}
					},
				})
			}
			if (action === MoveCopyAction.MOVE || action === MoveCopyAction.MOVE_OR_COPY) {
				buttons.push({
					label: target ? t('files', 'Move to {target}', { target }) : t('files', 'Move'),
					type: action === MoveCopyAction.MOVE ? 'primary' : 'secondary',
					icon: MoveIcon,
					async callback(destination: Node[]) {
						try {
							await handleCopyMoveNodeTo(node, destination[0], MoveCopyAction.MOVE)
							resolve(true)
						} catch (error) {
							reject(error)
						}
					},
				})
			}
			return buttons
		})

		const picker = filePicker.build()
		picker.pick()
	})
}

export const action = new FileAction({
	id: 'move-copy',
	displayName(nodes: Node[]) {
		switch (getActionForNodes(nodes)) {
		case MoveCopyAction.MOVE:
			return t('files', 'Move')
		case MoveCopyAction.COPY:
			return t('files', 'Copy')
		case MoveCopyAction.MOVE_OR_COPY:
			return t('files', 'Move or copy')
		}
	},
	iconSvgInline: () => FolderMoveSvg,
	enabled(nodes: Node[]) {
		// We only support moving/copying files within the user folder
		if (!nodes.every(node => node.root?.startsWith('/files/'))) {
			return false
		}
		return nodes.length > 0 && (canMove(nodes) || canCopy(nodes))
	},

	async exec(node: Node, view: View, dir: string) {
		const action = getActionForNodes([node])
		try {
			await openFilePickerForAction(action, dir, node)
			return true
		} catch (error) {
			if (error instanceof Error && !!error.message) {
				showError(error.message)
				// Silent action as we handle the toast
				return null
			}
			return false
		}
	},

	order: 15,
})

registerFileAction(action)
