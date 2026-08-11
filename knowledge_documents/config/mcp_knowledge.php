<?php

declare(strict_types=1);

return [
    'enabled' => (bool) env('MCP_ENABLED', false),
    'transport' => env('MCP_TRANSPORT', 'http'),
    'protocol_version' => env('MCP_PROTOCOL_VERSION', '2025-06-18'),
    'authentication' => env('MCP_AUTHENTICATION', 'bearer_token'),
    'token' => env('MCP_TOKEN', ''),
    'rate_limit_per_minute' => (int) env('MCP_RATE_LIMIT_PER_MINUTE', 30),
    'route' => env('MCP_ROUTE', 'mcp/knowledge'),
    'server_name' => env('MCP_SERVER_NAME', 'Catholic Bible Knowledge MCP'),
    'server_version' => env('MCP_SERVER_VERSION', '1.0.0'),

    'permissions' => [
        'READ_KNOWLEDGE',
        'READ_GRAPH',
        'READ_RETRIEVAL',
    ],

    'tools' => [
        'allowlist' => array_filter(array_map('trim', explode(',', env(
            'MCP_TOOL_ALLOWLIST',
            'bible_search,scripture_reference,catechism_search,church_father_search,knowledge_graph,advanced_retrieval',
        )))),
        'permissions' => [
            'bible_search' => ['READ_KNOWLEDGE'],
            'scripture_reference' => ['READ_KNOWLEDGE'],
            'catechism_search' => ['READ_KNOWLEDGE'],
            'church_father_search' => ['READ_KNOWLEDGE'],
            'knowledge_graph' => ['READ_GRAPH'],
            'advanced_retrieval' => ['READ_RETRIEVAL'],
        ],
    ],
];
