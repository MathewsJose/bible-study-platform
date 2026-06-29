<?php

return [
    'semantic_search' => [
        'limit' => (int) env('SEMANTIC_SEARCH_LIMIT', 10),
        'max_limit' => (int) env('SEMANTIC_SEARCH_MAX_LIMIT', 50),
        'score_threshold' => (float) env('SEMANTIC_SEARCH_SCORE_THRESHOLD', 0.0),
    ],
];
