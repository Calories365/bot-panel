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

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'diary_api' => [
        'url' => env('DIARY_API_URL', 'http://nginx/api'),
        'host' => env('DIARY_HOST', 'calories365.org'),
        'token' => env('DIARY_API_TOKEN', null),
        'key' => env('DIARY_API_KEY', null),
    ],

    // Voice input has no paid limit; this only stops a flood of recordings from one
    // account (bot and site together), each of which costs OpenAI calls.
    'voice' => [
        'max_per_hour' => env('VOICE_RATE_LIMIT_PER_HOUR', 30),
    ],

    'openai' => [
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com'),

        // One key per interface language; "ua" uses the _UK one, other locales the _RU one.
        'keys' => [
            'ua' => env('OPENAI_API_KEY_UK'),
            'ru' => env('OPENAI_API_KEY_RU'),
            'en' => env('OPENAI_API_KEY_EN'),
        ],

        // The panel only transcribes speech; what it says the diary reads in the meal
        // draft. A new variable name on purpose: OPENAI_STT_MODEL in existing .env files
        // still says whisper-1, which OpenAI switches off in February 2027.
        'models' => [
            'transcription' => env('OPENAI_TRANSCRIPTION_MODEL', 'gpt-transcribe'),
        ],
    ],

];
