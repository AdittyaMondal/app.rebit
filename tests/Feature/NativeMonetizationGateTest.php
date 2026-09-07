<?php

namespace Tests\Feature;

use App\Enums\User\UserStatus;
use App\Http\Middleware\NativeClientMiddleware;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * POST /api/wallet/deposit returns a hosted Stripe/PayPal checkout URL for
 * buying in-app currency. Google Play's Payments policy forbids that, so in the
 * app it must be unreachable, not merely hidden -- a reviewer will poke the API.
 *
 * The web must keep working exactly as before.
 */
class NativeMonetizationGateTest extends TestCase
{
    use RefreshDatabase;

    private const NATIVE_UA = 'Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 ' . NativeClientMiddleware::USER_AGENT_MARKER . '1.0';
    private const WEB_UA = 'Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 Chrome/126.0';

    protected function setUp(): void
    {
        parent::setUp();

        /*
         * SettingsServiceProvider populates config('features.*'),
         * config('app.default_currency') and friends from the database at boot
         * -- which happens before RefreshDatabase has migrated, so that config
         * is empty by the time a test runs. Re-run the merge now that the
         * schema and its default rows exist.
         */
        $this->seed(\Database\Seeders\CurrencySeeder::class);

        (new \App\Providers\SettingsServiceProvider($this->app))->boot();
    }

    private function user(): User
    {
        return User::create([
            'username' => 'u' . Str::random(8),
            'email' => Str::random(8) . '@example.test',
            'password' => 'irrelevant',
            'tips' => [],
            'status' => UserStatus::ACTIVE,
        ]);
    }

    public function test_deposit_is_unreachable_from_the_app(): void
    {
        $this->actingAs($this->user())
            ->withHeader('User-Agent', self::NATIVE_UA)
            ->postJson('/api/wallet/deposit', ['amount' => 100, 'provider' => 'stripe'])
            ->assertNotFound();
    }

    public function test_the_whole_wallet_api_is_unreachable_from_the_app(): void
    {
        // Not just the purchase endpoint: balances and transaction history are
        // pointless once the wallet is hidden, and leaving them reachable would
        // still show money in the UI.
        foreach (['/api/wallet/data', '/api/wallet/transactions', '/api/wallet/payment/providers'] as $endpoint) {
            $this->actingAs($this->user())
                ->withHeader('User-Agent', self::NATIVE_UA)
                ->getJson($endpoint)
                ->assertNotFound();
        }
    }

    public function test_the_web_wallet_is_untouched(): void
    {
        $response = $this->actingAs($this->user())
            ->withHeader('User-Agent', self::WEB_UA)
            ->postJson('/api/wallet/deposit', ['amount' => 100, 'provider' => 'stripe']);

        // Whatever it answers, it must not be the feature gate's 404.
        $this->assertNotSame(404, $response->getStatusCode());
    }

    public function test_the_app_is_told_the_wallet_is_off(): void
    {
        $this->withHeader('User-Agent', self::NATIVE_UA)
            ->getJson('/api/system/embeds')
            ->assertOk()
            ->assertJsonPath('data.config.features.wallet.enabled', false);
    }

    public function test_the_web_is_told_the_wallet_is_on(): void
    {
        $this->withHeader('User-Agent', self::WEB_UA)
            ->getJson('/api/system/embeds')
            ->assertOk()
            ->assertJsonPath('data.config.features.wallet.enabled', true);
    }
}
