<?php

declare(strict_types=1);

return [
    'enabled' => (bool) env('AI_SECURITY_ENABLED', true),

    'pii' => [
        'action' => env('AI_PII_ACTION', 'redact'),
    ],

    'prompt_injection' => [
        'action' => env('AI_PROMPT_INJECTION_ACTION', 'block'),
        'threshold' => (int) env('AI_PROMPT_INJECTION_THRESHOLD', 2),
    ],

    'external_processing' => [
        'allow' => (bool) env('AI_ALLOW_EXTERNAL_PROCESSING', false),
        'data_policy' => env('AI_DATA_POLICY', 'local_or_redacted'),
        'local_providers' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('AI_LOCAL_PROVIDERS', 'null,ollama,local')),
        ))),
    ],

    'limits' => [
        'max_input_characters' => (int) env('AI_SECURITY_MAX_INPUT_CHARACTERS', 1000),
        'max_history_message_characters' => (int) env('AI_SECURITY_MAX_HISTORY_MESSAGE_CHARACTERS', 3000),
        'max_retrieval_top_k' => (int) env('AI_SECURITY_MAX_RETRIEVAL_TOP_K', 50),
        'max_agent_steps' => (int) env('AI_SECURITY_MAX_AGENT_STEPS', 8),
        'max_agent_tool_calls' => (int) env('AI_SECURITY_MAX_AGENT_TOOL_CALLS', 8),
        'max_context_documents' => (int) env('AI_SECURITY_MAX_CONTEXT_DOCUMENTS', 50),
        'max_output_tokens' => (int) env('AI_SECURITY_MAX_OUTPUT_TOKENS', 1200),
        'max_mcp_payload_bytes' => (int) env('AI_SECURITY_MAX_MCP_PAYLOAD_BYTES', 32768),
    ],

    'rate_limits' => [
        'answer_per_minute' => (int) env('AI_RATE_LIMIT_ANSWER_PER_MINUTE', 20),
        'agent_per_minute' => (int) env('AI_RATE_LIMIT_AGENT_PER_MINUTE', 10),
        'retrieval_per_minute' => (int) env('AI_RATE_LIMIT_RETRIEVAL_PER_MINUTE', 60),
        'replay_per_minute' => (int) env('AI_RATE_LIMIT_REPLAY_PER_MINUTE', 10),
    ],

    'retention' => [
        'agent_traces_days' => (int) env('AGENT_TRACE_RETENTION_DAYS', 30),
        'replay_records_days' => (int) env('AGENT_REPLAY_RETENTION_DAYS', 30),
        'evaluation_records_days' => env('AI_EVALUATION_RETENTION_DAYS', 'retain'),
    ],

    'tools' => [
        'bible_search' => [
            'permission' => 'READ_KNOWLEDGE',
            'read_only' => true,
            'data_access' => 'public_knowledge',
            'risk' => 'low',
            'requires_authentication' => true,
            'requires_approval' => false,
        ],
        'scripture_reference' => [
            'permission' => 'READ_KNOWLEDGE',
            'read_only' => true,
            'data_access' => 'public_knowledge',
            'risk' => 'low',
            'requires_authentication' => true,
            'requires_approval' => false,
        ],
        'catechism_search' => [
            'permission' => 'READ_KNOWLEDGE',
            'read_only' => true,
            'data_access' => 'public_knowledge',
            'risk' => 'low',
            'requires_authentication' => true,
            'requires_approval' => false,
        ],
        'church_father_search' => [
            'permission' => 'READ_KNOWLEDGE',
            'read_only' => true,
            'data_access' => 'public_knowledge',
            'risk' => 'low',
            'requires_authentication' => true,
            'requires_approval' => false,
        ],
        'knowledge_graph' => [
            'permission' => 'READ_GRAPH',
            'read_only' => true,
            'data_access' => 'public_relationships',
            'risk' => 'low',
            'requires_authentication' => true,
            'requires_approval' => false,
        ],
        'advanced_retrieval' => [
            'permission' => 'READ_RETRIEVAL',
            'read_only' => true,
            'data_access' => 'public_knowledge',
            'risk' => 'medium',
            'requires_authentication' => true,
            'requires_approval' => false,
        ],
        'answer_generation' => [
            'permission' => 'GENERATE_ANSWER',
            'read_only' => true,
            'data_access' => 'public_knowledge_plus_user_question',
            'risk' => 'medium',
            'requires_authentication' => true,
            'requires_approval' => false,
        ],
    ],
];
