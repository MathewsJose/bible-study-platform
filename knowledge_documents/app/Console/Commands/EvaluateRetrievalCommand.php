<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Knowledge\DTOs\EvaluationDatasetValidationResult;
use App\Application\Knowledge\DTOs\RetrievalEvaluationResult;
use App\Application\Knowledge\Services\RetrievalEvaluationService;
use Illuminate\Console\Command;

final class EvaluateRetrievalCommand extends Command
{
    protected $signature = 'evaluate:retrieval
                            {--top-k=5 : Number of documents to retrieve for each question}
                            {--minimum-score= : Minimum semantic similarity score}
                            {--strategy=vector : Retrieval strategy: vector, lexical, or hybrid}
                            {--question-id= : Evaluate one question UUID}
                            {--category= : Evaluate questions in one category}
                            {--limit= : Limit number of questions}
                            {--save : Persist detailed runs and summary}
                            {--compare : Compare vector, lexical, and hybrid strategies}
                            {--weight-grid : Evaluate hybrid weights 0.8/0.2, 0.7/0.3, and 0.6/0.4}';

    /** @var list<string> */
    protected $aliases = ['evaluate'];

    protected $description = 'Evaluate vector, lexical, and hybrid retrieval against stored Catholic knowledge questions.';

    public function handle(RetrievalEvaluationService $evaluations): int
    {
        $options = $this->evaluationOptions();

        if (! in_array($options['strategy'], ['vector', 'lexical', 'hybrid'], true)) {
            $this->error('Invalid retrieval strategy. Use vector, lexical, or hybrid.');

            return self::FAILURE;
        }

        $validation = $evaluations->validateDataset($options);

        $this->displayDatasetValidation($validation);

        if ($validation->totalQuestions === 0) {
            $this->error('No evaluation questions matched the supplied filters.');

            return self::FAILURE;
        }

        if ($validation->validQuestions === 0) {
            $this->error('No valid evaluation questions are available.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Retrieval Evaluation');
        $this->line('Questions: '.$validation->validQuestions);
        $this->line('Top K: '.$options['topK']);

        if ($this->option('compare')) {
            $this->displayComparison($evaluations->compare($options), $options['topK']);

            return $validation->isValid() ? self::SUCCESS : self::FAILURE;
        }

        if ($this->option('weight-grid')) {
            $this->displayComparison($evaluations->weightGrid($options), $options['topK']);

            return $validation->isValid() ? self::SUCCESS : self::FAILURE;
        }

        $summary = $evaluations->evaluate($options);

        $this->newLine();
        $this->line('Hit@'.$options['topK'].': '.number_format($summary->hitRate * 100, 1).'%');
        $this->line('Precision@'.$options['topK'].': '.number_format($summary->meanPrecision, 3));
        $this->line('Recall@'.$options['topK'].': '.number_format($summary->meanRecall, 3));
        $this->line('MRR: '.number_format($summary->mrr, 3));
        $this->line('NDCG@'.$options['topK'].': '.number_format($summary->meanNdcg, 3));
        $this->line('Source coverage: '.number_format($summary->meanSourceCoverage * 100, 1).'%');
        $this->line('Average latency: '.$summary->averageLatencyMs.' ms');

        if ($summary->summaryId !== null) {
            $this->line('Saved summary: '.$summary->summaryId);
        }

        $this->displayFailures($summary->results);

        return $validation->isValid() ? self::SUCCESS : self::FAILURE;
    }

    /** @return array<string, mixed> */
    private function evaluationOptions(): array
    {
        return [
            'topK' => (int) $this->option('top-k'),
            'minimumScore' => $this->option('minimum-score') === null ? null : (float) $this->option('minimum-score'),
            'strategy' => (string) $this->option('strategy'),
            'questionId' => $this->option('question-id'),
            'category' => $this->option('category'),
            'limit' => $this->option('limit') === null ? null : (int) $this->option('limit'),
            'save' => (bool) $this->option('save'),
        ];
    }

    /**
     * @param  array<string, \App\Application\Knowledge\DTOs\RetrievalEvaluationSummary>  $summaries
     */
    private function displayComparison(array $summaries, int $topK): void
    {
        $this->newLine();
        $this->info('Retrieval Strategy Comparison');

        $this->table(
            ['Strategy', "Hit@{$topK}", "Precision@{$topK}", "Recall@{$topK}", 'MRR', "NDCG@{$topK}", 'Source Coverage', 'Avg Latency'],
            array_map(
                static fn (string $strategy, $summary): array => [
                    $strategy,
                    number_format($summary->hitRate * 100, 1).'%',
                    number_format($summary->meanPrecision, 3),
                    number_format($summary->meanRecall, 3),
                    number_format($summary->mrr, 3),
                    number_format($summary->meanNdcg, 3),
                    number_format($summary->meanSourceCoverage * 100, 1).'%',
                    $summary->averageLatencyMs.' ms',
                ],
                array_keys($summaries),
                array_values($summaries),
            ),
        );
    }

    private function displayDatasetValidation(EvaluationDatasetValidationResult $validation): void
    {
        $this->info('Evaluation Dataset Validation');
        $this->line('Questions: '.$validation->totalQuestions);
        $this->line('Valid: '.$validation->validQuestions);
        $this->line('Invalid: '.$validation->invalidQuestions);

        foreach ($validation->missingReferences as $missing) {
            $this->warn('Missing references for '.$missing['question'].': '.implode(', ', $missing['references']));
        }

        foreach ($validation->invalidSourceTypes as $invalid) {
            $this->warn('Invalid source types for '.$invalid['question'].': '.implode(', ', $invalid['source_types']));
        }

        foreach ($validation->duplicateExpectedReferences as $duplicate) {
            $this->warn('Duplicate expected references for '.$duplicate['question'].': '.implode(', ', $duplicate['references']));
        }

        foreach ($validation->questionsWithoutExpectedReferences as $empty) {
            $this->warn('Question has no expected references: '.$empty['question']);
        }
    }

    /**
     * @param  list<RetrievalEvaluationResult>  $results
     */
    private function displayFailures(array $results): void
    {
        $failures = array_values(array_filter(
            $results,
            static fn (RetrievalEvaluationResult $result): bool => ! $result->hit,
        ));

        if ($failures === []) {
            $this->newLine();
            $this->info('All evaluated questions had at least one expected reference in the retrieved results.');

            return;
        }

        $this->newLine();
        $this->warn('Retrieval failures:');

        foreach ($failures as $result) {
            $this->newLine();
            $this->line('Question: '.$result->question->question);
            $this->line('Expected: '.implode(', ', $result->expectedReferences));
            $this->line('Retrieved: '.implode(', ', array_map(
                static fn (array $retrieved): string => (string) $retrieved['reference'],
                $result->retrievedResults,
            )));
            $this->line('Recall: '.number_format($result->recall, 3));
        }
    }
}
