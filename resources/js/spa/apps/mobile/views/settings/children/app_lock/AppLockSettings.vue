<template>
	<Toolbar v-on:close="$router.back()" v-bind:title="$t('settings.app_lock')"></Toolbar>
	<SettingsDesc v-bind:text="$t('settings.forms.app_lock.page_desc')"></SettingsDesc>

	<div class="mb-3">
		<Border height="h-2" opacity="opacity-50"></Border>
	</div>

	<div v-if="state.isAvailable" class="mb-4">
		<SectionToggle
			v-model="state.enabled"
			v-on:update:modelValue="toggle"
			v-bind:captionText="$t('settings.forms.app_lock.enable_helper')"
		v-bind:titleText="$t('settings.forms.app_lock.enable')"></SectionToggle>
	</div>

	<div v-else class="px-4">
		<p class="text-par-m text-lab-sc">
			{{ $t('settings.forms.app_lock.unavailable') }}
		</p>
	</div>
</template>

<script>
	import { defineComponent, reactive, onMounted } from 'vue';

	import Toolbar from '@M/components/layout/Toolbar.vue';
	import SettingsDesc from '@M/views/settings/parts/SettingsDesc.vue';
	import SectionToggle from '@M/components/forms/SectionToggle.vue';

	export default defineComponent({
		setup: function() {
			const state = reactive({
				isAvailable: false,
				enabled: false,
			});

			onMounted(async () => {
				if (! window.__NATIVE__) {
					return;
				}

				const lock = await import('@M/core/services/native/lock.js');

				// A toggle that switches on and then silently never locks is
				// worse than no toggle, so availability is checked first.
				state.isAvailable = await lock.isLockAvailable();
				state.enabled = lock.isLockEnabled();
			});

			const toggle = async (value) => {
				const lock = await import('@M/core/services/native/lock.js');

				// Prove the user can actually authenticate before turning it on,
				// rather than locking them out on the next resume.
				if (value && ! await lock.verifyOnce()) {
					state.enabled = false;

					return;
				}

				lock.setLockEnabled(value);
			};

			return {
				state: state,
				toggle: toggle,
			};
		},
		components: {
			Toolbar: Toolbar,
			SettingsDesc: SettingsDesc,
			SectionToggle: SectionToggle,
		}
	});
</script>
