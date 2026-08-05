<?php

declare(strict_types=1);

return [
    'hybrid' => [
        'vector_weight' => (float) env('HYBRID_VECTOR_WEIGHT', 0.70),
        'lexical_weight' => (float) env('HYBRID_LEXICAL_WEIGHT', 0.30),
        'fetch_multiplier' => (int) env('HYBRID_FETCH_MULTIPLIER', 3),
        'minimum_score' => (float) env('HYBRID_MINIMUM_SCORE', 0.0),
    ],
];
