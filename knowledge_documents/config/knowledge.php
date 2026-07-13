<?php

return [
    'semantic_search' => [
        'limit' => (int) env('SEMANTIC_SEARCH_LIMIT', 10),
        'max_limit' => (int) env('SEMANTIC_SEARCH_MAX_LIMIT', 50),
        'score_threshold' => (float) env('SEMANTIC_SEARCH_SCORE_THRESHOLD', 0.0),
    ],
    'import' => [
        'directories' => array_filter(array_map('trim', explode(',', env('KNOWLEDGE_IMPORT_DIRECTORIES', 'storage/app/imports')))),
    ],
    'hybrid_search' => [
        'weights' => [
            'semantic' => (float) env('HYBRID_SEARCH_SEMANTIC_WEIGHT', 0.65),
            'full_text' => (float) env('HYBRID_SEARCH_FULL_TEXT_WEIGHT', 0.25),
            'source_priority' => (float) env('HYBRID_SEARCH_SOURCE_PRIORITY_WEIGHT', 0.10),
        ],
        'priority_sources' => array_filter(array_map('trim', explode(',', env('HYBRID_SEARCH_PRIORITY_SOURCES', 'Douay-Rheims Bible')))),
    ],
];
