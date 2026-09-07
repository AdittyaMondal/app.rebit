<?php

namespace App\Services\Censor;

use App\Models\User;
use App\Models\Censor;
use App\Enums\BlacklistType;
use App\Events\Admin\User\UserBannedEvent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Services\Blacklist\BlacklistService;

class CensorService
{
	public const CACHE_KEY = 'censor_banned_words';

	private User $userData;

	private array $bannedWords;

	public function __construct() {
		$this->bannedWords = $this->getBannedWords();
	}

	public function setUser(User $userData)
	{
		$this->userData = $userData;

		return $this;
	}

	public function censor(string $text): void
	{
		$match = $this->firstMatch($text);

		if ($match === null) {
			return;
		}

		/*
		 * This permanently blacklists the author's IP *and* email, with no
		 * review step and no way for them to appeal from inside the app -- and
		 * an IP ban takes out everyone else behind the same address. It is
		 * logged so that a wrongful ban can at least be found and reversed.
		 *
		 * ponytail: automatic, irreversible punishment on a word-list match is a
		 * moderation-policy decision, not a technical one. If it should instead
		 * flag for admin review, change banUser() -- the detection above is
		 * independent of what is done about it.
		 */
		Log::warning('Censor match; user auto-banned.', [
			'user_id' => $this->userData->id,
			'matched_word' => $match,
		]);

		$this->banUser();
	}

	/**
	 * Case-insensitive, and matched on word boundaries.
	 *
	 * The previous implementation used a case-sensitive str_contains, which both
	 * missed the obvious evasion of capitalising a letter and produced the
	 * classic substring false positives -- an innocent word containing a banned
	 * one permanently banned the author's IP.
	 */
	private function firstMatch(string $text): ?string
	{
		foreach ($this->bannedWords as $word) {
			if ($word === '') {
				continue;
			}

			if (preg_match('/\b' . preg_quote($word, '/') . '\b/iu', $text) === 1) {
				return $word;
			}
		}

		return null;
	}

	private function getBannedWords(): array
	{
		// rememberForever with no invalidation meant editing the word list had
		// no effect until someone cleared the cache by hand. Censor::saved()
		// and Censor::deleted() now forget this key -- see AppServiceProvider.
		return Cache::rememberForever(self::CACHE_KEY, function () {
			return Censor::banned()->pluck('word')->toArray();
		});
	}

	private function banUser()
	{
		$blacklistService = app(BlacklistService::class);

		$blacklistService->setType(BlacklistType::IP)->add($this->userData->ip_address);
        $blacklistService->setType(BlacklistType::EMAIL)->add($this->userData->email);

		event(new UserBannedEvent($this->userData));
	}
}
