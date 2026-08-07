<?php

declare(strict_types=1);

return [
    'provider' => env('AI_PROVIDER', 'null'),
    'model' => env('AI_MODEL', 'null-answer-model'),
    'temperature' => (float) env('AI_TEMPERATURE', 0.0),
    'max_tokens' => (int) env('AI_MAX_TOKENS', 800),
    'timeout' => (int) env('AI_TIMEOUT', 30),
    'retry_attempts' => (int) env('AI_RETRY_ATTEMPTS', 2),
    'retry_sleep_ms' => (int) env('AI_RETRY_SLEEP_MS', 250),

    'guardrails' => [
        'answer_only_from_context' => (bool) env('AI_ANSWER_ONLY_FROM_CONTEXT', true),
        'require_citations' => (bool) env('AI_REQUIRE_CITATIONS', true),
        'insufficient_evidence_message' => env(
            'AI_INSUFFICIENT_EVIDENCE_MESSAGE',
            'I do not have enough retrieved evidence to answer that confidently.',
        ),
    ],

    'prompt' => [
        'system' => env('AI_SYSTEM_PROMPT', 'You are a Catholic Bible study assistant. Answer only from the supplied context. Cite sources using bracketed citation numbers like [1]. If evidence is insufficient, say so clearly. Do not fabricate references.'),
        'template' => env('AI_PROMPT_TEMPLATE', "Question:\n{question}\n\nRetrieved context:\n{context}\n\nAnswer with citations:"),
        'token_budget' => (int) env('AI_PROMPT_TOKEN_BUDGET', 4000),
    ],

    'providers' => [
        'openai' => [
            'url' => env('OPENAI_CHAT_URL', 'https://api.openai.com/v1/chat/completions'),
            'api_key' => env('OPENAI_API_KEY', ''),
        ],
        'ollama' => [
            'url' => env('OLLAMA_CHAT_URL', 'http://ollama:11434/api/chat'),
        ],
        'gemini' => [
            'url' => env('GEMINI_CHAT_URL', ''),
            'api_key' => env('GEMINI_API_KEY', ''),
        ],
        'claude' => [
            'url' => env('CLAUDE_CHAT_URL', ''),
            'api_key' => env('ANTHROPIC_API_KEY', ''),
        ],
    ],
];
