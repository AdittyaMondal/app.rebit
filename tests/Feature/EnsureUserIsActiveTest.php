<?php

namespace Tests\Feature;

use App\Enums\User\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Guards the API stack against blocked and suspended users.
 *
 * Before EnsureUserIsActive existed, a user banned after login kept full API
 * access -- on web until the session died, and for a native client until its
 * bearer token expired.
 *
 * Run with:
 *   DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test --filter=EnsureUserIsActiveTest
 */
class EnsureUserIsActiveTest extends TestCase
{
    use RefreshDatabase;

    private function user(UserStatus $status): User
    {
        // Deliberately not CreateUserAction: this middleware only reads
        // $user->status, and the full creation path drags in wallet and
        // settings rows that depend on DB-backed config.
        return User::create([
            'username' => 'u' . Str::random(8),
            'email' => Str::random(8) . '@example.test',
            'password' => 'irrelevant',
            'tips' => [],
            'status' => $status,
        ]);
    }

    public function test_active_user_is_not_blocked_by_this_middleware(): void
    {
        Sanctum::actingAs($this->user(UserStatus::ACTIVE));

        // Asserting "not refused" rather than 200: what this middleware owes us
        // is that it steps aside, not that the bootstrap controller succeeds.
        $response = $this->getJson('/api/bootstrap/bootstrap');

        $this->assertNotContains($response->getStatusCode(), [403, 409]);
    }

    public function test_blocked_user_is_refused(): void
    {
        Sanctum::actingAs($this->user(UserStatus::BLOCKED));

        $this->getJson('/api/bootstrap/bootstrap')
            ->assertForbidden()
            ->assertJsonPath('reason', 'blocked');
    }

    public function test_suspended_user_is_refused(): void
    {
        Sanctum::actingAs($this->user(UserStatus::SUSPENDED));

        $this->getJson('/api/bootstrap/bootstrap')
            ->assertForbidden()
            ->assertJsonPath('reason', 'suspended');
    }

    public function test_onboarding_user_gets_a_conflict_not_a_redirect(): void
    {
        Sanctum::actingAs($this->user(UserStatus::ONBOARDING));

        // UserStatusMiddleware would have issued a 302 to an HTML page here,
        // which is why it could not simply be reused on the api stack.
        $this->getJson('/api/bootstrap/bootstrap')
            ->assertStatus(409)
            ->assertJsonPath('reason', 'onboarding');
    }

    public function test_unauthenticated_requests_are_untouched(): void
    {
        // The middleware runs on every api request; it must not turn a guest
        // request into a 403. auth:sanctum still rejects it with a 401.
        $this->getJson('/api/bootstrap/bootstrap')->assertUnauthorized();
    }
}
