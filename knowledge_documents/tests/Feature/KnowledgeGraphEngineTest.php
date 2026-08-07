<?php

declare(strict_types=1);

use App\Application\Knowledge\Graph\Contracts\KnowledgeGraphRepositoryInterface;
use App\Application\Knowledge\Graph\Services\RelatedDocumentsService;
use App\Domain\Knowledge\Enums\RelationshipType;
use App\Domain\Knowledge\Enums\SourceType;
use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRecord;
use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRelationshipRecord;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

function sprintTwelveSourceName(): string
{
    return 'Sprint 12 Graph Test Corpus';
}

beforeEach(function (): void {
    KnowledgeDocumentRecord::query()
        ->where('source_name', sprintTwelveSourceName())
        ->delete();
});

it('builds explicit scripture catechism and church father relationships without duplicates', function (): void {
    $john = KnowledgeDocumentRecord::factory()->create([
        'source_type' => SourceType::BibleVerse->value,
        'source_name' => sprintTwelveSourceName(),
        'reference' => 'Zephaniah 99:99',
        'title' => 'The Word became flesh',
    ]);
    $ccc457 = KnowledgeDocumentRecord::factory()->create([
        'source_type' => SourceType::Catechism->value,
        'source_name' => sprintTwelveSourceName(),
        'reference' => 'CCC 912457',
    ]);
    $athanasius = KnowledgeDocumentRecord::factory()->create([
        'source_type' => SourceType::ChurchFather->value,
        'source_name' => sprintTwelveSourceName(),
        'reference' => 'Sprint 12 Father, Test Work, Section 8',
    ]);
    $ccc456 = KnowledgeDocumentRecord::factory()->create([
        'source_type' => SourceType::Catechism->value,
        'source_name' => sprintTwelveSourceName(),
        'reference' => 'CCC 912456',
        'metadata' => [
            'scripture_references' => ['Zephaniah 99:99'],
            'internal_references' => ['CCC 912457'],
            'church_father_references' => ['Sprint 12 Father, Test Work, Section 8'],
        ],
    ]);

    expect(Artisan::call('graph:update'))->toBe(Command::SUCCESS)
        ->and(KnowledgeDocumentRelationshipRecord::query()->where('source_document_id', $ccc456->id)->count())->toBe(3)
        ->and(KnowledgeDocumentRelationshipRecord::query()->where('source_document_id', $ccc456->id)->where('target_document_id', $john->id)->where('relationship_type', RelationshipType::ScriptureReference->value)->exists())->toBeTrue()
        ->and(KnowledgeDocumentRelationshipRecord::query()->where('source_document_id', $ccc456->id)->where('target_document_id', $ccc457->id)->where('relationship_type', RelationshipType::CatechismReference->value)->exists())->toBeTrue()
        ->and(KnowledgeDocumentRelationshipRecord::query()->where('source_document_id', $ccc456->id)->where('target_document_id', $athanasius->id)->where('relationship_type', RelationshipType::ChurchFatherReference->value)->exists())->toBeTrue();

    expect(Artisan::call('graph:update'))->toBe(Command::SUCCESS)
        ->and(KnowledgeDocumentRelationshipRecord::query()->where('source_document_id', $ccc456->id)->count())->toBe(3);
});

it('updates one document incrementally and removes stale outgoing relationships', function (): void {
    $john = KnowledgeDocumentRecord::factory()->create([
        'source_type' => SourceType::BibleVerse->value,
        'source_name' => sprintTwelveSourceName(),
        'reference' => 'Zephaniah 99:99',
    ]);
    $romans = KnowledgeDocumentRecord::factory()->create([
        'source_type' => SourceType::BibleVerse->value,
        'source_name' => sprintTwelveSourceName(),
        'reference' => 'Romans 99:99',
    ]);
    $ccc = KnowledgeDocumentRecord::factory()->create([
        'source_type' => SourceType::Catechism->value,
        'source_name' => sprintTwelveSourceName(),
        'reference' => 'CCC 912456',
        'metadata' => ['scripture_references' => ['Zephaniah 99:99']],
    ]);

    Artisan::call('graph:update');
    expect(KnowledgeDocumentRelationshipRecord::query()->where('target_document_id', $john->id)->exists())->toBeTrue();

    $ccc->update(['metadata' => ['scripture_references' => ['Romans 99:99']]]);

    expect(Artisan::call('graph:update', ['--document-id' => $ccc->id]))->toBe(Command::SUCCESS)
        ->and(KnowledgeDocumentRelationshipRecord::query()->where('source_document_id', $ccc->id)->count())->toBe(1)
        ->and(KnowledgeDocumentRelationshipRecord::query()->where('target_document_id', $john->id)->exists())->toBeFalse()
        ->and(KnowledgeDocumentRelationshipRecord::query()->where('target_document_id', $romans->id)->exists())->toBeTrue();
});

