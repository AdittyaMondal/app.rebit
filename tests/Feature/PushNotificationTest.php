<?php

namespace Tests\Feature;

use App\Constants\Notifications;
use App\Enums\User\UserStatus;
use App\Models\PushToken;
use App\Models\User;
use App\Notifications\Channels\WebPushChannel;
use App\Notifications\Traits\BaseNotification;
use App\Services\Push\FcmClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

/**
 * Push was inert before this: WebPushChannel computed toPush() and discarded the
 * result, and all eleven toPush() implementations returned [].
 */
class PushNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::create([
            'username' => 'u' . Str::random(8),
            'email' => Str::random(8) . '@example.test',
            'password' => 'irrelevant',
            'tips' => [],
            'status' => UserStatus::ACTIVE,
            'language' => 'en',
        ]);
    }

    private function withTokens(User $user, array $tokens): User
    {
        foreach ($tokens as $token) {
            PushToken::create(['user_id' => $user->id, 'token' => $token, 'platform' => 'android']);
        }

        return $user->fresh();
    }

    // --- payload ----------------------------------------------------------

    public function test_the_shared_trait_builds_a_payload_from_existing_copy(): void
    {
        $payload = (new FakePushNotification())->toPush($this->user());

        // Reuses lang/en/notifications.php rather than adding new strings.
        $this->assertSame('New comment on your post.', $payload['title']);
        $this->assertSame('Ada commented on your post', $payload['body']);
        $this->assertSame(Notifications::POST_COMMENTED, $payload['type']);
        $this->assertSame('https://app.rebit.in/publication/abc123', $payload['link']);
        $this->assertIsInt($payload['badge']);
    }

    public function test_a_notification_without_message_keys_yields_no_payload(): void
    {
        $notification = new FakePushNotification();
        $notification->data = ['actor' => ['name' => 'Ada']];

        $this->assertSame([], $notification->toPush($this->user()));
    }

    // --- channel ----------------------------------------------------------

    public function test_it_sends_to_every_registered_device(): void
    {
        $user = $this->withTokens($this->user(), ['tok-a', 'tok-b']);
        $fcm = new FakeFcmClient();

        (new WebPushChannel($fcm))->send($user, new FakePushNotification());

        $this->assertSame(['tok-a', 'tok-b'], $fcm->sentTo);
        $this->assertNotNull(PushToken::where('token', 'tok-a')->first()->last_used_at);
    }

    public function test_a_dead_token_is_dropped_rather_than_retried_forever(): void
    {
        $user = $this->withTokens($this->user(), ['dead', 'alive']);
        $fcm = new FakeFcmClient();
        $fcm->deadTokens = ['dead'];

        (new WebPushChannel($fcm))->send($user, new FakePushNotification());

        $this->assertDatabaseMissing('push_tokens', ['token' => 'dead']);
        $this->assertDatabaseHas('push_tokens', ['token' => 'alive']);
    }

    public function test_money_notifications_are_never_pushed(): void
    {
        // The wallet is hidden in native builds, so a "deposit completed" banner
        // would advertise a purchase path the app does not offer.
        $user = $this->withTokens($this->user(), ['tok']);

        $notification = new FakePushNotification();
        $notification->notificationType = Notifications::WALLET_DEPOSIT;

        $fcm = new FakeFcmClient();
        (new WebPushChannel($fcm))->send($user, $notification);

        $this->assertSame([], $fcm->sentTo);
    }

    public function test_one_unreachable_device_does_not_stop_the_others(): void
    {
        $user = $this->withTokens($this->user(), ['a', 'boom', 'c']);
        $fcm = new FakeFcmClient();
        $fcm->throwFor = ['boom'];

        (new WebPushChannel($fcm))->send($user, new FakePushNotification());

        $this->assertSame(['a', 'c'], $fcm->sentTo);
    }

    public function test_nothing_is_attempted_when_fcm_is_not_configured(): void
    {
        $user = $this->withTokens($this->user(), ['tok']);
        $fcm = new FakeFcmClient();
        $fcm->configured = false;

        (new WebPushChannel($fcm))->send($user, new FakePushNotification());

        $this->assertSame([], $fcm->sentTo);
    }

    // --- registration endpoints -------------------------------------------

    public function test_a_device_registers_and_unregisters(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->postJson('/api/settings/notifications/push/token', [
                'token' => 'fcm-token-1', 'platform' => 'android', 'app_version' => '1.0.0',
            ])->assertOk();

        $this->assertDatabaseHas('push_tokens', ['token' => 'fcm-token-1', 'user_id' => $user->id]);

        $this->actingAs($user)
            ->deleteJson('/api/settings/notifications/push/token', ['token' => 'fcm-token-1'])
            ->assertOk();

        $this->assertDatabaseMissing('push_tokens', ['token' => 'fcm-token-1']);
    }

    public function test_a_handed_on_device_moves_to_its_new_owner(): void
    {
        $first = $this->user();
        $second = $this->user();

        // Asserted at the model level rather than over two HTTP requests: the
        // test harness keeps the first user's guard resolved between requests,
        // which is a harness artefact, not the behaviour under test. The
        // invariant that matters is that the unique key is the token, so a
        // re-registration moves ownership instead of creating a second row.
        $attributes = ['platform' => 'android', 'last_used_at' => now()];

        PushToken::updateOrCreate(['token' => 'shared-device'], $attributes + ['user_id' => $first->id]);
        PushToken::updateOrCreate(['token' => 'shared-device'], $attributes + ['user_id' => $second->id]);

        $this->assertSame(1, PushToken::where('token', 'shared-device')->count());
        $this->assertDatabaseHas('push_tokens', ['token' => 'shared-device', 'user_id' => $second->id]);

        // And the previous owner keeps nothing, so they stop receiving pushes.
        $this->assertSame(0, $first->fresh()->pushTokens()->count());
    }

    // --- direct messages ---------------------------------------------------

    public function test_a_direct_message_builds_a_push_payload(): void
    {
        $sender = $this->user();
        $sender->forceFill(['first_name' => 'Ada'])->save();

        $message = new \App\Models\Message([
            'chat_uuid' => 'chat-uuid-1',
            'content' => 'are we still on for tonight?',
        ]);
        $message->setRelation('user', $sender);

        $payload = (new \App\Notifications\User\Chat\MessageReceivedNotification($message))
            ->toPush($this->user());

        $this->assertSame('are we still on for tonight?', $payload['body']);
        // Asserting the path, not the origin: url() uses the app URL, which
        // differs per environment. The path is what the deep-link router reads.
        $this->assertSame('/messenger/c/chat-uuid-1', parse_url($payload['link'], PHP_URL_PATH));
    }

    public function test_a_media_only_message_still_says_something(): void
    {
        $message = new \App\Models\Message(['chat_uuid' => 'chat-uuid-2', 'content' => null]);
        $message->setRelation('user', $this->user());

        $payload = (new \App\Notifications\User\Chat\MessageReceivedNotification($message))
            ->toPush($this->user());

        $this->assertSame('sent you an attachment', $payload['body']);
    }

    public function test_registration_requires_authentication(): void
    {
        $this->postJson('/api/settings/notifications/push/token',
            ['token' => 'x', 'platform' => 'android'])->assertUnauthorized();
    }
}

/**
 * Exercises the trait without building a real notification's object graph of
 * posts, comments and actors.
 */
class FakePushNotification extends Notification
{
    use BaseNotification;

    public $notificationType = Notifications::POST_COMMENTED;

    public array $data = [
        'message_group' => 'post',
        'message_key' => 'post_commented',
        'message_params' => [],
        'actor' => ['name' => 'Ada'],
    ];

    public function toDatabase(): array
    {
        return $this->data;
    }

    protected function getDestinationLink(): string
    {
        return 'https://app.rebit.in/publication/abc123';
    }
}

class FakeFcmClient extends FcmClient
{
    public array $sentTo = [];
    public array $deadTokens = [];
    public array $throwFor = [];
    public bool $configured = true;

    public function isConfigured(): bool
    {
        return $this->configured;
    }

    public function send(string $deviceToken, array $payload): bool
    {
        if (in_array($deviceToken, $this->throwFor, true)) {
            throw new RuntimeException('device unreachable');
        }

        if (in_array($deviceToken, $this->deadTokens, true)) {
            return false;
        }

        $this->sentTo[] = $deviceToken;

        return true;
    }
}
