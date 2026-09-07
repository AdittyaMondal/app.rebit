<?php

namespace Tests\Feature;

use App\Enums\User\UserStatus;
use App\Http\Middleware\NativeAuthHandoffMiddleware;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The native app never sees a password. It opens the ordinary web auth pages in
 * the system browser and gets a token back through a custom URL scheme, using
 * PKCE so that nothing usable crosses that scheme.
 *
 * Run with:
 *   DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test --filter=NativeAuthHandoffTest
 */
class NativeAuthHandoffTest extends TestCase
{
    use RefreshDatabase;

    private string $verifier;
    private string $challenge;
    private string $state;

    protected function setUp(): void
    {
        parent::setUp();

        $this->verifier = Str::random(64);
        $this->challenge = $this->b64url(hash('sha256', $this->verifier, true));
        $this->state = Str::random(43);
    }

    private function b64url(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private function user(UserStatus $status = UserStatus::ACTIVE): User
    {
        return User::create([
            'username' => 'u' . Str::random(8),
            'email' => Str::random(8) . '@example.test',
            'password' => 'irrelevant',
            'tips' => [],
            'status' => $status,
        ]);
    }

    private function startUrl(): string
    {
        return "/auth/native/start?challenge={$this->challenge}&state={$this->state}";
    }

    private function handoffSession(int $ageSeconds = 0): array
    {
        return [NativeAuthHandoffMiddleware::SESSION_KEY => [
            'challenge' => $this->challenge,
            'state' => $this->state,
            'at' => now()->timestamp - $ageSeconds,
        ]];
    }

    // --- start ------------------------------------------------------------

    public function test_start_sends_a_guest_to_the_existing_login_page(): void
    {
        $this->get($this->startUrl())
            ->assertRedirect(route('user.auth.index'))
            ->assertSessionHas(NativeAuthHandoffMiddleware::SESSION_KEY);
    }

    public function test_start_short_circuits_for_an_already_signed_in_browser(): void
    {
        $this->actingAs($this->user())
            ->get($this->startUrl())
            ->assertRedirect(route('user.auth.native.complete'));
    }

    public function test_start_rejects_a_malformed_challenge(): void
    {
        $this->get('/auth/native/start?challenge=short&state=' . $this->state)
            ->assertSessionHasErrors('challenge');
    }

    // --- handoff middleware ------------------------------------------------

    public function test_middleware_diverts_an_active_user_after_any_web_auth_flow(): void
    {
        // Every flow -- Livewire login, Google, signup+onboarding, reset --
        // finishes at route('user.desktop.index'). This is that request.
        $this->actingAs($this->user())
            ->withSession($this->handoffSession())
            ->get('/')
            ->assertRedirect(route('user.auth.native.complete'));
    }

    public function test_middleware_waits_for_onboarding_to_finish(): void
    {
        // Username.php is the onboarding step that flips the user to ACTIVE.
        // Diverting before then would strand the user mid-signup.
        $response = $this->actingAs($this->user(UserStatus::ONBOARDING))
            ->withSession($this->handoffSession())
            ->get('/');

        $this->assertStringNotContainsString(
            'auth/native/complete',
            (string) $response->headers->get('Location')
        );
    }

    public function test_middleware_is_inert_for_ordinary_web_traffic(): void
    {
        // No session flag: this is every web user, all the time.
        $response = $this->actingAs($this->user())->get('/');

        $this->assertStringNotContainsString(
            'auth/native/complete',
            (string) $response->headers->get('Location')
        );
    }

    // --- complete ----------------------------------------------------------

    public function test_complete_returns_a_code_not_a_token(): void
    {
        $response = $this->actingAs($this->user())
            ->withSession($this->handoffSession())
            ->get(route('user.auth.native.complete'));

        $location = $response->headers->get('Location');

        $this->assertStringStartsWith(config('native.scheme') . '://auth?', $location);
        parse_str(parse_url($location, PHP_URL_QUERY), $query);

        $this->assertSame($this->state, $query['state']);
        $this->assertSame(43, strlen($query['code']));

        // The whole point of PKCE here: nothing usable crosses the scheme.
        $this->assertStringNotContainsString('|', $location, 'a Sanctum token leaked into the redirect');
        $response->assertSessionMissing(NativeAuthHandoffMiddleware::SESSION_KEY);
    }

    public function test_complete_refuses_a_stale_handoff(): void
    {
        $this->actingAs($this->user())
            ->withSession($this->handoffSession(config('native.handoff_ttl') + 60))
            ->get(route('user.auth.native.complete'))
            ->assertStatus(419);
    }

    public function test_complete_refuses_without_a_handoff_in_session(): void
    {
        $this->actingAs($this->user())
            ->get(route('user.auth.native.complete'))
            ->assertStatus(419);
    }

    // --- exchange ----------------------------------------------------------

    private function codeFor(User $user): string
    {
        $location = $this->actingAs($user)
            ->withSession($this->handoffSession())
            ->get(route('user.auth.native.complete'))
            ->headers->get('Location');

        parse_str(parse_url($location, PHP_URL_QUERY), $query);

        // The real exchange arrives from the app with no session and no token
        // -- the code and verifier are the whole credential. actingAs() would
        // otherwise leak a signed-in guard into the next request.
        $this->app['auth']->forgetGuards();

        return $query['code'];
    }

    public function test_exchange_issues_an_expiring_token(): void
    {
        $user = $this->user();
        $code = $this->codeFor($user);

        $response = $this->postJson('/api/auth/native/exchange', [
            'code' => $code,
            'verifier' => $this->verifier,
            'device_name' => 'pixel-test',
        ])->assertOk();

        $this->assertNotEmpty($response->json('data.token'));
        $this->assertNotNull($response->json('data.expires_at'));
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'native: pixel-test',
        ]);
    }

    public function test_exchange_rejects_a_wrong_verifier(): void
    {
        $code = $this->codeFor($this->user());

        $this->postJson('/api/auth/native/exchange', [
            'code' => $code,
            'verifier' => Str::random(64),
            'device_name' => 'attacker',
        ])->assertStatus(401);
    }

    public function test_a_code_cannot_be_replayed(): void
    {
        $code = $this->codeFor($this->user());
        $payload = ['code' => $code, 'verifier' => $this->verifier, 'device_name' => 'pixel-test'];

        $this->postJson('/api/auth/native/exchange', $payload)->assertOk();
        $this->postJson('/api/auth/native/exchange', $payload)->assertStatus(401);
    }

    public function test_exchange_refuses_a_user_suspended_between_login_and_exchange(): void
    {
        $user = $this->user();
        $code = $this->codeFor($user);

        $user->update(['status' => UserStatus::SUSPENDED]);

        $this->postJson('/api/auth/native/exchange', [
            'code' => $code,
            'verifier' => $this->verifier,
            'device_name' => 'pixel-test',
        ])->assertStatus(401);
    }

    public function test_the_issued_token_actually_authenticates(): void
    {
        $code = $this->codeFor($this->user());

        $token = $this->postJson('/api/auth/native/exchange', [
            'code' => $code,
            'verifier' => $this->verifier,
            'device_name' => 'pixel-test',
        ])->json('data.token');

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/bootstrap/bootstrap')
            ->assertOk();
    }
}
