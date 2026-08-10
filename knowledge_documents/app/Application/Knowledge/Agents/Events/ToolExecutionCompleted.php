<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Agents\Events;

use App\Application\Knowledge\Agents\DTOs\AgentState;
use App\Application\Knowledge\Agents\DTOs\ToolResult;
use Illuminate\Foundation\Events\Dispatchable;

final class ToolExecutionCompleted
{
    use Dispatchable;

    public function __construct(public readonly AgentState $state, public readonly ToolResult $result) {}
}
