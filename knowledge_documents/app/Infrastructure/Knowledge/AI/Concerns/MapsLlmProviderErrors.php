<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\AI\Concerns;

use App\Application\Knowledge\Answering\Exceptions\LlmAuthenticationException;
use App\Application\Knowledge\Answering\Exceptions\LlmConfigurationException;
use App\Application\Knowledge\Answering\Exceptions\LlmProviderException;
use App\Application\Knowledge\Answering\Exceptions\LlmRateLimitException;
use App\Application\Knowledge\Answering\Exceptions\LlmTimeoutException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Throwable;

trait MapsLlmProviderErrors
{
    private function mapProviderException(Throwable $exception, string $provider, ?string $model = null): LlmProviderException
    {
        if ($exception instanceof LlmProviderException) {
            return $exception;
        }

        if ($exception instanceof ConnectionException) {
            return new LlmTimeoutException('LLM provider connection failed or timed out.', $provider, $model, previous: $exception);
        }

        if ($exception instanceof RequestException) {
            $status = $exception->response->status();

            return match (true) {
                $status === 401 || $status === 403 => new LlmAuthenticationException('LLM provider authentication failed.', $provider, $model, ['status' => $status], previous: $exception),
                $status === 429 => new LlmRateLimitException('LLM provider rate limit reached.', $provider, $model, ['status' => $status], previous: $exception),
                $status >= 500 => new LlmProviderException('LLM provider returned a transient server error.', $provider, $model, ['status' => $status], previous: $exception),
                default => new LlmProviderException('LLM provider request failed.', $provider, $model, ['status' => $status], previous: $exception),
            };
        }

        return new LlmProviderException('LLM provider request failed.', $provider, $model, previous: $exception);
    }

    private function ensureConfigured(string $provider, ?string $url, ?string $apiKey = null, bool $requiresApiKey = false): void
    {
        if ($url === null || trim($url) === '') {
            throw new LlmConfigurationException('LLM provider endpoint is not configured.', $provider);
        }

        if ($requiresApiKey && ($apiKey === null || trim($apiKey) === '')) {
            throw new LlmConfigurationException('LLM provider API key is not configured.', $provider);
        }
    }
}
