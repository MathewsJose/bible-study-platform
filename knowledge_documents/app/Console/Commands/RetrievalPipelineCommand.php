<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Knowledge\Retrieval\DTOs\RetrievalContextDocument;
use App\Application\Knowledge\Retrieval\Services\RetrievalEngine;
use Illuminate\Console\Command;

final class RetrievalPipelineCommand extends Command
{
    protected $signature = 'retrieval:pipeline
                            {query : Retrieval query to run}
                            {--profile=ai_answer : Retrieval profile}
                            {--top-k= : Override top K}
                            {--context-limit= : Override context size}';

    protected $description = 'Run the advanced retrieval engine and display pipeline diagnostics.';

    public function handle(RetrievalEngine $retrieval): int
    {
        $result = $retrieval->retrieve(
            query: (string) $this->argument('query'),
            profile: (string) $this->option('profile'),
            topK: $this->option('top-k') === null ? null : (int) $this->option('top-k'),
            contextLimit: $this->option('context-limit') === null ? null : (int) $this->option('context-limit'),
        );

        $this->line('Advanced Retrieval Pipeline');
        $this->line('Profile: '.$result->profile->identifier);
        $this->line('Primary intent: '.$result->query->primaryIntent());
        $this->line('Expansion terms: '.implode(', ', $result->expansion->terms));
        $this->line('Expansion references: '.implode(', ', $result->expansion->references));

        $this->table(
            ['Reference', 'Source Type', 'Score', 'Stages'],
            array_map(
                static fn (RetrievalContextDocument $document): array => [
                    $document->candidate->document->reference,
                    $document->candidate->document->sourceType,
                    $document->candidate->score,
                    implode(', ', $document->candidate->stages),
                ],
                $result->context,
            ),
        );

        $this->line('Timings');
        foreach ($result->diagnostics->timingsMs as $stage => $ms) {
            $this->line("{$stage}: {$ms}ms");
        }

        return self::SUCCESS;
    }
}
