<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Evaluation\DTOs;

final readonly class AiEvaluationRunData
{
    /**
     * @param  list<AiEvaluationResultData>  $results
     * @param  array<string, mixed>  $metrics
     * @param  array<string, mixed>  $configuration
     * @param  array<string, mixed>  $fingerprints
     */
    public function __construct(
        public string $name,
        public string $evaluationType,
        public string $status,
        public array $results,
        public array $metrics,
        public array $configuration,
        public array $fingerprints,
        public ?string $runId = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(bool $includeResults = true): array
    {
        $data = [
            'run_id' => $this->runId,
            'name' => $this->name,
            'evaluation_type' => $this->evaluationType,
            'status' => $this->status,
            'total_questions' => count($this->results),
            'metrics' => $this->metrics,
            'configuration' => $this->configuration,
            'fingerprints' => $this->fingerprints,
        ];

        if ($includeResults) {
            $data['results'] = array_map(static fn (AiEvaluationResultData $result): array => $result->toArray(), $this->results);
        }

        return $data;
    }
}
