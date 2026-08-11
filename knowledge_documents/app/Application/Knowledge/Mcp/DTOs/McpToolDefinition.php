<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Mcp\DTOs;

final readonly class McpToolDefinition
{
    /**
     * @param  array<string, mixed>  $inputSchema
     * @param  array<string, mixed>  $outputSchema
     * @param  list<string>  $permissions
     */
    public function __construct(
        public string $name,
        public string $title,
        public string $description,
        public array $inputSchema,
        public array $outputSchema,
        public array $permissions,
        public bool $readOnly,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'title' => $this->title,
            'description' => $this->description,
            'input_schema' => $this->inputSchema,
            'output_schema' => $this->outputSchema,
            'permissions' => $this->permissions,
            'read_only' => $this->readOnly,
        ];
    }
}
