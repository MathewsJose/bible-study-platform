<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Retrieval\Experiments;

use App\Application\Knowledge\DTOs\HybridRankedKnowledgeDocumentData;
use App\Application\Knowledge\DTOs\KnowledgeDocumentData;
use App\Application\Knowledge\DTOs\RankedKnowledgeDocumentData;
use App\Application\Knowledge\Integration\Services\ReferenceResolutionService;
use App\Application\Knowledge\Services\HybridSearchService;
use App\Application\Knowledge\Services\LexicalSearchService;
use App\Application\Knowledge\Services\SemanticSearchService;
use App\Domain\Knowledge\Enums\SourceType;
use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRecord;
use InvalidArgumentException;

final readonly class ScriptureRoutingSearchService
{
    public function __construct(
        private DeterministicScriptureQueryRouter $router,
        private ReferenceResolutionService $references,
        private DoctrinalQueryExpansionService $expansion,
        private SemanticSearchService $semantic,
        private LexicalSearchService $lexical,
        private HybridSearchService $hybrid,
    ) {}

    public function search(string $query, string $mode = 'hybrid_router', int $limit = 10, ?string $sourceName = null): ScriptureRoutingResult
    {
        if (! in_array($mode, $this->modes(), true)) {
            throw new InvalidArgumentException('Unsupported scripture routing mode: '.$mode);
        }

        $classification = $this->router->classify($query);
        $effectiveMode = $this->effectiveMode($mode, $classification);
        $candidateLimit = $limit * max(1, (int) config('retrieval_sprint33.candidate_multiplier', 3));
        $candidates = match ($effectiveMode) {
            'baseline' => $this->baseline($query, $candidateLimit),
            'exact_reference_route' => $this->exactReferenceRoute($query, $classification, $candidateLimit, $sourceName),
            'reference_fusion' => $this->referenceFusion($query, $classification, $candidateLimit, $sourceName),
            'doctrinal_route' => $this->doctrinalRoute($query, $classification, $candidateLimit),
            default => $this->baseline($query, $candidateLimit),
        };

        $ranked = $this->fuse($candidates, $limit);

        return new ScriptureRoutingResult(
            query: $query,
            mode: $mode,
            classification: $classification,
            results: $ranked,
            diagnostics: [
                'effective_mode' => $effectiveMode,
                'config_version' => (string) config('retrieval_sprint33.experiment_version', 'retrieval-sprint-33-v1'),
                'config_fingerprint' => $this->fingerprint(config('retrieval_sprint33')),
                'source_name_override' => $sourceName,
            ],
        );
    }

    /**
     * @return list<string>
     */
    public function modes(): array
    {
        return array_values(array_map('strval', (array) config('retrieval_sprint33.modes', [])));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function baseline(string $query, int $limit): array
    {
        return $this->fromRanked($this->hybrid->search($query, $limit, (float) config('retrieval_sprint33.minimum_score', 0.0)), 'hybrid', 'Baseline hybrid retrieval.');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function exactReferenceRoute(string $query, ScriptureRoutingClassification $classification, int $limit, ?string $sourceName): array
    {
        $candidates = $this->exactCandidates($classification, $sourceName, $classification->route === 'exact_reference' ? 'Exact Scripture reference routed deterministically.' : 'Explicit Scripture reference retained as strong candidate.');

        if ($classification->route !== 'exact_reference') {
            $candidates = [...$candidates, ...$this->baseline($query, $limit)];
        }

        return $candidates;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function referenceFusion(string $query, ScriptureRoutingClassification $classification, int $limit, ?string $sourceName): array
    {
        return [
            ...$this->exactCandidates($classification, $sourceName, 'Explicit Scripture reference fused with semantic and lexical candidates.'),
            ...$this->fromRanked($this->semantic->search($query, $limit, 0.0), 'semantic', 'Semantic candidate for reference-aware fusion.'),
            ...$this->fromRanked($this->lexical->search($query, $limit), 'lexical', 'Lexical candidate for reference-aware fusion.'),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function doctrinalRoute(string $query, ScriptureRoutingClassification $classification, int $limit): array
    {
        $expanded = $classification->references === []
            ? $this->expansion->expand($query, 'combined')->expandedQuery
            : $query;

        return $this->fromRanked($this->hybrid->search($expanded, $limit, 0.0), 'doctrinal', 'Sprint 32 combined expansion for doctrinal semantic routing.');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function exactCandidates(ScriptureRoutingClassification $classification, ?string $sourceName, string $reason): array
    {
        $candidates = [];

        foreach ($classification->references as $reference) {
            $resolved = $this->references->resolve($reference, array_filter([
                'source_type' => SourceType::BibleVerse->value,
                'source_name' => $sourceName,
            ], static fn (?string $value): bool => $value !== null && $value !== ''));

            if ($resolved === null) {
                continue;
            }

            $candidates[] = [
                'document' => new KnowledgeDocumentData(
                    id: $resolved->id,
                    sourceType: $resolved->sourceType,
                    sourceName: $resolved->sourceName,
                    tradition: $resolved->tradition,
                    reference: $resolved->reference,
                    title: $resolved->title,
                    content: $resolved->content,
                    metadata: $resolved->metadata,
                    createdAt: '',
                    updatedAt: '',
                    embeddingStatus: '',
                ),
                'score' => (float) config('retrieval_sprint33.scoring.exact_reference', 1.0),
                'origin' => 'exact_reference',
                'reason' => $reason,
            ];
        }

        return $candidates;
    }

    /**
     * @param  list<RankedKnowledgeDocumentData|HybridRankedKnowledgeDocumentData>  $ranked
     * @return list<array<string, mixed>>
     */
    private function fromRanked(array $ranked, string $origin, string $reason): array
    {
        return array_map(static fn (RankedKnowledgeDocumentData|HybridRankedKnowledgeDocumentData $result): array => [
            'document' => $result->document,
            'score' => $result->score,
            'origin' => $origin,
            'reason' => $reason,
        ], $ranked);
    }

    /**
     * @param  list<array<string, mixed>>  $candidates
     * @return list<ScriptureRoutingCandidate>
     */
    private function fuse(array $candidates, int $limit): array
    {
        $maxByOrigin = [];

        foreach ($candidates as $candidate) {
            $origin = (string) $candidate['origin'];
            $maxByOrigin[$origin] = max($maxByOrigin[$origin] ?? 0.0, (float) $candidate['score']);
        }

        $fused = [];
        foreach ($candidates as $candidate) {
            /** @var KnowledgeDocumentData $document */
            $document = $candidate['document'];
            $origin = (string) $candidate['origin'];
            $normalized = $maxByOrigin[$origin] > 0.0 ? (float) $candidate['score'] / $maxByOrigin[$origin] : 0.0;
            $score = $this->weightedScore($origin, $normalized) * $this->documentTypeWeight($document->sourceType);
            $id = $document->id;

            if (! isset($fused[$id]) || $score > $fused[$id]['score']) {
                $fused[$id] = [
                    'document' => $document,
                    'score' => round($score, 6),
                    'origin' => $origin,
                    'reason' => (string) $candidate['reason'],
                ];
            }
        }

        usort($fused, static function (array $first, array $second): int {
            $score = $second['score'] <=> $first['score'];

            if ($score !== 0) {
                return $score;
            }

            /** @var KnowledgeDocumentData $firstDocument */
            $firstDocument = $first['document'];
            /** @var KnowledgeDocumentData $secondDocument */
            $secondDocument = $second['document'];

            return [$firstDocument->sourceName, $firstDocument->reference] <=> [$secondDocument->sourceName, $secondDocument->reference];
        });

        return array_values(array_map(
            static fn (array $candidate): ScriptureRoutingCandidate => new ScriptureRoutingCandidate(
                id: $candidate['document']->id,
                sourceType: $candidate['document']->sourceType,
                sourceName: $candidate['document']->sourceName,
                reference: $candidate['document']->reference,
                title: $candidate['document']->title,
                score: (float) $candidate['score'],
                retrievalOrigin: (string) $candidate['origin'],
                routingReason: (string) $candidate['reason'],
            ),
            array_slice($fused, 0, $limit),
        ));
    }

    private function effectiveMode(string $mode, ScriptureRoutingClassification $classification): string
    {
        if ($mode !== 'hybrid_router') {
            return $mode;
        }

        return match ($classification->route) {
            'exact_reference' => 'exact_reference_route',
            'reference_contextual' => 'reference_fusion',
            'doctrinal_semantic' => 'doctrinal_route',
            default => 'baseline',
        };
    }

    private function weightedScore(string $origin, float $normalizedScore): float
    {
        return match ($origin) {
            'exact_reference' => (float) config('retrieval_sprint33.scoring.exact_reference_contextual_boost', 0.85),
            'semantic' => $normalizedScore * (float) config('retrieval_sprint33.scoring.semantic_weight', 0.7),
            'lexical' => $normalizedScore * (float) config('retrieval_sprint33.scoring.lexical_weight', 0.3),
            'hybrid', 'doctrinal' => $normalizedScore * (float) config('retrieval_sprint33.scoring.hybrid_weight', 0.9),
            default => $normalizedScore,
        };
    }

    private function documentTypeWeight(string $sourceType): float
    {
        return (float) config("retrieval_sprint33.scoring.document_type_weights.{$sourceType}", 1.0);
    }

    private function fingerprint(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR));
    }
}
