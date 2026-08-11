<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Answering\Services;

final readonly class LlmUsageCostCalculator
{
    public function estimate(string $provider, string $model, ?int $inputTokens, ?int $outputTokens): ?float
    {
        if ($inputTokens === null || $outputTokens === null) {
            return null;
        }

        $pricing = config("llm.pricing.models.{$provider}:{$model}");
        if (! is_array($pricing)) {
            return null;
        }

        $inputCost = $pricing['input_cost_per_1m_tokens'] ?? null;
        $outputCost = $pricing['output_cost_per_1m_tokens'] ?? null;

        if (! is_numeric($inputCost) || ! is_numeric($outputCost)) {
            return null;
        }

        return round(($inputTokens / 1_000_000 * (float) $inputCost) + ($outputTokens / 1_000_000 * (float) $outputCost), 8);
    }
}
