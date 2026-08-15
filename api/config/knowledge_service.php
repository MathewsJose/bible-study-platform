<?php

declare(strict_types=1);

return [
    'base_url' => env('KNOWLEDGE_SERVICE_URL', 'http://host.docker.internal:8080'),
    'token' => env('KNOWLEDGE_SERVICE_TOKEN', ''),
    'connect_timeout' => (int) env('KNOWLEDGE_SERVICE_CONNECT_TIMEOUT', 2),
    'timeout' => (int) env('KNOWLEDGE_SERVICE_TIMEOUT', 10),
    'retry_attempts' => (int) env('KNOWLEDGE_SERVICE_RETRY_ATTEMPTS', 2),
    'retry_sleep_ms' => (int) env('KNOWLEDGE_SERVICE_RETRY_SLEEP_MS', 150),
    'ai_rate_limit_per_minute' => (int) env('KNOWLEDGE_AI_RATE_LIMIT_PER_MINUTE', 10),
    'feedback_rate_limit_per_minute' => (int) env('KNOWLEDGE_FEEDBACK_RATE_LIMIT_PER_MINUTE', 30),
    'feedback_store_comments' => (bool) env('KNOWLEDGE_FEEDBACK_STORE_COMMENTS', false),
];
