<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Agents\Replay\DTOs;

final readonly class ReplayComparisonResult
{
    /**
     * @param  array<string, string>  $environment
     * @param  list<array<string, mixed>>  $toolSequence
     * @param  array<string, mixed>  $retrieval
     * @param  array<string, mixed>  $citations
     * @param  array<string, mixed>  $answer
     * @param  list<string>  $possibleCauses
     * @param  array<string, mixed>  $latency
     */
    public function __construct(
        public string $status,
        public array $environment,
        public string $toolSequenceStatus,
        public array $toolSequence,
        public array $retrieval,
        public array $citations,
        public array $answer,
        public array $possibleCauses,
        public array $latency,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'environment' => $this->environment,
            'tool_sequence_status' => $this->toolSequenceStatus,
            'tool_sequence' => $this->toolSequence,
            'retrieval' => $this->retrieval,
            'citations' => $this->citations,
            'answer' => $this->answer,
            'possible_causes' => $this->possibleCauses,
            'latency' => $this->latency,
        ];
    }
}
