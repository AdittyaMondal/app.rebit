<?php


namespace App\Enums\User;

enum FollowStatus: string
{
	case REQUESTED = 'requested';
	case FOLLOWING = 'following';
	case REJECTED = 'rejected';
	case BLOCKED = 'blocked';

	public function isRequested(): bool
	{
		return $this === self::REQUESTED;
	}

	public function isFollowing(): bool
	{
		return $this === self::FOLLOWING;
	}
}
