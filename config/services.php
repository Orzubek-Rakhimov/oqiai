<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    /*
    | The speech-to-text driver that hears the Child read. "fake" returns
    | scripted words (tests, local demos) and needs no service.
    */
    'speech_to_text' => [
        'driver' => env('SPEECH_TO_TEXT_DRIVER', 'fake'),
    ],

    /*
    | The text-to-speech driver that voices the Syllable help clips. "fake"
    | makes short silent clips (tests, local demos) and needs no service.
    */
    'text_to_speech' => [
        'driver' => env('TEXT_TO_SPEECH_DRIVER', 'fake'),
    ],

];
