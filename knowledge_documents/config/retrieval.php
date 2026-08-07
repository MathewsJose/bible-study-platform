<?php

declare(strict_types=1);

return [
    'hybrid' => [
        'vector_weight' => (float) env('HYBRID_VECTOR_WEIGHT', 0.70),
        'lexical_weight' => (float) env('HYBRID_LEXICAL_WEIGHT', 0.30),
        'fetch_multiplier' => (int) env('HYBRID_FETCH_MULTIPLIER', 3),
        'minimum_score' => (float) env('HYBRID_MINIMUM_SCORE', 0.0),
    ],

    'engine' => [
        'default_profile' => env('RETRIEVAL_DEFAULT_PROFILE', 'ai_answer'),
        'default_top_k' => (int) env('RETRIEVAL_TOP_K', 10),
        'default_context_limit' => (int) env('RETRIEVAL_CONTEXT_LIMIT', 8),
        'default_token_budget' => (int) env('RETRIEVAL_TOKEN_BUDGET', 2500),
    ],

    'profiles' => [
        'ai_answer' => [
            'top_k' => (int) env('RETRIEVAL_AI_TOP_K', 10),
            'context_limit' => (int) env('RETRIEVAL_AI_CONTEXT_LIMIT', 8),
            'token_budget' => (int) env('RETRIEVAL_AI_TOKEN_BUDGET', 2500),
            'use_vector' => true,
            'use_lexical' => true,
            'use_expansion' => true,
            'graph_depth' => 1,
            'relationship_types' => [],
            'include_explanations' => true,
            'weights' => [
                'vector' => 0.45,
                'lexical' => 0.25,
                'graph' => 0.15,
                'metadata' => 0.10,
                'authority' => 0.05,
            ],
        ],
        'study_mode' => [
            'top_k' => 12,
            'context_limit' => 10,
            'token_budget' => 3500,
            'use_vector' => true,
            'use_lexical' => true,
            'use_expansion' => true,
            'graph_depth' => 2,
            'relationship_types' => [],
            'include_explanations' => true,
            'weights' => [
                'vector' => 0.35,
                'lexical' => 0.25,
                'graph' => 0.25,
                'metadata' => 0.10,
                'authority' => 0.05,
            ],
        ],
        'search' => [
            'top_k' => 10,
            'context_limit' => 10,
            'token_budget' => 2000,
            'use_vector' => true,
            'use_lexical' => true,
            'use_expansion' => false,
            'graph_depth' => 0,
            'relationship_types' => [],
            'include_explanations' => false,
            'weights' => [
                'vector' => 0.50,
                'lexical' => 0.45,
                'graph' => 0.0,
                'metadata' => 0.05,
                'authority' => 0.0,
            ],
        ],
        'cross_references' => [
            'top_k' => 8,
            'context_limit' => 12,
            'token_budget' => 2200,
            'use_vector' => false,
            'use_lexical' => true,
            'use_expansion' => true,
            'graph_depth' => 2,
            'relationship_types' => [],
            'include_explanations' => true,
            'weights' => [
                'vector' => 0.0,
                'lexical' => 0.20,
                'graph' => 0.60,
                'metadata' => 0.15,
                'authority' => 0.05,
            ],
        ],
        'research' => [
            'top_k' => 20,
            'context_limit' => 15,
            'token_budget' => 5000,
            'use_vector' => true,
            'use_lexical' => true,
            'use_expansion' => true,
            'graph_depth' => 2,
            'relationship_types' => [],
            'include_explanations' => true,
            'weights' => [
                'vector' => 0.30,
                'lexical' => 0.30,
                'graph' => 0.25,
                'metadata' => 0.10,
                'authority' => 0.05,
            ],
        ],
    ],

    'expansions' => [
        'incarnation' => [
            'terms' => ['Word became flesh', 'Jesus became man', 'the Word became Flesh'],
            'references' => ['John 1:14', 'CCC 456', 'CCC 457', 'Athanasius'],
        ],
        'grace' => [
            'terms' => ['justification', 'gift of God', 'sanctifying grace'],
            'references' => ['Romans 5:1', 'CCC 1996', 'CCC 1997'],
        ],
        'trinity' => [
            'terms' => ['Father Son Holy Spirit', 'one God three Persons'],
            'references' => ['Matthew 28:19', 'CCC 232', 'CCC 234'],
        ],
    ],
];
