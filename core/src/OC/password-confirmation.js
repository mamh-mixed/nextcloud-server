/**
 * @copyright 2019 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author John Molakvoæ <skjnldsv@protonmail.com>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

import _ from 'underscore'
import $ from 'jquery'
import moment from 'moment'
import { generateUrl } from '@nextcloud/router'
import { confirmPassword } from '@nextcloud/password-confirmation'

import OC from './index.js'

/**
 * @namespace OC.PasswordConfirmation
 */
export default {
	callback: null,

	pageLoadTime: null,

	init() {
		$('.password-confirm-required').on('click', _.bind(this.requirePasswordConfirmation, this))
		this.pageLoadTime = moment.now()
	},

	requiresPasswordConfirmation() {
		const serverTimeDiff = this.pageLoadTime - (window.nc_pageLoad * 1000)
		const timeSinceLogin = moment.now() - (serverTimeDiff + (window.nc_lastLogin * 1000))

		// if timeSinceLogin > 30 minutes and user backend allows password confirmation
		return (window.backendAllowsPasswordConfirmation && timeSinceLogin > 30 * 60 * 1000)
	},

	/**
	 * @param {Function} callback success callback function
	 * @param {object} options options currently not used by confirmPassword
	 * @param {Function} rejectCallback error callback function
	 */
	requirePasswordConfirmation(callback, options, rejectCallback) {
		confirmPassword().then(callback, rejectCallback)
	},

	_confirmPassword(password, config) {
		const self = this

		$.ajax({
			url: generateUrl('/login/confirm'),
			data: {
				password,
			},
			type: 'POST',
			success(response) {
				window.nc_lastLogin = response.lastLogin

				if (_.isFunction(self.callback)) {
					self.callback()
				}
			},
			error() {
				config.error = t('core', 'Failed to authenticate, please fill out your password')
				OC.PasswordConfirmation.requirePasswordConfirmation(self.callback, config)
			},
		})
	},
}
