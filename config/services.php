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

    'ai_pacs' => [
        'base_url' => env('AI_PACS_URL', 'http://124.225.183.175:8361'),
        'username' => env('AI_PACS_USERNAME'),
        'password' => env('AI_PACS_PASSWORD'),
        'driver' => env('AI_PACS_DRIVER', 'http'),
        'timeout_seconds' => (int) env('AI_PACS_TIMEOUT_SECONDS', 30),
        'polling_interval_seconds' => (int) env('AI_PACS_POLLING_INTERVAL_SECONDS', 2),
        'max_polling_attempts' => (int) env('AI_PACS_MAX_POLLING_ATTEMPTS', 10),
    ],

];
