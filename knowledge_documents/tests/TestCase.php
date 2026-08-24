<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('knowledge_sources.sources', [
            [
                'id' => 'bible',
                'type' => 'bible',
                'name' => 'Automated Bible Test Fixture',
                'language' => 'en',
                'license' => 'Test fixture only',
                'copyright_status' => 'verified',
                'verification_status' => 'approved',
                'rights_notes' => 'Synthetic automated test fixture; not a real corpus source.',
                'import_allowed' => true,
            ],
            [
                'id' => 'catechism',
                'type' => 'catechism',
                'name' => 'Automated Catechism Test Fixture',
                'language' => 'en',
                'license' => 'Test fixture only',
                'copyright_status' => 'verified',
                'verification_status' => 'approved',
                'rights_notes' => 'Synthetic automated test fixture; not a real corpus source.',
                'import_allowed' => true,
            ],
            [
                'id' => 'church_fathers',
                'type' => 'church_fathers',
                'name' => 'Automated Church Fathers Test Fixture',
                'language' => 'en',
                'license' => 'Test fixture only',
                'copyright_status' => 'verified',
                'verification_status' => 'approved',
                'rights_notes' => 'Synthetic automated test fixture; not a real corpus source.',
                'import_allowed' => true,
            ],
        ]);
    }
}
