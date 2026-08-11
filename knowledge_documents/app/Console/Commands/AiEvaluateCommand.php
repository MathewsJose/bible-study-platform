<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Knowledge\Evaluation\Services\AiEvaluationRunService;
use Illuminate\Console\Command;

final class AiEvaluateCommand extends Command
{
    protected $signature = 'ai:evaluate
                            {--type=retrieval : retrieval, answer, agent, safety, or all}
                            {--all : Run all evaluation types}
                            {--top-k=5 : Retrieval top K}
                            {--strategy=vector : Retrieval strategy}
                            {--category= : Filter by dataset category}
                            {--difficulty= : Filter by easy, medium, or hard}
                            {--limit= : Limit dataset questions}
                            {--save : Persist run and per-question results}
                            {--name=ai-evaluation : Evaluation run name}
                            {--format=table : table or json}';

    protected $description = 'Run production AI evaluation across retrieval, answer, agent, and safety layers.';

    public function handle(AiEvaluationRunService $evaluations): int
    {
        $type = (bool) $this->option('all') ? 'all' : (string) $this->option('type');

        if (! in_array($type, ['retrieval', 'answer', 'agent', 'safety', 'all'], true)) {
            $this->error('Invalid type. Use retrieval, answer, agent, safety, or all.');

            return self::FAILURE;
        }

        $run = $evaluations->run($type, [
            'topK' => (int) $this->option('top-k'),
            'strategy' => (string) $this->option('strategy'),
            'category' => $this->option('category'),
            'difficulty' => $this->option('difficulty'),
            'limit' => $this->option('limit') === null ? null : (int) $this->option('limit'),
            'save' => (bool) $this->option('save'),
            'name' => (string) $this->option('name'),
        ]);

        if ($this->option('format') === 'json') {
            $this->line(json_encode($run->toArray(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

            return $run->status === 'failed' ? self::FAILURE : self::SUCCESS;
        }

        $this->info('AI Evaluation');
        $this->line('Run: '.($run->runId ?? 'not saved'));
        $this->line('Type: '.$run->evaluationType);
        $this->line('Status: '.$run->status);
        $this->line('Total: '.$run->metrics['total']);
        $this->line('Average score: '.number_format((float) $run->metrics['average_score'], 3));
        $this->line('Average latency: '.$run->metrics['average_latency_ms'].' ms');

        $this->table(
            ['Type', 'Category', 'Difficulty', 'Status', 'Score', 'Latency'],
            array_map(static fn ($result): array => [
                $result->evaluationType,
                $result->category ?? '-',
                $result->difficulty ?? '-',
                $result->status,
                number_format($result->score, 3),
                $result->latencyMs.' ms',
            ], $run->results),
        );

        return $run->status === 'failed' ? self::FAILURE : self::SUCCESS;
    }
}
