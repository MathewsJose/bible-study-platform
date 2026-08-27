<?php

declare(strict_types=1);

return [
    'experiment_version' => env('SPRINT33_EXPERIMENT_VERSION', 'retrieval-sprint-33-v1'),
    'modes' => [
        'baseline',
        'exact_reference_route',
        'reference_fusion',
        'doctrinal_route',
        'hybrid_router',
    ],
    'top_k' => [5, 10],
    'minimum_score' => (float) env('SPRINT33_MINIMUM_SCORE', 0.0),
    'candidate_multiplier' => (int) env('SPRINT33_CANDIDATE_MULTIPLIER', 3),
    'canonical_bible_source_name' => env('SPRINT33_CANONICAL_BIBLE_SOURCE_NAME', 'Douay-Rheims Bible'),
    'classification' => [
        'exact_reference_cues' => ['read', 'quote', 'show', 'what does', 'explain', 'say', 'says'],
        'contextual_reference_cues' => ['teach', 'teaches', 'about', 'mean', 'means', 'why', 'doctrine', 'trinity', 'word', 'god', 'divine'],
        'doctrinal_terms' => [
            'baptism',
            'catechism',
            'church',
            'divine',
            'divinity',
            'eucharist',
            'faith',
            'grace',
            'incarnation',
            'mary',
            'salvation',
            'sacrament',
            'trinity',
            'word',
        ],
    ],
    'scoring' => [
        'exact_reference' => 1.0,
        'semantic_weight' => (float) env('SPRINT33_SEMANTIC_WEIGHT', 0.7),
        'lexical_weight' => (float) env('SPRINT33_LEXICAL_WEIGHT', 0.3),
        'hybrid_weight' => (float) env('SPRINT33_HYBRID_WEIGHT', 0.9),
        'exact_reference_contextual_boost' => (float) env('SPRINT33_EXACT_REFERENCE_CONTEXTUAL_BOOST', 0.85),
        'document_type_weights' => [
            'bible_verse' => 1.0,
            'bible_chapter' => 0.94,
            'catechism' => 1.02,
            'church_father' => 1.0,
        ],
    ],
    'john_1_1_diagnostic_queries' => [
        'John 1:1',
        'What does John 1:1 say?',
        'Explain John 1:1.',
        'What does John 1:1 teach about the Word?',
        'Why does John 1:1 teach that the Word was God?',
        'Why do Christians believe that the Word is divine?',
        'What does the Bible teach about the divinity of the Word?',
    ],
    'false_positive_queries' => [
        'What are the three persons of the Trinity?',
        'Why are there 73 books in the Catholic Bible?',
        'What does the first chapter teach about creation?',
        'What happened in year 325?',
        'Explain Book 2 of the Confessions.',
        'What does madeup 1:1 say?',
        'What does John chapter one teach?',
    ],
];
