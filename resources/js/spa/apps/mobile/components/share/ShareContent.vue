<template>
	<div>
		<div class="flex gap-1 overflow-x-auto px-4 hidden-scroll">
			<button class="w-20 shrink-0">
				<ShareMediaItem
					v-on:click="copyLink"
					iconName="link-01"
					iconType="line"
				v-bind:buttonText="$t('labels.copy_link')"></ShareMediaItem>
			</button>

			<!--
				On native the OS share sheet replaces this row entirely. It
				reaches every app the user actually has, rather than a fixed
				list, and it sidesteps target="_blank", which in a bundled
				WebView either does nothing or navigates the app out of its own
				bundle with no way back.
			-->
			<button v-if="isNative" v-on:click="shareNatively" class="w-20 shrink-0">
				<ShareMediaItem
					iconName="share-01"
					iconType="line"
				v-bind:buttonText="$t('labels.share_via')"></ShareMediaItem>
			</button>

			<a v-else v-for="mediaItem in mediaOptions" v-bind:href="mediaItem.url + link" target="_blank" class="w-20 shrink-0">
				<ShareMediaItem v-bind:iconName="mediaItem.icon" v-bind:buttonText="mediaItem.name"></ShareMediaItem>
			</a>
		</div>
	</div>
</template>

<script>
	import { defineComponent } from 'vue';

	import ShareMediaItem from '@M/components/share/ShareMediaItem.vue';
	import MarginalTextButton from '@D/components/inter-ui/buttons/MarginalTextButton.vue';

	export default defineComponent({
		emits: ['close'],
		props: {
			mediaOptions: {
				type: Array,
				required: true
			},
			link: {
				type: String,
				required: true
			}
		},
		setup: function(props, context) {
			const shareNatively = async () => {
				try {
					const { Share } = await import('@capacitor/share');

					await Share.share({ url: props.link });
				} catch (error) {
					// User dismissed the sheet, or the plugin is unavailable.
					console.warn('Native share unavailable.', error);
				}

				context.emit('close');
			};

			return {
				isNative: window.__NATIVE__ === true,
				shareNatively: shareNatively,
				copyLink: () => {
					try {
						navigator.clipboard.writeText(props.link).then(() => {
							toastSuccess(__t('toast.share_copied'));
						});
					} catch (error) {
						toastError(__t('toast.share_copied'));
					}

					context.emit('close');
				}
			};
		},
		components: {
			ShareMediaItem: ShareMediaItem,
			MarginalTextButton: MarginalTextButton
		}
	});
</script>