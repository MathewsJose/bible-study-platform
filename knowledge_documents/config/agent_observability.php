<?php

declare(strict_types=1);

return [
    'tracing' => [
        'enabled' => (bool) env('AGENT_TRACE_ENABLED', true),
        'store_inputs' => (bool) env('AGENT_TRACE_STORE_INPUTS', false),
        'store_outputs' => (bool) env('AGENT_TRACE_STORE_OUTPUTS', false),
        'retention_days' => (int) env('AGENT_TRACE_RETENTION_DAYS', 30),
        'prune_limit' => (int) env('AGENT_TRACE_PRUNE_LIMIT', 500),
        'sampling_rate' => (float) env('AGENT_TRACE_SAMPLING_RATE', 1.0),
    ],

    'trace_api' => [
        'token' => env('AGENT_TRACE_API_TOKEN', ''),
    ],

    'metrics' => [
        'track_tokens' => (bool) env('AGENT_TRACE_TRACK_TOKENS', true),
    ],

    'redaction' => [
        'keys' => [
            'authorization',
            'api_key',
            'token',
            'password',
            'secret',
            'headers',
        ],
        'patterns' => [
            '/Bearer\s+[A-Za-z0-9\-\._~\+\/]+=*/i',
            '/sk-[A-Za-z0-9\-_]+/',
        ],
    ],

    'evaluation' => [
        'dataset_versions' => [
            'agent' => env('AGENT_EVALUATION_DATASET_VERSION', 'agent-v1'),
            'retrieval' => env('RETRIEVAL_EVALUATION_DATASET_VERSION', 'retrieval-v1'),
            'answer' => env('ANSWER_EVALUATION_DATASET_VERSION', 'answer-v1'),
        ],
        'regression' => [
            'success_rate_drop' => (float) env('AGENT_EVAL_SUCCESS_RATE_DROP', 0.05),
            'latency_increase_ratio' => (float) env('AGENT_EVAL_LATENCY_INCREASE_RATIO', 0.25),
        ],
    ],

    'replay' => [
        'score_tolerance' => (float) env('AGENT_REPLAY_SCORE_TOLERANCE', 0.001),
        'allow_http_live_replay' => (bool) env('AGENT_REPLAY_HTTP_LIVE', true),
    ],
];
