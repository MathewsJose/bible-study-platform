<?php

declare(strict_types=1);

return [
    'gate' => [
        'enabled' => (bool) env('KNOWLEDGE_PROVENANCE_GATE_ENABLED', true),
        'allow_unverified_override' => (bool) env('KNOWLEDGE_PROVENANCE_ALLOW_UNVERIFIED_OVERRIDE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Candidate Source Inventory
    |--------------------------------------------------------------------------
    |
    | These entries describe sources before import. They are deliberately not
    | legal conclusions. Online availability does not imply redistribution
    | rights; leave sources blocked until provenance is verified.
    |
    */
    'sources' => [
        [
            'id' => 'bible.douay_rheims',
            'type' => 'bible',
            'name' => 'Douay-Rheims Bible',
            'language' => 'en',
            'edition' => null,
            'source_url' => null,
            'license_url' => null,
            'license' => null,
            'copyright_status' => 'requires_verification',
            'verification_status' => 'requires_verification',
            'rights_notes' => 'Requires source-specific provenance and redistribution verification before full-corpus import.',
            'expected_document_count' => null,
            'expected_references' => [],
            'import_allowed' => false,
        ],
        [
            'id' => 'catechism.ccc',
            'type' => 'catechism',
            'name' => 'Catechism of the Catholic Church',
            'language' => 'en',
            'edition' => null,
            'source_url' => null,
            'license_url' => null,
            'license' => null,
            'copyright_status' => 'requires_verification',
            'verification_status' => 'requires_verification',
            'rights_notes' => 'Requires manual verification before importing or redistributing a digital CCC source.',
            'expected_document_count' => null,
            'expected_references' => [],
            'import_allowed' => false,
        ],
        [
            'id' => 'church_fathers.public_domain_candidate',
            'type' => 'church_fathers',
            'name' => 'Church Fathers Candidate Source',
            'language' => 'en',
            'edition' => null,
            'source_url' => null,
            'license_url' => null,
            'license' => null,
            'copyright_status' => 'requires_verification',
            'verification_status' => 'requires_verification',
            'rights_notes' => 'Each author, work, translation, and digital transcription requires separate verification.',
            'expected_document_count' => null,
            'expected_references' => [],
            'import_allowed' => false,
        ],
    ],
];
