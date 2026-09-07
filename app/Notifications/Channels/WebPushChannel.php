<?php

namespace App\Notifications\Channels;

use App\Constants\Notifications;
use App\Services\Push\FcmClient;
use Illuminate\Notifications\Notification;
use Throwable;

/**
 * Delivers notifications to native devices via FCM.
 *
 * The class name is unchanged on purpose: eleven notification classes reference
 * it by FQCN in their via(), so swapping only the body keeps this a one-file
 * change rather than a twelve-file one.
 */
class WebPushChannel
{
    /**
     * Money-related notifications are never pushed.
     *
     * The wallet is hidden in native builds (Play Payments policy), so a
     * "your deposit completed" banner would be an in-app message about a
     * purchase the app does not offer -- exactly what store review catches.
     * These still reach the user by database and mail on the web.
     */
    private const SUPPRESSED_TYPES = [
        Notifications::WALLET_DEPOSIT,
        Notifications::PAYMENT_RECEIVED,
    ];

    public function __construct(private FcmClient $fcm)
    {
    }

    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notifiable, 'pushTokens') || ! $this->fcm->isConfigured()) {
            return;
        }

        $payload = $notification->toPush($notifiable);

        if (empty($payload) || in_array($payload['type'] ?? null, self::SUPPRESSED_TYPES, true)) {
            return;
        }

        // ponytail: serial loop -- FCM v1 has no multicast endpoint. Fine at
        // this scale; move into a queued job per chunk if a single
        // notification ever fans out to thousands of devices.
        foreach ($notifiable->pushTokens as $device) {
            try {
                if ($this->fcm->send($device->token, $payload) === false) {
                    // FCM reported the token as unregistered or invalid; it will
                    // never work again, so stop carrying it.
                    $device->delete();

                    continue;
                }

                $device->forceFill(['last_used_at' => now()])->save();
            } catch (Throwable $exception) {
                // One unreachable device must not abort delivery to the others,
                // nor fail the queued job and retry the whole fan-out.
                report($exception);
            }
        }
    }
}
