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

    'orange' => [
        'base_url' => env('ORANGE_BASE_URL', 'https://api.orange.com'),
        'oauth_token_path' => env('ORANGE_OAUTH_TOKEN_PATH', '/oauth/v3/token'),
        'payment_initiate_path' => env('ORANGE_PAYMENT_INITIATE_PATH', '/orange-money-webpay/gn/v1/webpayment'),
        'payment_status_path' => env('ORANGE_PAYMENT_STATUS_PATH', '/orange-money-webpay/gn/v1/transactionstatus'),
        'client_id' => env('ORANGE_CLIENT_ID'),
        'client_secret' => env('ORANGE_CLIENT_SECRET'),
        'merchant_key' => env('ORANGE_MERCHANT_KEY'),
        'return_url' => env('ORANGE_RETURN_URL'),
        'cancel_url' => env('ORANGE_CANCEL_URL'),
        'notif_url' => env('ORANGE_NOTIF_URL'),
        'webhook_secret' => env('ORANGE_WEBHOOK_SECRET'),
        'webhook_signature_header' => env('ORANGE_WEBHOOK_SIGNATURE_HEADER', 'X-Orange-Signature'),
        'currency' => env('ORANGE_CURRENCY', 'GNF'),
        'country_code' => env('ORANGE_COUNTRY_CODE', 'GN'),
        'pay_token_key' => env('ORANGE_PAY_TOKEN_KEY', 'pay_token'),
        'payment_url_key' => env('ORANGE_PAYMENT_URL_KEY', 'payment_url'),
        'transaction_id_key' => env('ORANGE_TRANSACTION_ID_KEY', 'txnid'),
        'status_key' => env('ORANGE_STATUS_KEY', 'status'),
        'oauth_cache_key' => env('ORANGE_OAUTH_CACHE_KEY', 'payments.orange.oauth_token'),
    ],
];
