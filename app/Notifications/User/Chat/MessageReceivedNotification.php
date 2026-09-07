<?php

namespace App\Notifications\User\Chat;

use App\Models\Message;
use App\Notifications\Channels\WebPushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Direct messages are the highest-value push in a social app, and until now this
 * class carried no payload at all: it took no constructor arguments and only
 * ever broadcast an empty array. The direct_messages toggle existed in the
 * settings screen and did nothing for push.
 *
 * Primitives are extracted in the constructor rather than holding the Message
 * model, because this is queued and the base Notification does not serialise
 * models. It also keeps the queue payload small.
 */
class MessageReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    private string $senderName;
    private string $chatUuid;
    private ?string $preview;

    public function __construct(?Message $message = null)
    {
        // Nullable so any existing caller that has not been updated still works
        // exactly as before: broadcast only, no push.
        $this->senderName = $message?->user?->name ?? '';
        $this->chatUuid = $message?->chat_uuid ?? '';
        $this->preview = $message?->content ? Str::limit($message->content, 80) : null;
    }

    public function broadcastType(): string
    {
        return 'chat.notification';
    }

    public function via(object $notifiable): array
    {
        $channels = [];

        // TODO: Add email channel with time window.

        if($notifiable->pushNotificationSettings->direct_messages) {
            array_push($channels, 'broadcast');

            if (config('notifications.push.enabled') && $this->chatUuid !== '') {
                array_push($channels, WebPushChannel::class);
            }
        }

        return $channels;
    }

    public function toBroadcast(): BroadcastMessage
    {
        return new BroadcastMessage([
            'data' => []
        ]);
    }

    public function toPush(object $notifiable): array
    {
        $locale = $notifiable->language;

        return [
            'title' => $this->senderName !== ''
                ? $this->senderName
                : __('notifications.subjects.message_received', [], $locale),
            // A media-only message has no text to preview.
            'body' => $this->preview ?? __('notifications.chat.message_attachment', [], $locale),
            'type' => 'chat.message',
            'link' => url("/messenger/c/{$this->chatUuid}"),
            'badge' => 0,
        ];
    }
}
