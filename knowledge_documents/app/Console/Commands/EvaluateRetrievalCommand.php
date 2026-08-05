<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Knowledge\DTOs\EvaluationDatasetValidationResult;
use App\Application\Knowledge\DTOs\RetrievalEvaluationResult;
use App\Application\Knowledge\Services\RetrievalEvaluationService;
use Illuminate\Console\Command;

final class EvaluateRetrievalCommand extends Command
{
    protected $signature = 'evaluate
                            {--top-k=5 : Number of documents to retrieve for each question}
                            {--minimum-score= : Minimum semantic similarity score}
                            {--question-id= : Evaluate one question UUID}
                            {--category= : Evaluate questions in one category}
                            {--limit= : Limit number of questions}
                            {--save : Persist detailed runs and summary}';

    protected $description = 'Evaluate semantic retrieval against stored Catholic knowledge questions.';

    public function handle(RetrievalEvaluationService $evaluations): int
    {
        $options = $this->evaluationOptions();
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

        $summary = $evaluations->evaluate($options);

        $this->newLine();
        $this->line('Hit@'.$options['topK'].': '.number_format($summary->hitRate * 100, 1).'%');
        $this->line('Precision@'.$options['topK'].': '.number_format($summary->meanPrecision, 3));
        $this->line('Recall@'.$options['topK'].': '.number_format($summary->meanRecall, 3));
        $this->line('MRR: '.number_format($summary->mrr, 3));
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
            'questionId' => $this->option('question-id'),
            'category' => $this->option('category'),
            'limit' => $this->option('limit') === null ? null : (int) $this->option('limit'),
            'save' => (bool) $this->option('save'),
        ];
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
