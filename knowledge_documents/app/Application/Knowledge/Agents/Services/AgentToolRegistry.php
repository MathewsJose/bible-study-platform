<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Agents\Services;

use App\Application\Knowledge\Agents\Contracts\ToolInterface;
use InvalidArgumentException;

final class AgentToolRegistry
{
    /** @var array<string, ToolInterface> */
    private array $tools = [];

    public function register(ToolInterface $tool): void
    {
        if (isset($this->tools[$tool->name()])) {
            throw new InvalidArgumentException("Agent tool [{$tool->name()}] is already registered.");
        }

        $this->tools[$tool->name()] = $tool;
    }

    public function resolve(string $name): ToolInterface
    {
        if (! isset($this->tools[$name])) {
            throw new InvalidArgumentException("Unknown agent tool [{$name}].");
        }

        return $this->tools[$name];
    }

    public function has(string $name): bool
    {
        return isset($this->tools[$name]);
    }

    /** @return list<ToolInterface> */
    public function all(): array
    {
        return array_values($this->tools);
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_keys($this->tools);
    }
}
