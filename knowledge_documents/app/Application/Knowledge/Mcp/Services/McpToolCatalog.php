<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Mcp\Services;

use App\Application\Knowledge\Agents\Contracts\ToolInterface;
use App\Application\Knowledge\Agents\Services\AgentToolRegistry;
use App\Application\Knowledge\Mcp\DTOs\McpToolDefinition;
use InvalidArgumentException;

final readonly class McpToolCatalog
{
    public function __construct(private AgentToolRegistry $tools) {}

    /** @return list<McpToolDefinition> */
    public function all(): array
    {
        return array_values(array_filter(
            array_map(fn (ToolInterface $tool): ?McpToolDefinition => $this->definitionForTool($tool), $this->tools->all()),
        ));
    }

    public function definition(string $name): McpToolDefinition
    {
        if (! $this->isAllowed($name)) {
            throw new InvalidArgumentException("MCP tool [{$name}] is not allowed.");
        }

        return $this->definitionForTool($this->tools->resolve($name))
            ?? throw new InvalidArgumentException("MCP tool [{$name}] is not available.");
    }

    public function tool(string $name): ToolInterface
    {
        $this->definition($name);

        return $this->tools->resolve($name);
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_map(static fn (McpToolDefinition $definition): string => $definition->name, $this->all());
    }

    private function definitionForTool(ToolInterface $tool): ?McpToolDefinition
    {
        if (! $this->isAllowed($tool->name()) || ! $tool->isReadOnly()) {
            return null;
        }

        $schema = $tool->inputSchema();

        return new McpToolDefinition(
            name: $tool->name(),
            title: $tool->displayName(),
            description: $tool->description(),
            inputSchema: $this->jsonSchema($schema),
            outputSchema: $tool->outputSchema(),
            permissions: $this->permissions($tool->name()),
            readOnly: true,
        );
    }

    /** @param array<string, mixed> $schema */
    private function jsonSchema(array $schema): array
    {
        $rules = (array) ($schema['rules'] ?? []);

        return [
            'type' => 'object',
            'properties' => (object) ($schema['properties'] ?? []),
            'required' => array_values(array_filter(
                array_keys($rules),
                static fn (string $key): bool => ! str_contains($key, '.') && in_array('required', (array) $rules[$key], true),
            )),
            'additionalProperties' => false,
        ];
    }

    /** @return list<string> */
    private function permissions(string $tool): array
    {
        return array_values(array_map('strval', (array) config("mcp_knowledge.tools.permissions.{$tool}", ['READ_KNOWLEDGE'])));
    }

    private function isAllowed(string $tool): bool
    {
        return in_array($tool, (array) config('mcp_knowledge.tools.allowlist', []), true);
    }
}
