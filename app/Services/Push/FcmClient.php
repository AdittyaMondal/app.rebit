<?php

namespace App\Services\Push;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Minimal Firebase Cloud Messaging HTTP v1 client.
 *
 * FCM v1 is two HTTP calls: exchange a self-signed JWT for an OAuth access
 * token, then POST the message. kreait/firebase-php is a very large SDK for
 * that, so this stays hand-rolled -- no new composer dependency.
 *
 * Using FCM for both platforms (rather than APNs directly for iOS) is what
 * keeps the server speaking one protocol. iOS, when it is added, costs zero
 * changes here.
 */
class FcmClient
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const CACHE_KEY = 'fcm.access_token';

    public function isConfigured(): bool
    {
        return ! empty(config('services.fcm.project_id'))
            && is_readable((string) config('services.fcm.credentials'));
    }

    /**
     * @return bool false when the token is dead and should be dropped.
     */
    public function send(string $deviceToken, array $payload): bool
    {
        $response = Http::withToken($this->accessToken())
            ->acceptJson()
            ->post($this->endpoint(), [
                'message' => $this->buildMessage($deviceToken, $payload),
            ]);

        // UNREGISTERED (404) and INVALID_ARGUMENT (400) both mean the token will
        // never work again; the caller deletes it rather than retrying forever.
        if (in_array($response->status(), [400, 404], true)) {
            return false;
        }

        $response->throw();

        return true;
    }

    private function buildMessage(string $deviceToken, array $payload): array
    {
        return [
            'token' => $deviceToken,
            'notification' => [
                'title' => $payload['title'] ?? config('app.name'),
                'body' => $payload['body'] ?? '',
            ],
            // Data travels alongside the notification so the tap handler can
            // route without a round trip. Values must be strings for FCM.
            'data' => [
                'type' => (string) ($payload['type'] ?? ''),
                'link' => (string) ($payload['link'] ?? ''),
            ],
            'android' => [
                'priority' => 'high',
                'notification' => [
                    'channel_id' => 'rebit_default',
                    'notification_count' => (int) ($payload['badge'] ?? 0),
                ],
            ],
            'apns' => [
                'payload' => [
                    'aps' => [
                        'badge' => (int) ($payload['badge'] ?? 0),
                        'sound' => 'default',
                    ],
                ],
            ],
        ];
    }

    private function endpoint(): string
    {
        return sprintf(
            'https://fcm.googleapis.com/v1/projects/%s/messages:send',
            config('services.fcm.project_id')
        );
    }

    /**
     * Google's access tokens last an hour; cached slightly short of that so a
     * request never picks up one that expires mid-flight.
     */
    private function accessToken(): string
    {
        return Cache::remember(self::CACHE_KEY, 3300, function () {
            $account = $this->serviceAccount();
            $now = time();

            $assertion = $this->signRs256([
                'iss' => $account['client_email'],
                'scope' => self::SCOPE,
                'aud' => self::TOKEN_URL,
                'iat' => $now,
                'exp' => $now + 3600,
            ], $account['private_key']);

            return Http::asForm()->post(self::TOKEN_URL, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $assertion,
            ])->throw()->json('access_token');
        });
    }

    private function serviceAccount(): array
    {
        $path = (string) config('services.fcm.credentials');

        if (! is_readable($path)) {
            throw new RuntimeException("FCM credentials not readable at [{$path}].");
        }

        $account = json_decode((string) file_get_contents($path), true);

        if (! isset($account['client_email'], $account['private_key'])) {
            throw new RuntimeException('FCM credentials file is missing client_email or private_key.');
        }

        return $account;
    }

    private function signRs256(array $claims, string $privateKey): string
    {
        $segments = [
            $this->base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])),
            $this->base64Url(json_encode($claims)),
        ];

        $signingInput = implode('.', $segments);
        $signature = '';

        if (! openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Could not sign the FCM JWT assertion.');
        }

        return $signingInput . '.' . $this->base64Url($signature);
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
