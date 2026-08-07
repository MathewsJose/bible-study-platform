<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Knowledge\Services\RetrievalDiagnosticsService;
use App\Infrastructure\Knowledge\Persistence\EvaluationQuestionRecord;
use Illuminate\Console\Command;
use Throwable;

final class EvaluateDiagnoseCommand extends Command
{
    protected $signature = 'evaluate:diagnose
                            {--question-id= : Diagnose one evaluation question UUID}
                            {--top-k=5 : Number of results per strategy}
                            {--strategy=all : Retrieval strategy: all, vector, lexical, or hybrid}';

    protected $description = 'Diagnose retrieval results for evaluation questions without changing retrieval behavior.';

    public function handle(RetrievalDiagnosticsService $diagnostics): int
    {
        $strategy = (string) $this->option('strategy');
        $topK = max(1, (int) $this->option('top-k'));
        $questionId = $this->option('question-id') === null ? null : (string) $this->option('question-id');

        if (! in_array($strategy, ['all', 'vector', 'lexical', 'hybrid'], true)) {
            $this->error('Invalid strategy. Use all, vector, lexical, or hybrid.');

            return self::FAILURE;
        }

        $questions = $diagnostics->evaluationQuestions($questionId);

        if ($questions->isEmpty()) {
            $this->error('No evaluation questions matched the supplied filters.');

            return self::FAILURE;
        }

        $this->info('Evaluation Dataset Analysis');
        $coverage = $diagnostics->evaluationCoverage($questionId);
        $this->line('Defined questions: '.$coverage['defined']);
        $this->line('Stored questions: '.$coverage['stored']);
        $this->line('Fully covered: '.$coverage['fully_covered']);
        $this->line('Partially covered: '.$coverage['partially_covered']);
        $this->line('Unavailable: '.$coverage['unavailable']);
        $this->line('Evaluable: '.$coverage['evaluable']);

        foreach ($diagnostics->evaluationDataset($questionId) as $question) {
            $this->newLine();
            $this->line('Question ID: '.$question['question_id']);
            $this->line('Question: '.$question['question']);
            $this->line('Category: '.($question['category'] ?? 'none'));
            $this->line('Coverage: '.$question['coverage_status']);
            $this->line('Intended references: '.$this->csv($question['intended_references']));
            $this->line('Available/evaluable references: '.$this->csv($question['expected_references']));
            $this->line('Missing references: '.$this->csv($question['missing_references']));
            $this->line('Expected source types: '.$this->csv($question['expected_source_types']));
            $this->table(
                ['Reference', 'Exists', 'Source Type', 'Source Name', 'Content Length'],
                array_map(static fn (array $document): array => [
                    $document['reference'],
                    $document['exists'] ? 'yes' : 'no',
                    $document['source_type'] ?? 'missing',
                    $document['source_name'] ?? 'missing',
                    $document['content_length'] ?? 'missing',
                ], $question['expected_documents']),
            );
        }

        foreach ($questions as $question) {
            if (($question->expected_references ?? []) === []) {
                $this->newLine(2);
                $this->warn('Skipping retrieval diagnostics for unavailable question: '.$question->question);

                continue;
            }

            $this->displayQuestionDiagnostics($diagnostics, $question, $topK, $strategy);
        }

        return self::SUCCESS;
    }

    private function displayQuestionDiagnostics(RetrievalDiagnosticsService $diagnostics, EvaluationQuestionRecord $question, int $topK, string $strategy): void
    {
        $this->newLine(2);
        $this->info('Question: '.$question->question);
        $this->line('Category: '.($question->category ?? 'none'));
        $this->line('Expected: '.$this->csv($question->expected_references ?? []));

        try {
            $queryEmbedding = $diagnostics->queryEmbeddingStats($question->question);
            $results = $diagnostics->retrievalResults($question, $topK, $strategy);
            $ranges = $diagnostics->scoreRanges($question, $topK);
        } catch (Throwable $exception) {
            $this->error('Retrieval failed for question '.$question->id.': '.$exception->getMessage());

            return;
        }

        $this->line('Query embedding provider: '.$queryEmbedding['provider']);
        $this->line('Query embedding model: '.$queryEmbedding['configured_model']);
        $this->line('Query embedding dimensions: '.$queryEmbedding['actual_dimensions'].' / configured '.$queryEmbedding['configured_dimensions']);

        foreach ($results as $strategyName => $strategyResults) {
            $this->newLine();
            $this->info(strtoupper($strategyName));
            $this->table(
                ['Rank', 'Reference', 'Source Type', 'Source Name', 'Similarity', 'Lexical', 'Combined', 'Expected'],
                array_map(static fn (array $result): array => [
                    $result['rank'],
                    $result['reference'],
                    $result['source_type'],
                    $result['source_name'],
                    $result['similarity_score'] === null ? '-' : number_format((float) $result['similarity_score'], 6),
                    $result['lexical_score'] === null ? '-' : number_format((float) $result['lexical_score'], 6),
                    $result['combined_score'] === null ? '-' : number_format((float) $result['combined_score'], 6),
                    $result['expected'] ? 'YES' : 'NO',
                ], $strategyResults),
            );

            $hit = collect($strategyResults)->contains(static fn (array $result): bool => (bool) $result['expected']);
            $this->line('Expected hit: '.($hit ? 'YES' : 'NO'));
        }

        $this->newLine();
        $this->info('Score Ranges');
        $this->table(
            ['Score', 'Min', 'Max'],
            array_map(static fn (string $name, array $range): array => [
                $name,
                $range['min'] === null ? '-' : number_format((float) $range['min'], 6),
                $range['max'] === null ? '-' : number_format((float) $range['max'], 6),
            ], array_keys($ranges), array_values($ranges)),
        );
    }

    /**
     * @param  array<int, mixed>  $values
     */
    private function csv(array $values): string
    {
        $strings = array_values(array_filter(array_map(static fn (mixed $value): string => (string) $value, $values)));

        return $strings === [] ? 'none' : implode(', ', $strings);
    }
}
