<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Knowledge\Answering\Services\LlmProviderHealthService;
use Illuminate\Console\Command;

final class AiProvidersCommand extends Command
{
    protected $signature = 'ai:providers {--format=table : table or json}';

    protected $description = 'List configured LLM providers and their safe availability status.';

    public function handle(LlmProviderHealthService $health): int
    {
        $results = $health->check();

        if ($this->option('format') === 'json') {
            $this->line(json_encode(array_map(static fn ($result): array => $result->toArray(), $results), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        }

        $this->info('LLM Providers');
        $this->table(
            ['Provider', 'Enabled', 'External', 'Status', 'Model'],
            array_map(static function ($result): array {
                $config = (array) config("llm.providers.{$result->provider}", []);
                $category = (string) ($config['category'] ?? '');

                return [
                    $result->provider,
                    ($config['enabled'] ?? true) === false ? 'no' : 'yes',
                    in_array($category, ['local'], true) ? 'no' : 'yes',
                    $result->status,
                    $result->model ?? '-',
                ];
            }, $results),
        );

        return self::SUCCESS;
    }
}
