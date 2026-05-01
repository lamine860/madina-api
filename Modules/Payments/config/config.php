<?php

return [
    'name' => 'Payments',

    'lengopay' => [
        'base_url' => env('LENGOPAY_BASE_URL', 'https://api.lengopay.example'),
        'initiate_path' => env('LENGOPAY_INITIATE_PATH', '/payments/initiate'),
        'api_key' => env('LENGOPAY_API_KEY'),
        'merchant_id' => env('LENGOPAY_MERCHANT_ID'),
        'webhook_secret' => env('LENGOPAY_WEBHOOK_SECRET'),
        'webhook_signature_header' => env('LENGOPAY_WEBHOOK_SIGNATURE_HEADER', 'X-Lengopay-Signature'),
        /** JSON key for redirect URL in initiate API response */
        'redirect_url_key' => env('LENGOPAY_REDIRECT_URL_KEY', 'redirect_url'),
        /** JSON key for provider transaction id in initiate API response */
        'transaction_id_key' => env('LENGOPAY_TRANSACTION_ID_KEY', 'transaction_id'),
    ],
];
