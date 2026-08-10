<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Agents\DTOs;

final readonly class AgentAction
{
    /**
     * @param  array<string, mixed>  $arguments
     */
    public function __construct(
        public string $tool,
        public array $arguments,
        public string $reason,
    ) {}

    public function signature(): string
    {
        return $this->tool.':'.md5(json_encode($this->arguments, JSON_THROW_ON_ERROR));
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'tool' => $this->tool,
            'arguments' => $this->arguments,
            'reason' => $this->reason,
        ];
    }
}
