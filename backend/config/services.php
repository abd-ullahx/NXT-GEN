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
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'microsoft' => [
        'client_id'     => env('MICROSOFT_CLIENT_ID'),
        'client_secret' => env('MICROSOFT_CLIENT_SECRET'),
        'tenant_id'     => env('MICROSOFT_TENANT_ID', 'common'),
        'redirect_uri'  => env('MICROSOFT_REDIRECT_URI', 'http://localhost:8000/api/outlook/callback'),
    ],

    'zego' => [
        'app_id' => env('ZEGO_APP_ID'),
        'app_sign' => env('ZEGO_APP_SIGN'),
        'server_secret' => env('ZEGO_SERVER_SECRET'),
    ],

    'whisper' => [
        'url'                     => env('WHISPER_SERVICE_URL', 'http://127.0.0.1:9000'),
        'token'                   => env('WHISPER_API_TOKEN'),
        'timeout'                 => (int) env('WHISPER_TIMEOUT', 900),
        'disk'                    => env('CALL_RECORDINGS_DISK', 'local'),
        'max_upload_size_kb'      => (int) env('CALL_RECORDING_MAX_SIZE_KB', 102400), // 100MB
        'consent_notice_required' => (bool) env('CALL_RECORDING_CONSENT_REQUIRED', true),
    ],

    'twilio' => [
        'account_sid'   => env('TWILIO_ACCOUNT_SID'),
        'auth_token'    => env('TWILIO_AUTH_TOKEN'),
        'webhook_token' => env('TWILIO_WEBHOOK_TOKEN'),
    ],

];

