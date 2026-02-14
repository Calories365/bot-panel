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

    'openai' => [
        // Master switch: false -> api.openai.com, true -> local/proxy endpoints.
        'local_models' => env('LOCAL_MODELS', false),

        // Default public OpenAI base URL.
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com'),

        // Unified proxy endpoint (preferred for local mode).
        'proxy_base_url' => env('OPENAI_PROXY_URL', null),
        'proxy_token' => env('OPENAI_PROXY_TOKEN', null),

        // Optional split endpoints/tokens for local mode compatibility.
        'local_chat_base_url' => env('LLM_URL', null),
        'local_chat_token' => env('LLM_TOKEN', null),
        'local_audio_base_url' => env('WHISPER_URL', null),
        'local_audio_token' => env('WHISPER_TOKEN', null),

        // Request paths and model names.
        'chat_path' => env('OPENAI_CHAT_PATH', '/v1/chat/completions'),
        'audio_path' => env('OPENAI_AUDIO_PATH', '/v1/audio/transcriptions'),
        'chat_model' => env('OPENAI_CHAT_MODEL', 'gpt-4o'),
        'audio_model' => env('OPENAI_STT_MODEL', 'whisper-1'),
    ],

];
