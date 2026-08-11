<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Answering\DTOs;

final readonly class LlmProviderHealth
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public string $provider,
        public string $status,
        public ?string $model,
        public string $connectivity,
        public string $securityPolicy,
        public array $metadata = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'status' => $this->status,
            'model' => $this->model,
            'connectivity' => $this->connectivity,
            'security_policy' => $this->securityPolicy,
            'metadata' => $this->metadata,
        ];
    }
}
