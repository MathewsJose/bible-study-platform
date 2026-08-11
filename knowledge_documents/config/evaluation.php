<?php

declare(strict_types=1);

return [
    'thresholds' => [
        'minimum_average_score' => (float) env('EVAL_MINIMUM_AVERAGE_SCORE', 0.50),
        'minimum_hit_at_k' => (float) env('EVAL_MINIMUM_HIT_AT_K', 0.50),
        'minimum_mrr' => (float) env('EVAL_MINIMUM_MRR', 0.20),
        'minimum_citation_correctness' => (float) env('EVAL_MINIMUM_CITATION_CORRECTNESS', 0.70),
        'maximum_latency_ms' => (int) env('EVAL_MAXIMUM_LATENCY_MS', 5000),
        'maximum_failure_rate' => (float) env('EVAL_MAXIMUM_FAILURE_RATE', 0.30),
        'maximum_score_drop' => (float) env('EVAL_MAXIMUM_SCORE_DROP', 0.05),
    ],
];
