<template>
	<div class="text-field">
		<label v-if="!labelOutside && label !== undefined"
			class="text-field__label"
			:class="{ 'text-field__label--hidden': !labelVisible }"
			:for="inputName">
			{{ label }}
		</label>
		<div class="text-field__main-wrapper">
			<input v-bind="$attrs"
				ref="input"
				:name="inputName"
				class="text-field__input"
				:type="type"
				:placeholder="computedPlaceholder"
				aria-live="polite"
				:class="{
					'text-field__input--leading-icon': hasLeadingIcon,
					'text-field__input--trailing-icon': hasTrailingIcon,
				}"
				:value="value"
				v-on="$listeners"
				@input="handleInput">

			<!-- Leading icon -->
			<div class="text-field__icon text-field__icon--leading">
				<!-- Leading material design icon in the text field, set the size to 18 -->
				<slot />
			</div>

			<!-- clear text button -->
			<Button type="tertiary-no-background"
				class="text-field__show-button"
				@click="togglePasswordVisibility">
				<template #icon>
					<Eye v-if="isPasswordHidden" :size="20" />
					<EyeOff v-else :size="18" />
				</template>
			</Button>
		</div>
	</div>
</template>

<script>
import Button from '@nextcloud/vue/dist/Components/Button'
import Close from 'vue-material-design-icons/Close'
import Check from 'vue-material-design-icons/Check'
import Eye from 'vue-material-design-icons/Eye'
import EyeOff from 'vue-material-design-icons/EyeOff'

export default {
	name: 'PasswordField',
	components: {
		Button,
		Close,
		Eye,
		EyeOff,
	},
	data() {
		return {
			isPasswordHidden: true,
		}
	},
	props: {
		id: {
			type: String,
			required: true,
		},
		/**
		 * The value of the input field
		 */
		value: {
			type: String,
			required: true,
		},
		/**
		 * The hidden input label for accessibility purposes. This will also
		 * be used as a placeholder unless the placeholder prop is populated
		 * with a different string.
		 */
		label: {
			type: String,
			default: undefined,
		},
		/**
		 * Pass in true if you want to use an external label. This is useful
		 * if you need a label that looks different from the one provided by
		 * this component
		 */
		labelOutside: {
			type: Boolean,
			default: false,
		},
		/**
		 * We normally have the lable hidden visually and use it for
		 * accessibility only. If you want to have the label visible just above
		 * the input field pass in true to this prop.
		 */
		labelVisible: {
			type: Boolean,
			default: false,
		},
		/**
		 * The placeholder of the input. This defaults as the string that's
		 * passed into the label prop. In order to remove the placeholder,
		 * pass in an empty string.
		 */
		placeholder: {
			type: String,
			default: undefined,
		},
		/**
		 * Controls whether to display the clear button or not. Since the
		 * parent component will have to store the value of this text input,
		 * once clear button is pressed, there's no change in the value of
		 * the text input. Instead, a 'clear' event is sent to the parent
		 * component.
		 */
		canClear: {
			type: Boolean,
			default: false,
		},
		/**
		 * Toggles the success state of the component. Adds a checkmark icon.
		 * this cannot be used together with canClear.
		 */
		success: {
			type: Boolean,
			default: false,
		},
	},
	computed: {
		type() {
			return this.isPasswordHidden ? 'password' : 'text'
		},
		inputName() {
			return 'input' + this.id
		},
		hasLeadingIcon() {
			return this.$slots.default
		},
		hasTrailingIcon() {
			return this.success
		},
		hasPlaceholder() {
			return this.placeholder !== '' && this.placeholder !== undefined
		},
		computedPlaceholder() {
			if (this.labelVisible) {
				return this.hasPlaceholder ? this.placeholder : ''
			} else {
				return this.hasPlaceholder ? this.placeholder : this.label
			}
		},
	},
	watch: {
		/**
		 * Don't allow both trailing checkmark and clear button to be present
		 * at the same time
		 */
		success() {
			this.validateTrailingIcons()
		},
		canClear() {
			this.validateTrailingIcons()
		},
		label() {
			this.validateLabel()
		},
		labelOutside() {
			this.validateLabel()
		},
	},
	methods: {
		togglePasswordVisibility() {
			return this.isPasswordHidden = !this.isPasswordHidden
		},
		handleInput(event) {
			this.$emit('update:value', event.target.value)
		},
		validateTrailingIcons() {
			if (this.canClear && this.success) {
				throw new Error('success and canClear props cannot be true at the same time')
			}
		},
		validateLabel() {
			if (this.label && !this.labelOutside) {
				throw new Error('You need to add a label to the textField component. Either use the prop label or use an external one, as per the example in the documentation')
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.text-field {
	position: relative;
	width: 100%;
	border-radius: var(--border-radius-large);
	&__main-wrapper {
		height: 36px;
		position: relative;
	}
	&__input {
		margin: 0;
		padding: 0 12px;
		font-size: var(--default-font-size);
		background-color: var(--color-main-background);
		color: var(--color-main-text);
		border: 2px solid var(--color-border-dark);
		height: 36px !important;
		border-radius: var(--border-radius-large);
		text-overflow: ellipsis;
		cursor: pointer;
		width: 100%;
		padding-right: 28px;
		-webkit-appearance: textfield !important;
		-moz-appearance: textfield !important;
		&:hover {
			border-color: var(--color-primary-element);
		}
		&:focus {
			cursor: text;
		}
		&--leading-icon {
			padding-left: 28px;
		}
		&--trailing-icon {
			padding-right: 28px;
		}
	}
	&__label {
		padding: 0px 4px 4px 12px;
		display: block;
		&--hidden {
			position: absolute;
			left: -10000px;
			top: auto;
			width: 1px;
			height: 1px;
			overflow: hidden;
		}
	}
	&__icon {
		position: absolute;
		height: 32px;
		width: 32px;
		display: flex;
		align-items: center;
		justify-content: center;
		opacity: 0.7;
		&--leading {
			bottom: 2px;
			left: 2px;
		}
		&--trailing {
			bottom: 2px;
			right: 2px;
		}
	}
	&__show-button {
		position: absolute !important;
		top: 2px;
		right: 1px;
	}
}
::v-deep .button-vue {
	min-width: unset !important;
	min-height: unset !important;
	height: 32px !important;
	width: 32px !important;
	border-radius: var(--border-radius-large) !important;
}
</style>:
