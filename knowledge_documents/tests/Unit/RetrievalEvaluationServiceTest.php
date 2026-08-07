<?php

declare(strict_types=1);

use App\Application\Knowledge\Contracts\EmbeddingProviderInterface;
use App\Application\Knowledge\Contracts\EmbeddingRepositoryInterface;
use App\Application\Knowledge\Contracts\KnowledgeDocumentRepositoryInterface;
use App\Application\Knowledge\Services\EmbeddingVectorValidator;
use App\Application\Knowledge\Services\HybridSearchService;
use App\Application\Knowledge\Services\LexicalSearchService;
use App\Application\Knowledge\Services\RetrievalEvaluationService;
use App\Application\Knowledge\Services\SemanticSearchService;
use App\Application\Knowledge\Services\WeightedScoreFusionStrategy;
use App\Infrastructure\Knowledge\Persistence\EvaluationQuestionRecord;
use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class, RefreshDatabase::class);

final class EvaluationEmbeddingProvider implements EmbeddingProviderInterface
{
    public function embed(string $text): array
    {
        return [1.0, 0.0, 0.0];
    }

    public function embedMany(array $texts): array
    {
        return array_map(fn (): array => [1.0, 0.0, 0.0], $texts);
    }

    public function identifier(): string
    {
        return 'evaluation-test-model';
    }
}

final class EvaluationEmbeddingRepository implements EmbeddingRepositoryInterface
{
    /** @var list<array{record: KnowledgeDocumentRecord, score: float}> */
    public array $results = [];

    public function idsNeedingEmbeddings(?int $limit = null, bool $force = false, bool $retryFailed = false, ?string $documentId = null, ?string $sourceType = null, ?string $sourceName = null): array
    {
        return [];
    }

    public function documentsForEmbedding(array $ids): Collection
    {
        return new Collection();
    }

    public function storeEmbedding(string $documentId, array $embedding, string $provider, string $model, int $dimensions): void {}

    public function markEmbeddingFailed(string $documentId, string $error): void {}

    public function summarizeEmbeddingStatus(array $ids): array
    {
        return ['processed' => 0, 'generated' => 0, 'failures' => 0];
    }

    public function semanticSearch(array $embedding, int $topK, float $threshold, array $filters = []): array
    {
        return array_slice($this->results, 0, $topK);
    }
}

function evaluationServiceWithResults(array $results): RetrievalEvaluationService
{
    config()->set('embeddings.dimensions', 3);
    config()->set('retrieval.hybrid.vector_weight', 0.7);
    config()->set('retrieval.hybrid.lexical_weight', 0.3);

    $embeddingRepository = new EvaluationEmbeddingRepository();
    $embeddingRepository->results = $results;
    $embeddingProvider = new EvaluationEmbeddingProvider();
    $semanticSearch = new SemanticSearchService(
        $embeddingProvider,
        $embeddingRepository,
        new EmbeddingVectorValidator(),
    );

    $documentRepository = \Mockery::mock(KnowledgeDocumentRepositoryInterface::class);
    $documentRepository->shouldReceive('fullTextSearch')->byDefault()->andReturn($results);

    $lexicalSearch = new LexicalSearchService($documentRepository);

    return new RetrievalEvaluationService(
        $semanticSearch,
        $lexicalSearch,
        new HybridSearchService($lexicalSearch, $semanticSearch, new WeightedScoreFusionStrategy()),
    );
}

it('calculates hit precision recall and reciprocal rank for partial results', function (): void {
    $ccc457 = KnowledgeDocumentRecord::factory()->create(['reference' => 'CCC 457']);
    $john114 = KnowledgeDocumentRecord::factory()->create(['reference' => 'John 1:14']);
    $ccc999 = KnowledgeDocumentRecord::factory()->create(['reference' => 'CCC 999']);

    $question = EvaluationQuestionRecord::factory()->create([
        'expected_references' => ['CCC 456', 'CCC 457', 'John 1:14'],
    ]);

    $service = evaluationServiceWithResults([
        ['record' => $ccc999, 'score' => 0.99],
        ['record' => $ccc457, 'score' => 0.95],
        ['record' => $john114, 'score' => 0.90],
    ]);

    $result = $service->evaluateQuestion($question, 3, 0.0);

    expect($result->hit)->toBeTrue()
        ->and($result->precision)->toBe(0.666667)
        ->and($result->recall)->toBe(0.666667)
        ->and($result->reciprocalRank)->toBe(0.5);
});

