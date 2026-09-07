<?php

namespace Tests\Feature;

use App\Enums\CensorLevel;
use App\Enums\User\UserStatus;
use App\Models\Censor;
use App\Models\User;
use App\Services\Censor\CensorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * A censor match permanently blacklists the author's IP and email, so a false
 * positive is expensive: an IP ban takes out everyone behind that address.
 *
 * The original matcher was a case-sensitive str_contains, which both missed the
 * obvious evasion of capitalising a letter and produced substring false
 * positives -- the classic Scunthorpe problem.
 */
class CensorServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        /*
         * banUser() blacklists and THEN fires UserBannedEvent, whose listener
         * does network I/O (broadcast/mail). The blacklisting is what is under
         * test, so the event is faked rather than the assertions weakened.
         */
        \Illuminate\Support\Facades\Event::fake([
            \App\Events\Admin\User\UserBannedEvent::class,
        ]);
    }

    private function user(): User
    {
        return User::create([
            'username' => 'u' . Str::random(8),
            'email' => Str::random(8) . '@example.test',
            'password' => 'irrelevant',
            'tips' => [],
            'status' => UserStatus::ACTIVE,
            'language' => 'en',
            'ip_address' => '203.0.113.7',
        ]);
    }

    /**
     * BlacklistService::add() records me()->id as admin_id. In production the
     * author is the authenticated user when their own content is censored, so
     * the test has to reproduce that or the ban path throws on a null me().
     */
    private function actingAsAuthor(): User
    {
        $user = $this->user();

        $this->actingAs($user);

        return $user;
    }

    private function ban(string $word): void
    {
        Censor::create(['word' => $word, 'level' => CensorLevel::BANNED]);
        Cache::forget(CensorService::CACHE_KEY);
    }

    private function wasBanned(User $user): bool
    {
        return app(\App\Services\Blacklist\BlacklistService::class)
            ->isEmailBlacklisted($user->email);
    }

    public function test_an_innocent_word_containing_a_banned_one_is_not_a_match(): void
    {
        $this->ban('scun');

        $user = $this->actingAsAuthor();
        app(CensorService::class)->setUser($user)->censor('I grew up in Scunthorpe.');

        $this->assertFalse($this->wasBanned($user), 'substring match wrongly banned the author');
    }

    public function test_matching_ignores_case(): void
    {
        $this->ban('badword');

        $user = $this->actingAsAuthor();
        app(CensorService::class)->setUser($user)->censor('This is a BadWord here.');

        $this->assertTrue($this->wasBanned($user), 'capitalisation evaded the censor');
    }

    public function test_a_clean_post_is_left_alone(): void
    {
        $this->ban('badword');

        $user = $this->actingAsAuthor();
        app(CensorService::class)->setUser($user)->censor('A perfectly ordinary sentence.');

        $this->assertFalse($this->wasBanned($user));
    }

    public function test_editing_the_word_list_takes_effect_without_clearing_the_cache(): void
    {
        // Warm the cache with an empty list, as a running app would have.
        app(CensorService::class);

        // No Cache::forget here on purpose: the model hook must do it.
        Censor::create(['word' => 'freshword', 'level' => CensorLevel::BANNED]);

        $user = $this->actingAsAuthor();
        app(CensorService::class)->setUser($user)->censor('a freshword appears');

        $this->assertTrue($this->wasBanned($user), 'the cached word list was never invalidated');
    }
}
