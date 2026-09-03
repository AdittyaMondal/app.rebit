<?php


namespace App\Enums\Story;

enum StoryPrivacy: string
{
	case ALL = 'all';
	case FOLLOWERS = 'followers';
	case SELECTED_USERS = 'selected_users';
}
