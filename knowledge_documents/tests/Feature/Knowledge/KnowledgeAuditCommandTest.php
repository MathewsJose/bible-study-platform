<?php

declare(strict_types=1);

namespace Tests\Feature\Knowledge;

use App\Domain\Knowledge\Enums\EmbeddingStatus;
use App\Domain\Knowledge\Enums\SourceType;
use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class KnowledgeAuditCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_command_reports_counts_and_quality(): void
    {
        KnowledgeDocumentRecord::factory()->count(5)->create([
            'source_type' => SourceType::BibleVerse,
            'source_name' => 'Bible A',
            'embedding_status' => EmbeddingStatus::Ready,
            'metadata' => ['source_url' => 'http://example.com', 'license' => 'Public Domain'],
        ]);

        KnowledgeDocumentRecord::factory()->count(3)->create([
            'source_type' => SourceType::Catechism,
            'source_name' => 'Catechism B',
            'embedding_status' => EmbeddingStatus::Pending,
            'metadata' => ['license' => 'Copyright'], // Missing source_url
        ]);

        KnowledgeDocumentRecord::factory()->create([
            'source_type' => SourceType::ChurchFather,
            'source_name' => 'Father C',
            'embedding_status' => EmbeddingStatus::Failed,
            'metadata' => [], // Missing both
        ]);

        $status = Artisan::call('knowledge:audit');
        $output = Artisan::output();

        $this->assertEquals(0, $status);
        $this->assertStringContainsString('Total Documents: 9', $output);
        $this->assertStringContainsString('bible_verse: 5', $output);
        $this->assertStringContainsString('catechism: 3', $output);
        $this->assertStringContainsString('church_father: 1', $output);
        
        $this->assertStringContainsString('Missing source_url: 4', $output);
        $this->assertStringContainsString('Missing license: 1', $output);
        
        $this->assertStringContainsString('Pending Embeddings: 3', $output);
        $this->assertStringContainsString('Failed Embeddings: 1', $output);
        $this->assertStringContainsString('Ready Embeddings: 5', $output);
    }

    public function test_audit_command_with_filters(): void
    {
        KnowledgeDocumentRecord::factory()->count(5)->create([
            'source_name' => 'Target Source',
        ]);
        KnowledgeDocumentRecord::factory()->count(2)->create([
            'source_name' => 'Other Source',
        ]);

        $status = Artisan::call('knowledge:audit', ['--source-name' => 'Target Source']);
        $output = Artisan::output();

        $this->assertEquals(0, $status);
        $this->assertStringContainsString('Total Documents: 5', $output);
        $this->assertStringNotContainsString('Other Source', $output);
    }
}
