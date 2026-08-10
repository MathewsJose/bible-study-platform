<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Agents\Contracts;

use App\Application\Knowledge\Agents\DTOs\ToolInvocation;
use App\Application\Knowledge\Agents\DTOs\ToolResult;

interface ToolInterface
{
    public function name(): string;

    public function displayName(): string;

    public function description(): string;

    /** @return array<string, mixed> */
    public function inputSchema(): array;

    /** @return array<string, mixed> */
    public function outputSchema(): array;

    /** @return list<string> */
    public function permissions(): array;

    public function timeoutSeconds(): int;

    public function isReadOnly(): bool;

    public function execute(ToolInvocation $invocation): ToolResult;
}
