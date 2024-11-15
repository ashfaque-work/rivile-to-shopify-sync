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

    'rivile' => [
        'url' => env('RIVILE_API_URL', 'https://api.manorivile.lt/client/v2'),
        'key' => env('RIVILE_API_KEY'),
        'list' => env('RIVILE_LIST', 'A'),
        'product_list_method' => env('RIVILE_PRODUCT_LIST_METHOD', 'GET_N17_LIST'),
        'product_group_method' => env('RIVILE_PRODUCT_GROUP_METHOD', 'GET_N19_LIST'),
        'product_brand_method' => env('RIVILE_PRODUCT_BRAND_METHOD', 'GET_N35_LIST'),
        'description_method' => env('RIVILE_DESCRIPTION_METHOD', 'GET_PAP_LIST'),
        'inventory_method' => env('RIVILE_INVENTORY_METHOD', 'GET_I17_LIST'),
        'collection_method' => env('RIVILE_COLLECTION_METHOD', 'GET_N35_LIST'),
    ],

    'shopify' => [
        'url' => env('SHOPIFY_API_URL', 'development-rivile.myshopify.com'),
        'access_token' => env('SHOPIFY_ACCESS_TOKEN'),
        'api_key' => env('SHOPIFY_API_KEY'),
        'secret' => env('SHOPIFY_API_SECRET'),
    ],

];
