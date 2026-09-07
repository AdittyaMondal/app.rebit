<?php
/*
|--------------------------------------------------------------------------
| ColibriPlus - The Social Network Web Application.
|--------------------------------------------------------------------------
| Author: Mansur Terla. Full-Stack Web Developer, UI/UX Designer.
| Website: www.terla.me
| E-mail: mansurtl.contact@gmail.com
| Instagram: @mansur_terla
| Telegram: @mansurtl_contact
|--------------------------------------------------------------------------
| Copyright (c)  ColibriPlus. All rights reserved.
|--------------------------------------------------------------------------
*/

namespace App\Http\Controllers\Api\User\Settings;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Traits\Http\Api\SupportsApiResponses;

class NotificationSettingsController extends Controller
{
    use SupportsApiResponses;
    
    private $me;

    public function __construct() {
        $this->me = me();
    }

    public function getPushSettings()
    {
        $pushSettings = $this->me->pushNotificationSettings;

        return $this->responseSuccess([
            'data' => [
                'direct_messages' => $pushSettings->direct_messages,
                'reactions' => $pushSettings->reactions,
                'comments' => $pushSettings->comments,
                'shared_posts' => $pushSettings->shared_posts,
                'followers' => $pushSettings->followers,
                'follow_request' => $pushSettings->follow_request,
                'mentions' => $pushSettings->mentions,
            ]
        ]);
    }

    public function getEmailSettings()
    {
        $emailSettings = $this->me->emailNotificationSettings;

        return $this->responseSuccess([
            'data' => [
                'direct_messages' => $emailSettings->direct_messages,
                'reactions' => $emailSettings->reactions,
                'comments' => $emailSettings->comments,
                'shared_posts' => $emailSettings->shared_posts,
                'followers' => $emailSettings->followers,
                'follow_request' => $emailSettings->follow_request,
                'mentions' => $emailSettings->mentions,
            ]
        ]);
    }

    /**
     * Registers a device for native push.
     *
     * updateOrCreate keys on the token, not on the user: a phone handed to
     * someone else re-registers the same FCM token, and it must move to its new
     * owner rather than keep delivering the previous owner's notifications.
     */
    public function storePushToken(Request $request)
    {
        $input = $request->validate([
            'token' => ['required', 'string', 'max:512'],
            'platform' => ['required', 'string', 'in:android,ios'],
            'app_version' => ['nullable', 'string', 'max:32'],
        ]);

        \App\Models\PushToken::updateOrCreate(
            ['token' => $input['token']],
            [
                'user_id' => $this->me->id,
                'platform' => $input['platform'],
                'app_version' => $input['app_version'] ?? null,
                'last_used_at' => now(),
            ]
        );

        return $this->responseSuccess([
            'data' => null
        ]);
    }

    /**
     * Called on sign-out. Without it the next person to use the device would
     * receive the previous account's notifications.
     */
    public function deletePushToken(Request $request)
    {
        $input = $request->validate([
            'token' => ['required', 'string', 'max:512'],
        ]);

        $this->me->pushTokens()->where('token', $input['token'])->delete();

        return $this->responseSuccess([
            'data' => null
        ]);
    }

    public function updatePushSettings(Request $request)
    {
        $this->me->pushNotificationSettings()->update($this->fetchNotificationSettingsFromRequest($request));

        return $this->responseSuccess([
            'data' => null
        ]);
    }

    public function updateEmailSettings(Request $request)
    {
        $this->me->emailNotificationSettings()->update($this->fetchNotificationSettingsFromRequest($request));

        return $this->responseSuccess([
            'data' => null
        ]);
    }

    private function fetchNotificationSettingsFromRequest(Request $request) {
        return [
            'comments' => $request->boolean('comments', false),
            'direct_messages' => $request->boolean('direct_messages', false),
            'follow_request' => $request->boolean('follow_request', false),
            'followers' => $request->boolean('followers', false),
            'mentions' => $request->boolean('mentions', false),
            'reactions' => $request->boolean('reactions', false),
            'shared_posts' => $request->boolean('shared_posts', false)
        ];
    }

    public function updateLoginNotification(Request $request)
    {
        $loginNotification = $request->boolean('login_notification', false);

        $this->me->securitySettings()->update([
            'login_notification' => $loginNotification
        ]);

        return $this->responseSuccess([
            'data' => null
        ]);
    }
}
