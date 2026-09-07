<template>
	<div
		v-if="isOffline"
		class="fixed top-0 left-0 right-0 z-[99998] pt-safe-top bg-red-900 text-white text-center text-par-s py-1.5 px-4"
	>
		{{ $t('labels.no_connection') }}
	</div>
</template>

<script>
	/*
	 * The app had no offline state at all before this: a dropped connection just
	 * produced silent failures, because there are no axios error interceptors on
	 * the web path either.
	 *
	 * Driven by @capacitor/network through the event bus rather than
	 * navigator.onLine, which lies on Android (it reports "online" for a
	 * connected wifi with no route to the internet).
	 */
	import { defineComponent, ref, onMounted, onUnmounted } from 'vue';

	import { colibriEventBus } from '@/kernel/events/bus/index.js';

	export default defineComponent({
		setup: function() {
			const isOffline = ref(false);

			const onNetworkChange = (status) => {
				isOffline.value = status?.connected === false;
			};

			onMounted(() => colibriEventBus.on('native:network', onNetworkChange));
			onUnmounted(() => colibriEventBus.off('native:network', onNetworkChange));

			return {
				isOffline: isOffline,
			};
		}
	});
</script>
