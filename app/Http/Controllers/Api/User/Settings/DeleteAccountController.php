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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Actions\User\DeleteUserAction;
use App\Models\AccountDeletionFeedback;
use Illuminate\Support\Facades\Validator;
use App\Traits\Http\Api\SupportsApiResponses;

class DeleteAccountController extends Controller
{
    use SupportsApiResponses;

    private $me;

    public function __construct()
    {
        $this->me = me();
    }

    private const REDACTED = '[redacted on deletion]';

    public function deleteAccount(Request $request) {

        $validator = Validator::make(data: [
            'password' => $request->get('password', ''),
            'message' => $request->get('message', '')
        ], rules: [
            'password' => ['required', 'bail', 'string', 'max:120'],
            'message' => ['nullable', 'bail', 'string', 'max:1200']
        ], attributes: [
            'password' => __('labels.password'),
            'message' => __('labels.message')
        ]);

        if($validator->fails()) {
            return $this->throwValidationError($validator);
        }
        else{
            if(Hash::check($request->get('password'), $this->me->password)) {
                $deleteReasonMessage = $request->get('message', '');

                if($deleteReasonMessage) {
                    /*
                     * The point of this row is "why did people leave", and that
                     * is the message plus the aggregate counts. Retaining name,
                     * email, phone, IP and user agent after someone asks to be
                     * deleted keeps exactly the identifiers the deletion was
                     * meant to remove -- bad under both stores' policies and
                     * India's DPDP Act.
                     *
                     * The email is kept only as a one-way hash, which still
                     * lets repeated sign-up-and-delete cycles be spotted without
                     * holding the address. The columns are NOT NULL, so the rest
                     * are redacted rather than nulled.
                     */
                    AccountDeletionFeedback::create([
                       'feedback_message' => $deleteReasonMessage,
                       'username' => self::REDACTED,
                       'full_name' => self::REDACTED,
                       'email' => hash('sha256', (string) $this->me->email),
                       'phone' => self::REDACTED,
                       'registered_at' => $this->me->getCreatedAt()->getTimestamp(),
                       'publications_count' => $this->me->publications_count,
                       'followers_count' => $this->me->followers_count,
                       'following_count' => $this->me->following_count,
                       'ip_address' => self::REDACTED,
                       'user_agent' => self::REDACTED,
                       'deleted_at' => now()
                    ]);
                }

                // A partial teardown used to be logged and then reported as
                // success, leaving the user believing an account still on the
                // platform was gone.
                if ((new DeleteUserAction($this->me))->execute() !== true) {
                    return $this->responseError([
                        'message' => __('user.account_deletion_failed')
                    ]);
                }

                Auth::guard('web')->logout();
    
                return $this->responseSuccess([
                    'message' => __('user.account_deleted')
                ]);
            }
            else {
                return $this->responseError([
                    'message' => __('user.validation.password.incorrect'),
                    'errors' => [
                        'password' => [__('user.validation.password.incorrect')]
                    ]
                ]);
            }
        }
    }
}
