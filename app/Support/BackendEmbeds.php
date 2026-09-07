<?php

namespace App\Support;

/**
 * The runtime configuration blob the SPA reads through window.BackendEmbeds.
 *
 * Single source of truth for two consumers:
 *   - resources/views/layouts/spa/apps/parts/embeds.blade.php  (web, inlined)
 *   - GET /api/system/embeds                                   (native, fetched)
 *
 * They must never drift, which is why the array lives here and not in Blade.
 */
class BackendEmbeds
{
    public static function toArray(): array
    {
        return [
            'assets' => [
                'images' => [
                    'upload_avatar' => asset('assets/avatars/upload-avatar.png'),
                    'bio_avatar' => asset('assets/avatars/bio-avatar.png'),
                ],
                'logos' => [
                    'url' => self::logotypeUrl(),
                ],
                'emojis' => [
                    'animated' => config('emojis.animated'),
                ],
            ],
            'translation_service' => [
                'name' => (string) config('services.translation.name'),
                'url' => (string) config('services.translation.url'),
                'logo_url' => (string) config('services.translation.logo'),
            ],
            'routes' => [
                'business_dashboard_index' => route('business.dashboard.index'),
                'business_wallet_cashouts' => route('business.wallet.cashouts'),
                'user_auth_index' => route('user.auth.index'),
                'terms_of_use' => route('document.terms.index'),
                'privacy_policy' => route('document.privacy.index'),
                'cookies_policy' => route('document.cookies.index'),
                'api_developers' => route('document.developers.index'),
                'help_center' => route('document.help.index'),
                'user_linker_index' => route('user.linker.index'),
                'verification_rules' => route('document.verification.index'),
                'become_author' => route('document.author.index'),
                // Added for store compliance: published content standards must be
                // reachable from inside the app, not only from the web footer.
                'community_guidelines' => route('document.guidelines.index'),
            ],
            'sharing' => [
                'stories' => config('content.sharing.stories'),
            ],
            'links' => [
                'assets_url' => asset('/'),
                'base_url' => url('/') . '/',
                'assets' => [
                    'emoji' => asset('assets/emoji/img-apple-64') . '/',
                ],
                'guide_links' => [
                    'publication_rules' => asset('documents/publication-rules.pdf'),
                ],
            ],
            'locale' => app()->getLocale(),
            'locale_name' => (new Languages())->getLocaleName(),
            'available_locales' => available_locales(),
            'theme' => theme_name(),
            'config' => [
                'features' => config('features'),
                'app' => [
                    'name' => (string) config('app.name'),
                    'currency' => default_currency(),
                ],
                'verification' => [
                    'service_url' => (string) config('verification.service_url'),
                ],
                'validation' => [
                    'user' => [
                        'bio' => config('user.validation.bio'),
                    ],
                ],
                'chat' => [
                    'group' => [
                        'invite_expire_days' => config('chat.group.invite_expire_days'),
                    ],
                ],
                'user' => [
                    'default_avatar' => asset(config('user.avatar')),
                ],
                'wallet' => [
                    'name' => (string) config('wallet.name'),
                    'about_link' => (string) config('wallet.about_link'),
                    'deposit' => [
                        'max_amount' => config('wallet.deposit.max_amount'),
                        'min_amount' => config('wallet.deposit.min_amount'),
                        'commission' => config('wallet.commission.deposit'),
                    ],
                    'transfer' => [
                        'max_amount' => config('wallet.transfer.max_amount'),
                        'min_amount' => config('wallet.transfer.min_amount'),
                        'commission' => config('wallet.commission.transfer'),
                    ],
                    'withdraw' => [
                        'max_amount' => config('wallet.withdraw.max_amount'),
                        'min_amount' => config('wallet.withdraw.min_amount'),
                        'commission' => config('wallet.commission.withdraw'),
                    ],
                ],
                'sounds' => [
                    'chat' => [
                        'active_chat_message_received' => asset(config('chat.sounds.active_chat_message_received')),
                        'chat_message_sent' => asset(config('chat.sounds.chat_message_sent')),
                        'background_chat_message_received' => asset(config('chat.sounds.background_chat_message_received')),
                    ],
                    'notification' => [
                        'received' => asset(config('notifications.sounds.notification_received')),
                        'ui_feedback' => asset(config('notifications.sounds.ui_feedback')),
                    ],
                ],
            ],
            'contacts' => [
                'support_email' => (string) config('contacts.support_email'),
                'support_phone' => (string) config('contacts.support_phone'),
                'address' => (string) config('contacts.address'),
            ],
        ];
    }

    /**
     * Mirrors the View::composer('*') in ViewServiceProvider, which is not
     * available outside a rendered view (e.g. the API endpoint, console).
     */
    private static function logotypeUrl(): string
    {
        $logo = (theme_name() === 'dark') ? 'dark.png' : 'light.png';

        return asset("assets/logos/{$logo}");
    }
}
