<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Knowledge\Agents\Contracts\AgentInterface;
use App\Application\Knowledge\Agents\DTOs\AgentRequest;
use App\Application\Knowledge\Agents\DTOs\ToolResult;
use Illuminate\Console\Command;

final class AgentRunCommand extends Command
{
    protected $signature = 'agent:run
                            {input : User request for the agent}
                            {--profile=catholic_research : Agent profile}
                            {--max-steps= : Maximum execution steps}';

    protected $description = 'Run the controlled knowledge agent and print the execution trace.';

    public function handle(AgentInterface $agent): int
    {
        $response = $agent->execute(new AgentRequest(
            input: (string) $this->argument('input'),
            profile: (string) $this->option('profile'),
            maxSteps: $this->option('max-steps') === null ? null : (int) $this->option('max-steps'),
        ));

        $this->line('Agent Response');
        $this->line('Status: '.$response->status);
        $this->line('Agent ID: '.$response->agentId);
        $this->line('');
        $this->line($response->answer);
        $this->line('');
        $this->line('Tools');
        $this->table(
            ['Tool', 'Status', 'Latency'],
            array_map(static fn (ToolResult $result): array => [$result->tool, $result->status, $result->latencyMs.'ms'], $response->toolResults),
        );

        return $response->status === 'failed' ? self::FAILURE : self::SUCCESS;
    }
}
