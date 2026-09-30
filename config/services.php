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

    'birbank' => [
        'test_mode' => env('BIRBANK_TEST_MODE', true),
        'test_url' => env('BIRBANK_TEST_URL', 'https://txpgtst.kapitalbank.az/api'),
        'live_url' => env('BIRBANK_LIVE_URL', 'https://e-commerce.kapitalbank.az/api'),
        'test_username' => env('BIRBANK_TEST_USERNAME'),
        'test_password' => env('BIRBANK_TEST_PASSWORD'),
        'username' => env('BIRBANK_USERNAME'),
        'password' => env('BIRBANK_PASSWORD'),
    ],

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

    'parfumshop_sms' => [
        'url' => env('PARFUMSHOP_SMS_URL', 'https://apps.lsim.az/quicksms/v1/send'),
        'history_url' => env('PARFUMSHOP_SMS_HISTORY_URL', 'https://apps.lsim.az/information/search-by-msisdn'),
        'login' => env('PARFUMSHOP_SMS_LOGIN'),
        'password' => env('PARFUMSHOP_SMS_PASSWORD'),
        'sender' => env('PARFUMSHOP_SMS_SENDER', 'ParfumShop'),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-5-mini'),
    ],

    'serper' => [
        'api_key' => env('SERPER_API_KEY'),
    ],

    // Brend loqoları (SVG → PNG): https://worldvectorlogo.com/docs/
    'worldvectorlogo' => [
        'api_key' => env('WORLDVECTORLOGO_API_KEY'),
    ],

];
