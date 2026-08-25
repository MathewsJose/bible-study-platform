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
            'name' => 'Original Douay-Rheims Bible',
            'language' => 'en',
            'edition' => '1582 New Testament / 1609-1610 Old Testament JSON dataset',
            'source_version' => 'git:0bf4218b9b46b5b00d29a703b5b74226051b97a5',
            'source_url' => 'https://github.com/janvier-s/original-douay-rheims',
            'license_url' => 'https://creativecommons.org/publicdomain/zero/1.0/',
            'license' => 'CC0 1.0 Universal public domain dedication claimed by source publisher',
            'copyright_status' => 'public_domain',
            'verification_status' => 'approved',
            'rights_notes' => 'Manually approved by the project owner on 2026-08-25 for use in this project based on the pinned source repository, claimed CC0 1.0 Universal dedication, source checksum f72f81c450096401b59d3f7d08bee054690411b5d18f0e922223d6192fee14e4, and normalized corpus checksum 864d1e7aeb06f855d64124ae8353aa62184276f28349a0b0fe759c19df306738. Approval records provenance for controlled import preparation and is not independent legal advice.',
            'expected_document_count' => 35809,
            'expected_references' => [],
            'import_allowed' => true,
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
