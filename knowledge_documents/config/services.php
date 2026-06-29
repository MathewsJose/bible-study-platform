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

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'embeddings_url' => env('OPENAI_EMBEDDINGS_URL', 'https://api.openai.com/v1/embeddings'),
        'embedding_model' => env('OPENAI_EMBEDDING_MODEL'),
        'embedding_dimensions' => env('OPENAI_EMBEDDING_DIMENSIONS') !== null
            ? (int) env('OPENAI_EMBEDDING_DIMENSIONS')
            : null,
        'embedding_provider' => env('EMBEDDING_PROVIDER', 'openai'),
        'retry_attempts' => (int) env('OPENAI_RETRY_ATTEMPTS', 3),
        'retry_sleep_ms' => (int) env('OPENAI_RETRY_SLEEP_MS', 200),
    ],

];
