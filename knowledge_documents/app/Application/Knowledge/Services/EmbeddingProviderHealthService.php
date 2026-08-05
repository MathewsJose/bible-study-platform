<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Services;

use App\Application\Knowledge\Contracts\EmbeddingProviderInterface;
use Throwable;

final readonly class EmbeddingProviderHealthService
{
    public function __construct(
        private EmbeddingProviderInterface $provider,
        private EmbeddingVectorValidator $vectors,
        private RetrievalDiagnosticsService $diagnostics,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function check(): array
    {
        $providerName = (string) config('embeddings.provider', 'null');
        $model = (string) config('embeddings.model', '');
        $dimensions = (int) config('embeddings.dimensions', 1536);
        $apiKeyConfigured = $providerName !== 'openai' || (string) config('embeddings.openai.api_key', '') !== '';
        $connectionOk = false;
        $message = null;
        $actualDimensions = null;

        try {
            $embedding = $this->provider->embed('Embedding provider health check for Catholic Bible knowledge retrieval.');
            $actualDimensions = count($embedding);
            $this->vectors->validate(array_values($embedding));
            $connectionOk = true;
        } catch (Throwable $exception) {
            $message = $exception->getMessage();
        }

        return [
            'provider' => $providerName,
            'provider_identifier' => $this->provider->identifier(),
            'model' => $model,
            'dimensions' => $dimensions,
            'api_key_configured' => $apiKeyConfigured,
            'api_key_status' => $providerName === 'openai' ? ($apiKeyConfigured ? 'configured' : 'missing') : 'not required',
            'api_connection_ok' => $connectionOk,
            'actual_test_dimensions' => $actualDimensions,
            'message' => $message,
            'database' => $this->diagnostics->embeddingStats(),
        ];
    }
}
