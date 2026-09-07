<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],
    'giphy' => [
        'api_key' => env('GIPHY_API_KEY'),
    ],
    'vonage' => [
        'api_key' => env('VONAGE_API_KEY'),
        'api_secret' => env('VONAGE_API_SECRET'),
        'from_number' => env('VONAGE_FROM_NUMBER'),
    ],
    'smsaero' => [
        'login' => env('SMSAERO_LOGIN'),
        'api_key' => env('SMSAERO_API_KEY'),
        'sender_name' => env('SMSAERO_SENDER_NAME'),
        'channel' => env('SMSAERO_CHANNEL'),
    ],
    'ipinfo' => [
        'token' => env('IPINFO_TOKEN'),
    ],
    'translation' => [
        'api_url' => env('TRANSLATION_SERVICE_API_URL'),
        'api_key' => env('TRANSLATION_SERVICE_API_KEY'),
        'service' => env('TRANSLATION_SERVICE'),
        'logo' => env('TRANSLATION_SERVICE_LOGO'),
        'name' => env('TRANSLATION_SERVICE_NAME'),
        'url' => env('TRANSLATION_SERVICE_URL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Firebase Cloud Messaging
    |--------------------------------------------------------------------------
    | Deliberately here rather than in the database with an admin editor, which
    | is this codebase's convention for Stripe, PayPal and Google login. Three
    | reasons:
    |
    |   1. It is a multi-line RSA private key, not two short strings, and
    |      Admin/Config/PWA.php already shows this app corrupting a JSON blob it
    |      round-trips through an admin form.
    |   2. Admin\Page\PageController writes form input into a .blade.php, so an
    |      admin-reachable service-account key widens that blast radius from
    |      "this site" to "this Firebase project".
    |   3. It is consumed by the Horizon worker, not by any admin request path.
    |
    | Deployment note: the credentials file must be mounted into BOTH the `app`
    | and `horizon` containers, or push fails silently in production only.
    */
    'fcm' => [
        'project_id' => env('FCM_PROJECT_ID'),
        'credentials' => env('FCM_CREDENTIALS', storage_path('app/private/fcm-service-account.json')),
    ],
];
