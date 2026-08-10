<?php

declare(strict_types=1);

namespace App\Application\Knowledge\DTOs;

final readonly class KnowledgeServiceResult
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public array $data,
        public array $meta = [],
        public string $requestId = '',
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'request_id' => $this->requestId,
            'data' => $this->data,
            'meta' => $this->meta,
        ];
    }
}
