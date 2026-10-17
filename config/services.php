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

        // New variable names on purpose: OPENAI_CHAT_MODEL and OPENAI_STT_MODEL in
        // existing .env files still say gpt-4o and whisper-1, which OpenAI switches
        // off in January and February 2027.
        'models' => [
            // What the user ate, from speech: accuracy matters more than price.
            'meal_analysis' => env('OPENAI_MEAL_ANALYSIS_MODEL', 'gpt-6.1-sol'),
            // Nutrition facts of a single product: short and frequent.
            'product' => env('OPENAI_PRODUCT_MODEL', 'gpt-6-luna'),
            'transcription' => env('OPENAI_TRANSCRIPTION_MODEL', 'gpt-transcribe'),
        ],

        // The GPT-6 models reason before answering; low keeps the answer fast. They
        // reject temperature other than 1 and max_tokens.
        'reasoning_effort' => env('OPENAI_REASONING_EFFORT', 'low'),
    ],

];
