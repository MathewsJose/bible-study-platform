<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Agents\Events;

use App\Application\Knowledge\Agents\DTOs\AgentAction;
use App\Application\Knowledge\Agents\DTOs\AgentState;
use Illuminate\Foundation\Events\Dispatchable;

final class AgentStepStarted
{
    use Dispatchable;

    public function __construct(public readonly AgentState $state, public readonly AgentAction $action) {}
}
