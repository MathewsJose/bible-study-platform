<?php

declare(strict_types=1);

return [
    'default_provider' => env('LLM_DEFAULT_PROVIDER', env('AI_PROVIDER', 'null')),
    'default_model' => env('LLM_DEFAULT_MODEL', env('AI_MODEL', 'null-answer-model')),
    'timeout' => (int) env('LLM_TIMEOUT', env('AI_TIMEOUT', 30)),
    'connect_timeout' => (int) env('LLM_CONNECT_TIMEOUT', 5),
    'retry_attempts' => (int) env('LLM_RETRY_ATTEMPTS', env('AI_RETRY_ATTEMPTS', 2)),
    'retry_sleep_ms' => (int) env('LLM_RETRY_SLEEP_MS', env('AI_RETRY_SLEEP_MS', 250)),

    'routing' => [
        'answer_generation' => env('LLM_PROFILE_ANSWER_GENERATION', 'fast_local'),
        'agent_planning' => env('LLM_PROFILE_AGENT_PLANNING', 'fast_local'),
        'summarization' => env('LLM_PROFILE_SUMMARIZATION', 'fast_local'),
        'classification' => env('LLM_PROFILE_CLASSIFICATION', 'fast_local'),
        'evaluation' => env('LLM_PROFILE_EVALUATION', 'fast_local'),
    ],

    'profiles' => [
        'fast_local' => [
            'provider' => env('LLM_FAST_LOCAL_PROVIDER', env('LLM_DEFAULT_PROVIDER', env('AI_PROVIDER', 'null'))),
            'model' => env('LLM_FAST_LOCAL_MODEL', env('LLM_DEFAULT_MODEL', env('AI_MODEL', 'null-answer-model'))),
            'fallback' => env('LLM_FAST_LOCAL_FALLBACK', 'null_default'),
        ],
        'quality_local' => [
            'provider' => env('LLM_QUALITY_LOCAL_PROVIDER', env('LLM_LOCAL_PROVIDER', 'local')),
            'model' => env('LLM_QUALITY_LOCAL_MODEL', env('LLM_LOCAL_MODEL', 'local-model')),
            'fallback' => env('LLM_QUALITY_LOCAL_FALLBACK', 'null_default'),
        ],
        'null_default' => [
            'provider' => 'null',
            'model' => env('LLM_NULL_MODEL', 'null-answer-model'),
            'fallback' => null,
        ],
        'openai_default' => [
            'provider' => 'openai',
            'model' => env('LLM_OPENAI_MODEL', env('OPENAI_CHAT_MODEL', 'gpt-4o-mini')),
            'fallback' => env('LLM_OPENAI_FALLBACK', 'fast_local'),
        ],
        'anthropic_default' => [
            'provider' => 'anthropic',
            'model' => env('LLM_ANTHROPIC_MODEL', 'claude-3-5-haiku-latest'),
            'fallback' => env('LLM_ANTHROPIC_FALLBACK', 'fast_local'),
        ],
        'google_default' => [
            'provider' => 'google',
            'model' => env('LLM_GOOGLE_MODEL', 'gemini-1.5-flash'),
            'fallback' => env('LLM_GOOGLE_FALLBACK', 'fast_local'),
        ],
        'research' => [
            'provider' => env('LLM_RESEARCH_PROVIDER', env('LLM_DEFAULT_PROVIDER', env('AI_PROVIDER', 'null'))),
            'model' => env('LLM_RESEARCH_MODEL', env('LLM_DEFAULT_MODEL', env('AI_MODEL', 'null-answer-model'))),
            'fallback' => env('LLM_RESEARCH_FALLBACK', 'fast_local'),
        ],
    ],

    'providers' => [
        'null' => [
            'category' => 'local',
            'model' => env('LLM_NULL_MODEL', 'null-answer-model'),
            'base_url' => null,
            'api_key' => '',
            'health_path' => null,
            'enabled' => true,
        ],
        'local' => [
            'category' => 'local',
            'model' => env('LLM_LOCAL_MODEL', 'local-model'),
            'base_url' => env('LLM_LOCAL_BASE_URL', ''),
            'api_key' => env('LLM_LOCAL_API_KEY', ''),
            'health_path' => env('LLM_LOCAL_HEALTH_PATH', '/health'),
            'enabled' => (bool) env('LLM_LOCAL_ENABLED', true),
            'openai_compatible' => (bool) env('LLM_LOCAL_OPENAI_COMPATIBLE', true),
        ],
        'ollama' => [
            'category' => 'local',
            'model' => env('LLM_OLLAMA_MODEL', env('AI_MODEL', 'llama3.1')),
            'base_url' => env('LLM_OLLAMA_BASE_URL', env('OLLAMA_BASE_URL', 'http://ollama:11434')),
            'chat_url' => env('LLM_OLLAMA_CHAT_URL', env('OLLAMA_CHAT_URL', 'http://ollama:11434/api/chat')),
            'api_key' => '',
            'health_path' => '/api/tags',
            'enabled' => true,
        ],
        'openai' => [
            'category' => 'openai',
            'model' => env('LLM_OPENAI_MODEL', env('OPENAI_CHAT_MODEL', 'gpt-4o-mini')),
            'base_url' => env('LLM_OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'chat_url' => env('LLM_OPENAI_CHAT_URL', env('OPENAI_CHAT_URL', 'https://api.openai.com/v1/chat/completions')),
            'api_key' => env('LLM_OPENAI_API_KEY', env('OPENAI_API_KEY', '')),
            'health_path' => null,
            'enabled' => true,
        ],
        'anthropic' => [
            'category' => 'anthropic',
            'model' => env('LLM_ANTHROPIC_MODEL', 'claude-3-5-haiku-latest'),
            'base_url' => env('LLM_ANTHROPIC_BASE_URL', 'https://api.anthropic.com'),
            'chat_url' => env('LLM_ANTHROPIC_MESSAGES_URL', 'https://api.anthropic.com/v1/messages'),
            'api_key' => env('LLM_ANTHROPIC_API_KEY', env('ANTHROPIC_API_KEY', '')),
            'version' => env('LLM_ANTHROPIC_VERSION', '2023-06-01'),
            'health_path' => null,
            'enabled' => true,
        ],
        'google' => [
            'category' => 'google',
            'model' => env('LLM_GOOGLE_MODEL', 'gemini-1.5-flash'),
            'base_url' => env('LLM_GOOGLE_BASE_URL', 'https://generativelanguage.googleapis.com'),
            'api_key' => env('LLM_GOOGLE_API_KEY', env('GEMINI_API_KEY', '')),
            'health_path' => null,
            'enabled' => true,
        ],
    ],

    'models' => [
        'null:null-answer-model' => [
            'provider' => 'null',
            'model' => 'null-answer-model',
            'capabilities' => ['json' => false, 'tools' => false, 'streaming' => true],
            'context_window' => null,
        ],
        'openai:gpt-4o-mini' => [
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'capabilities' => ['json' => true, 'tools' => true, 'streaming' => true],
            'context_window' => 128000,
        ],
    ],

    'pricing' => [
        'models' => [
            'openai:gpt-4o-mini' => [
                'input_cost_per_1m_tokens' => env('LLM_OPENAI_GPT4O_MINI_INPUT_COST_PER_1M', null),
                'output_cost_per_1m_tokens' => env('LLM_OPENAI_GPT4O_MINI_OUTPUT_COST_PER_1M', null),
            ],
        ],
    ],
];
