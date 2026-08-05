<?php

declare(strict_types=1);

return [
    'provider' => env('EMBEDDINGS_PROVIDER', env('EMBEDDING_PROVIDER', 'null')),
    'model' => env('EMBEDDINGS_MODEL', env('OPENAI_EMBEDDING_MODEL', '')),
    'dimensions' => (int) env('EMBEDDINGS_DIMENSIONS', env('OPENAI_EMBEDDING_DIMENSIONS', 1536)),
    'batch_size' => (int) env('EMBEDDINGS_BATCH_SIZE', 100),
    'timeout' => (int) env('EMBEDDINGS_TIMEOUT', 30),
    'retry_attempts' => (int) env('EMBEDDINGS_RETRY_ATTEMPTS', env('OPENAI_RETRY_ATTEMPTS', 3)),
    'retry_sleep_ms' => (int) env('EMBEDDINGS_RETRY_SLEEP_MS', env('OPENAI_RETRY_SLEEP_MS', 200)),
    'queue_connection' => env('EMBEDDINGS_QUEUE_CONNECTION', env('QUEUE_CONNECTION', 'database')),

    'openai' => [
        'api_key' => env('OPENAI_API_KEY', ''),
        'url' => env('OPENAI_EMBEDDINGS_URL', 'https://api.openai.com/v1/embeddings'),
    ],

    'local' => [
        'url' => env('LOCAL_EMBEDDING_URL', 'http://embedding-service:8000'),
    ],
];
