<template>
	<div>
		<NcSettingsSection :name="$t('dav', 'Availability')"
			:description="$t('dav', 'If you configure your working hours, other users will see when you are out of office when they book a meeting.')">
			<AvailabilityForm />
		</NcSettingsSection>
		<NcSettingsSection :name="$t('dav', 'Absence')"
			:description="$t('dav', 'Configure your next absence period.')">
			<AbsenceForm />
		</NcSettingsSection>
	</div>
</template>

<script>
import NcSettingsSection from '@nextcloud/vue/dist/Components/NcSettingsSection.js'
import AbsenceForm from '../components/AbsenceForm.vue'
import AvailabilityForm from '../components/AvailabilityForm.vue'

export default {
	name: 'Availability',
	components: {
		NcSettingsSection,
		AbsenceForm,
		AvailabilityForm,
	},
}
</script>
