<template>
	<template v-if="appLoading">
		<div class="fixed inset-0 z-100 bg-bg-pr flex-center">
            <div class="inline-block w-10">
                <img v-bind:src="$embedder('assets.logos.url')" alt="Logo" class="w-full">
            </div>
        </div>
	</template>
	<template v-else>
		<NetworkBanner></NetworkBanner>

		<ApplicationMainLayout v-if="isMainLayout"></ApplicationMainLayout>

		<FlatLayout v-if="isFlatLayout"></FlatLayout>

		<PostEditorLayout v-if="isPostEditorLayout"></PostEditorLayout>

		<MessengerLayout v-if="isMessengerLayout"></MessengerLayout>
	</template>
</template>

<script>
	import { defineComponent, computed, ref, onMounted } from 'vue';
	import { useAppStore } from '@M/store/app/app.store.js';
	import { useAuthStore } from '@M/store/auth/auth.store.js';
	import { useRoute, useRouter } from 'vue-router';
	import { Layouts } from '@M/core/constants/layouts.js';
	import { colibriEventBus } from '@/kernel/events/bus/index.js';

	import ApplicationMainLayout from '@M/layouts/ApplicationMainLayout.vue';
	import PostEditorLayout from '@M/layouts/PostEditorLayout.vue';
	import MessengerLayout from '@M/layouts/MessengerLayout.vue';
	import FlatLayout from '@M/layouts/FlatLayout.vue';
	import NetworkBanner from '@M/components/general/NetworkBanner.vue';

	export default defineComponent({
		setup: function() {
			const route = useRoute();
			const router = useRouter();
			const appLoading = ref(true);
			const appStore = useAppStore();
			const authStore = useAuthStore();

			onMounted(async () => {
                await appStore.bootstrapApplication();

				appLoading.value = false;

				colibriEventBus.on('auth:logout', logoutUser);

				// Dynamic import so none of the Capacitor plugin code is pulled
				// into the web bundle's execution path. Hides the native splash
				// only now, once there is something to show behind it.
				if (window.__NATIVE__) {
					const { initNativePlatform } = await import('@M/core/services/native/index.js');

					await initNativePlatform(router);
				}
			});

			const logoutUser = () => {
				colibriEventBus.emit('confirmation-modal:open', {
					title: __t('prompt.logout.title'),
					description: __t('prompt.logout.description'),
					confirmButtonText: __t('prompt.logout.confirm'),
					onConfirm: () => {
						authStore.logoutUser();

						// On native this URL is on another origin: following it
						// would navigate the WebView out of the bundle with no
						// way back. nativeSignOut() clears the stored token and
						// returns to the sign-in gate instead.
						if (window.__NATIVE__) {
							window.nativeSignOut();

							return;
						}

						window.location.href = embedder('routes.user_auth_index');
					}
				});
			}

			const layoutType = computed(() => {
                return route.meta.layout;
            });

			return {
				appLoading: appLoading,
				isMainLayout: computed(() => {
					return layoutType.value == Layouts.MAIN;
				}),
				isPostEditorLayout: computed(() => {
					return layoutType.value == Layouts.POST_EDITOR;
				}),
				isMessengerLayout: computed(() => {
					return layoutType.value == Layouts.MESSENGER;
				}),
				isFlatLayout: computed(() => {
					return layoutType.value == Layouts.FLAT;
				})
			};
		},
		components: {
			NetworkBanner: NetworkBanner,
			ApplicationMainLayout: ApplicationMainLayout,
			PostEditorLayout: PostEditorLayout,
			MessengerLayout: MessengerLayout,
			FlatLayout: FlatLayout
		}
	});
</script>