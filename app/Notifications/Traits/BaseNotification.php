<?php

namespace App\Notifications\Traits;

use App\Support\Num;
use Illuminate\Support\Str;

trait BaseNotification
{
	protected $notificationViewPath = 'emails.user.notifications.email-notification';
	
    public function broadcastType(): string
    {
        return 'main.notification';
    }

    public function databaseType(): string
    {
        return $this->notificationType;
    }

	protected function getUnreadCount(object $notifiable): array
	{
		$unreadCount = $notifiable->getUnreadNotificationsCount();
		$unreadCount += 1;

		return [
			'formatted' => Num::abbreviate($unreadCount),
			'raw' => $unreadCount
		];
	}

	/**
	 * Payload for native push.
	 *
	 * Lives here rather than in each notification class because all eleven of
	 * them share the same shape: a public no-argument toDatabase() and a
	 * getDestinationLink(). Their per-class toPush() stubs all returned an empty
	 * array, so this replaces eleven dead methods with one live one.
	 *
	 * Copy is reused verbatim from the lang notifications files -- the same
	 * subjects.{key} and {group}.{key} pair toMail() already renders -- so there
	 * is no second set of strings to write or translate.
	 */
	public function toPush(object $notifiable): array
	{
		$data = $this->toDatabase();

		if (! isset($data['message_group'], $data['message_key'])) {
			return [];
		}

		$key = $data['message_key'];
		$locale = $notifiable->language;

		$actor = $data['actor']['name'] ?? '';
		$action = __("notifications.{$data['message_group']}.{$key}", $data['message_params'] ?? [], $locale);

		return [
			'title' => __("notifications.subjects.{$key}", [], $locale),
			'body' => trim("{$actor} {$action}"),
			'type' => $this->notificationType,
			'link' => $this->getDestinationLink(),
			'badge' => $this->getUnreadCount($notifiable)['raw'],
		];
	}

	public function toBroadcast(object $notifiable): array
	{
        $unreadCount = $this->getUnreadCount($notifiable);

		return [
			'data' => $unreadCount
		];
	}
	
	protected function cutContent(string $content): string
	{
		return Str::limit($content, 50);
	}

	protected function getImportantNotificationChannels(): array
	{
		$channels = ['database'];

		if($this->isEmailEnabled()) {
			array_push($channels, 'mail');
		}

		if($this->isBroadcastEnabled()) {
			array_push($channels, 'broadcast');
		}

		return $channels;
	}

	protected function isPushEnabled(): bool
	{
		return config('notifications.push.enabled');
	}

	protected function isBroadcastEnabled(): bool
	{
		return config('notifications.broadcast.enabled');
	}

	protected function isEmailEnabled(): bool
	{
		return config('notifications.email.enabled');
	}
}
