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
    | The Gemini API (paid tier only, see docs/deployment.md). One key serves
    | both the listening and the voice models.
    */
    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
    ],

    /*
    | Aisha (VoiceLab, aisha.group), a speech service built for Uzbek. One key
    | serves both listening and the voice (see docs/deployment.md).
    */
    'aisha' => [
        'api_key' => env('AISHA_API_KEY'),
    ],

    /*
    | The speech-to-text driver that hears the Child read:
    | - "fake" returns scripted words (tests, local demos) and needs no service;
    | - "gemini" uses a Gemini transcription model (GEMINI_API_KEY);
    | - "chirp" uses Google Cloud Speech-to-Text V2, signed in with a service
    |   account key file (GOOGLE_APPLICATION_CREDENTIALS). The project is the
    |   key file's unless CHIRP_PROJECT_ID is set;
    | - "aisha" uses Aisha's Uzbek speech-to-text (AISHA_API_KEY).
    | Production refuses "fake" (php artisan speech:check).
    */
    'speech_to_text' => [
        'driver' => env('SPEECH_TO_TEXT_DRIVER', 'fake'),
        'timeout' => 20,
        'gemini' => [
            'model' => env('GEMINI_TRANSCRIBE_MODEL', 'gemini-3.5-transcribe'),
        ],
        'chirp' => [
            'model' => env('CHIRP_MODEL', 'chirp_3'),
            'location' => env('CHIRP_LOCATION', 'us'),
            'project' => env('CHIRP_PROJECT_ID'),
            'credentials' => env('GOOGLE_APPLICATION_CREDENTIALS'),
        ],
    ],

    /*
    | The text-to-speech driver that voices the Syllable help clips:
    | - "fake" makes short silent clips (tests, local demos) and needs no service;
    | - "gemini" uses a Gemini speech model (GEMINI_API_KEY), such as
    |   gemini-3.8-flash-tts or the cheaper gemini-3.8-flash-lite-tts;
    | - "aisha" uses Aisha's Uzbek voice (AISHA_API_KEY): the Gulnoza model in
    |   one of its moods (Neutral, Cheerful, Happy, Sad), with AISHA_TTS_SLOW_SPEED
    |   (0.5 to 2.0, 1.0 is the usual pace) for the slow Syllable help clips.
    | Production refuses "fake" (php artisan speech:check).
    */
    'text_to_speech' => [
        'driver' => env('TEXT_TO_SPEECH_DRIVER', 'fake'),
        'timeout' => 60,
        'gemini' => [
            'model' => env('GEMINI_TTS_MODEL', 'gemini-3.8-flash-tts'),
            'voice' => env('GEMINI_TTS_VOICE', 'Kore'),
        ],
        'aisha' => [
            'model' => env('AISHA_TTS_MODEL', 'Gulnoza'),
            'mood' => env('AISHA_TTS_MOOD', 'Neutral'),
            'slow_speed' => (float) env('AISHA_TTS_SLOW_SPEED', 0.8),
        ],
    ],

];
