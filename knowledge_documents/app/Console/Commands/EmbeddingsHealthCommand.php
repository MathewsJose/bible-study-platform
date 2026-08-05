<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Knowledge\Services\EmbeddingProviderHealthService;
use Illuminate\Console\Command;

final class EmbeddingsHealthCommand extends Command
{
    protected $signature = 'embeddings:health';

    protected $description = 'Verify embedding provider configuration, test embedding generation, and database embedding coverage.';

    public function handle(EmbeddingProviderHealthService $health): int
    {
        $result = $health->check();
        $database = $result['database'];

        $this->info('Embedding Provider Health');
        $this->line('Provider: '.$result['provider']);
        $this->line('Provider Identifier: '.$result['provider_identifier']);
        $this->line('Model: '.$result['model']);
        $this->line('Dimensions: '.$result['dimensions']);
        $this->line('API Key: '.$result['api_key_status']);
        $this->line('API Connection: '.($result['api_connection_ok'] ? 'OK' : 'FAILED'));
        if ($result['provider'] === 'local') {
            $this->line('Model loaded: '.($result['api_connection_ok'] ? 'YES' : 'NO'));
        }

        if ($result['actual_test_dimensions'] !== null) {
            $this->line('Test Embedding Dimensions: '.$result['actual_test_dimensions']);
        }

        if ($result['message'] !== null) {
            $this->warn('Message: '.$result['message']);
        }

        $this->newLine();
        $this->info('Database Embeddings');
        $this->line('Total: '.$database['total']);
        $this->line('With embeddings: '.$database['with_embeddings']);
        $this->line('Without embeddings: '.$database['without_embeddings']);
        $this->line('Coverage: '.number_format((float) $database['coverage'] * 100, 2).'%');
        $this->line('Configured dimensions: '.$database['configured_dimensions']);
        $this->line('Actual dimensions: '.$this->csv($database['actual_dimensions']));

        $this->table(
            ['Embedding Provider', 'Count'],
            array_map(
                static fn (string $provider, int $count): array => [$provider, $count],
                array_keys($database['providers']),
                array_values($database['providers']),
            ),
        );

        $this->table(
            ['Embedding Model', 'Count'],
            array_map(
                static fn (string $model, int $count): array => [$model, $count],
                array_keys($database['models']),
                array_values($database['models']),
            ),
        );

        $this->table(
            ['Stored Dimensions', 'Count'],
            array_map(
                static fn (string $dimensions, int $count): array => [$dimensions, $count],
                array_keys($database['stored_dimensions']),
                array_values($database['stored_dimensions']),
            ),
        );

        return $result['api_connection_ok'] ? self::SUCCESS : self::FAILURE;
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
