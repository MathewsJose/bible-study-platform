<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Knowledge\Evaluation\Services\AiEvaluationRunService;
use App\Application\Knowledge\Evaluation\Services\RegressionComparisonService;
use Illuminate\Console\Command;

final class AiModelCompareCommand extends Command
{
    protected $signature = 'ai:model:compare
                            {--models= : Comma-separated provider:model entries}
                            {--type=answer : Evaluation type to run}
                            {--limit=5 : Dataset limit}
                            {--top-k=5 : Retrieval top K}
                            {--strategy=hybrid : Retrieval strategy}
                            {--format=table : table or json}';

    protected $description = 'Run the same AI evaluation dataset against multiple configured LLM provider/model selections.';

    public function handle(AiEvaluationRunService $evaluations, RegressionComparisonService $comparisons): int
    {
        $models = $this->models();

        if (count($models) < 2) {
            $this->error('Provide at least two --models entries, for example local:model-a,openai:gpt-4o-mini.');

            return self::FAILURE;
        }

        $runs = [];
        $originalProfile = config('llm.profiles.model_compare');
        $originalRoute = config('llm.routing.answer_generation');

        try {
            foreach ($models as $model) {
                [$provider, $modelName] = $model;
                config()->set('llm.profiles.model_compare', [
                    'provider' => $provider,
                    'model' => $modelName,
                    'fallback' => 'null_default',
                ]);
                config()->set('llm.routing.answer_generation', 'model_compare');

                $run = $evaluations->run((string) $this->option('type'), [
                    'topK' => (int) $this->option('top-k'),
                    'strategy' => (string) $this->option('strategy'),
                    'limit' => (int) $this->option('limit'),
                    'save' => true,
                    'name' => "model-compare {$provider}:{$modelName}",
                ]);

                $runs[] = $run;
            }
        } finally {
            config()->set('llm.profiles.model_compare', $originalProfile);
            config()->set('llm.routing.answer_generation', $originalRoute);
        }

        $comparison = count($runs) >= 2 && $runs[0]->runId !== null && $runs[1]->runId !== null
            ? $comparisons->compare($runs[0]->runId, $runs[1]->runId)
            : null;

        $payload = [
            'runs' => array_map(static fn ($run): array => $run->toArray(includeResults: false), $runs),
            'comparison' => $comparison?->toArray(),
        ];

        if ($this->option('format') === 'json') {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

            return $comparison?->status === 'FAIL' ? self::FAILURE : self::SUCCESS;
        }

        $this->info('AI Model Comparison');
        $this->table(
            ['Run', 'Type', 'Status', 'Average Score', 'Latency'],
            array_map(static fn ($run): array => [
                $run->runId ?? '-',
                $run->evaluationType,
                $run->status,
                number_format((float) ($run->metrics['average_score'] ?? 0), 3),
                ($run->metrics['average_latency_ms'] ?? 0).' ms',
            ], $runs),
        );

        if ($comparison !== null) {
            $this->line('Regression status: '.$comparison->status);
        }

        return $comparison?->status === 'FAIL' ? self::FAILURE : self::SUCCESS;
    }

    /** @return list<array{0: string, 1: string}> */
    private function models(): array
    {
        return array_values(array_filter(array_map(
            static function (string $entry): ?array {
                $parts = explode(':', trim($entry), 2);

                if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
                    return null;
                }

                return [$parts[0], $parts[1]];
            },
            explode(',', (string) $this->option('models')),
        )));
    }
}