it('traverses two hops with cycle protection', function (): void {
    $first = KnowledgeDocumentRecord::factory()->create(['source_name' => sprintTwelveSourceName(), 'reference' => 'CCC Graph 1']);
    $second = KnowledgeDocumentRecord::factory()->create(['source_name' => sprintTwelveSourceName(), 'reference' => 'CCC Graph 2']);
    $third = KnowledgeDocumentRecord::factory()->create(['source_name' => sprintTwelveSourceName(), 'reference' => 'CCC Graph 3']);

    KnowledgeDocumentRelationshipRecord::query()->create([
        'source_document_id' => $first->id,
        'target_document_id' => $second->id,
        'relationship_type' => RelationshipType::CatechismReference->value,
    ]);
    KnowledgeDocumentRelationshipRecord::query()->create([
        'source_document_id' => $second->id,
        'target_document_id' => $third->id,
        'relationship_type' => RelationshipType::CatechismReference->value,
    ]);
    KnowledgeDocumentRelationshipRecord::query()->create([
        'source_document_id' => $third->id,
        'target_document_id' => $first->id,
        'relationship_type' => RelationshipType::CatechismReference->value,
    ]);

    $relationships = app(KnowledgeGraphRepositoryInterface::class)->traverse(
        $first->id,
        depth: 2,
        relationshipTypes: [RelationshipType::CatechismReference->value],
    );

    expect($relationships)->toHaveCount(3);
});

it('groups related documents by relationship type', function (): void {
    $ccc = KnowledgeDocumentRecord::factory()->create(['source_name' => sprintTwelveSourceName(), 'reference' => 'CCC 912456']);
    $john = KnowledgeDocumentRecord::factory()->create([
        'source_type' => SourceType::BibleVerse->value,
        'source_name' => sprintTwelveSourceName(),
        'reference' => 'Zephaniah 99:99',
        'title' => 'The Word became flesh',
    ]);

    KnowledgeDocumentRelationshipRecord::query()->create([
        'source_document_id' => $ccc->id,
        'target_document_id' => $john->id,
        'relationship_type' => RelationshipType::ScriptureReference->value,
    ]);

    $grouped = app(RelatedDocumentsService::class)->groupedForDocument($ccc->id);

    expect($grouped[RelationshipType::ScriptureReference->value][0])
        ->toMatchArray([
            'id' => $john->id,
            'reference' => 'Zephaniah 99:99',
            'title' => 'The Word became flesh',
            'source_type' => SourceType::BibleVerse->value,
            'score' => 1.0,
        ]);
});

it('reports graph diagnostics through cli commands and knowledge status', function (): void {
    $john = KnowledgeDocumentRecord::factory()->create([
        'source_type' => SourceType::BibleVerse->value,
        'source_name' => sprintTwelveSourceName(),
        'reference' => 'Zephaniah 99:99',
    ]);
    $ccc = KnowledgeDocumentRecord::factory()->create([
        'source_type' => SourceType::Catechism->value,
        'source_name' => sprintTwelveSourceName(),
        'reference' => 'CCC 912456',
        'metadata' => ['scripture_references' => ['Zephaniah 99:99']],
    ]);

    expect(Artisan::call('graph:rebuild', ['--document-id' => $ccc->id]))->toBe(Command::SUCCESS);
    $rebuildOutput = Artisan::output();
    expect($rebuildOutput)->toContain('relationships created: 1');

    expect(Artisan::call('graph:verify'))->toBe(Command::SUCCESS);
    $verifyOutput = Artisan::output();
    expect($verifyOutput)->toContain('Knowledge Graph Diagnostics')
        ->and($verifyOutput)->toContain('Total graph edges: 1')
        ->and($verifyOutput)->toContain(RelationshipType::ScriptureReference->value);

    expect(Artisan::call('knowledge:status'))->toBe(Command::SUCCESS);
    $statusOutput = Artisan::output();
    expect($statusOutput)->toContain('Knowledge Graph Status')
        ->and($statusOutput)->toContain('Total graph edges: 1');

    expect($john->exists)->toBeTrue();
});