it('handles no relevant results all relevant results and top k larger than available results', function (): void {
    $ccc457 = KnowledgeDocumentRecord::factory()->create(['reference' => 'CCC 457']);
    $ccc458 = KnowledgeDocumentRecord::factory()->create(['reference' => 'CCC 458']);
    $john114 = KnowledgeDocumentRecord::factory()->create(['reference' => 'John 1:14']);

    $question = EvaluationQuestionRecord::factory()->create([
        'expected_references' => ['CCC 457', 'CCC 458'],
    ]);

    $noRelevant = evaluationServiceWithResults([
        ['record' => $john114, 'score' => 0.9],
    ])->evaluateQuestion($question, 10, 0.0);

    $allRelevant = evaluationServiceWithResults([
        ['record' => $ccc457, 'score' => 0.9],
        ['record' => $ccc458, 'score' => 0.8],
    ])->evaluateQuestion($question, 10, 0.0);

    expect($noRelevant->hit)->toBeFalse()
        ->and($noRelevant->precision)->toBe(0.0)
        ->and($noRelevant->recall)->toBe(0.0)
        ->and($noRelevant->reciprocalRank)->toBe(0.0)
        ->and($allRelevant->hit)->toBeTrue()
        ->and($allRelevant->precision)->toBe(1.0)
        ->and($allRelevant->recall)->toBe(1.0)
        ->and($allRelevant->reciprocalRank)->toBe(1.0);
});

it('reports validation problems for duplicate empty and missing expected references', function (): void {
    KnowledgeDocumentRecord::factory()->create(['reference' => 'CCC 457']);

    EvaluationQuestionRecord::factory()->create([
        'question' => 'Duplicate question',
        'expected_references' => ['CCC 457', 'CCC 457', 'CCC 999'],
        'expected_source_types' => ['catechism', 'invalid'],
    ]);

    EvaluationQuestionRecord::factory()->create([
        'question' => 'Empty question',
        'expected_references' => [],
        'expected_source_types' => ['catechism'],
    ]);

    $validation = evaluationServiceWithResults([])->validateDataset();

    expect($validation->totalQuestions)->toBe(2)
        ->and($validation->validQuestions)->toBe(0)
        ->and($validation->invalidQuestions)->toBe(1)
        ->and($validation->missingReferences[0]['references'])->toBe(['CCC 999'])
        ->and($validation->invalidSourceTypes[0]['source_types'])->toBe(['invalid'])
        ->and($validation->duplicateExpectedReferences[0]['references'])->toBe(['CCC 457'])
        ->and($validation->questionsWithoutExpectedReferences[0]['question'])->toBe('Empty question');
});

it('summarizes mean metrics and persists runs when requested', function (): void {
    $ccc457 = KnowledgeDocumentRecord::factory()->create(['reference' => 'CCC 457']);
    $john114 = KnowledgeDocumentRecord::factory()->create(['reference' => 'John 1:14']);

    EvaluationQuestionRecord::factory()->create([
        'question' => 'Hit question',
        'expected_references' => ['CCC 457'],
    ]);
    EvaluationQuestionRecord::factory()->create([
        'question' => 'Miss question',
        'expected_references' => ['John 1:14'],
    ]);

    $service = evaluationServiceWithResults([
        ['record' => $ccc457, 'score' => 0.9],
        ['record' => $john114, 'score' => 0.8],
    ]);

    $summary = $service->evaluate(['topK' => 1, 'minimumScore' => 0.0, 'save' => true]);

    expect($summary->summaryId)->not->toBeNull()
        ->and($summary->totalQuestions)->toBe(2)
        ->and($summary->hitRate)->toBe(0.5)
        ->and($summary->meanPrecision)->toBe(0.5)
        ->and($summary->meanRecall)->toBe(0.5)
        ->and($summary->mrr)->toBe(0.5)
        ->and(DB::table('retrieval_evaluation_runs')->count())->toBe(2)
        ->and(DB::table('retrieval_evaluation_runs')->value('retrieval_strategy'))->toBe('vector')
        ->and(DB::table('retrieval_evaluation_summaries')->count())->toBe(1);
});

it('compares vector lexical and hybrid retrieval strategies', function (): void {
    $ccc457 = KnowledgeDocumentRecord::factory()->create(['reference' => 'CCC 457']);

    EvaluationQuestionRecord::factory()->create([
        'question' => 'Why did Jesus become man?',
        'expected_references' => ['CCC 457'],
    ]);

    $summaries = evaluationServiceWithResults([
        ['record' => $ccc457, 'score' => 0.9],
    ])->compare(['topK' => 1, 'minimumScore' => 0.0]);

    expect(array_keys($summaries))->toBe(['vector', 'lexical', 'hybrid'])
        ->and($summaries['vector']->hitRate)->toBe(1.0)
        ->and($summaries['lexical']->hitRate)->toBe(1.0)
        ->and($summaries['hybrid']->configuration['retrieval'])->toBe('hybrid');
});
