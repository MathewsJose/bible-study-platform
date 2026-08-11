<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Knowledge\Answering\Services\LlmProviderHealthService;
use Illuminate\Console\Command;

final class AiProvidersHealthCommand extends Command
{
    protected $signature = 'ai:providers:health {--format=table : table or json}';

    protected $description = 'Show configured LLM provider health without exposing secrets.';

    public function handle(LlmProviderHealthService $health): int
    {
        $results = $health->check();

        if ($this->option('format') === 'json') {
            $this->line(json_encode(array_map(static fn ($result): array => $result->toArray(), $results), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        }

        $this->info('LLM Provider Health');
        $this->table(
            ['Provider', 'Status', 'Model', 'Connectivity', 'Security policy'],
            array_map(static fn ($result): array => [
                $result->provider,
                $result->status,
                $result->model ?? '-',
                $result->connectivity,
                $result->securityPolicy,
            ], $results),
        );

        return self::SUCCESS;
    }
}
