<?php

declare(strict_types=1);

return [
    'name' => 'Notification',

    'sms_provider' => env('SMS_PROVIDER', 'orange'),

    'orange' => [
        'base_url' => env('ORANGE_SMS_BASE_URL', 'https://api.orange.com'),
        'oauth_token_path' => env('ORANGE_SMS_OAUTH_TOKEN_PATH', '/oauth/v3/token'),
        'sms_send_path_template' => env('ORANGE_SMS_SEND_PATH', '/smsmessaging/v1/outbound/tel%3A%2B{sender}/requests'),
        'client_id' => env('ORANGE_SMS_CLIENT_ID'),
        'client_secret' => env('ORANGE_SMS_CLIENT_SECRET'),
        'sender_number' => env('ORANGE_SMS_SENDER_NUMBER'),
        'sender_name' => env('ORANGE_SMS_SENDER_NAME', 'Kilora'),
        'oauth_cache_key' => env('ORANGE_SMS_OAUTH_CACHE_KEY', 'notification.orange.oauth_token'),
    ],
];
