<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Knowledge\Agents\Services\AgentProfileRepository;
use App\Application\Knowledge\Agents\Services\AgentToolRegistry;
use Illuminate\Console\Command;

final class AgentHealthCommand extends Command
{
    protected $signature = 'agent:health';

    protected $description = 'Show agent framework configuration and diagnostics.';

    public function handle(AgentToolRegistry $tools, AgentProfileRepository $profiles): int
    {
        $this->line('Agent Framework Health');
        $this->line('Planner: '.config('agents.planner', 'deterministic'));
        $this->line('Default profile: '.config('agents.default_profile', 'catholic_research'));
        $this->line('Registered tools: '.count($tools->all()));
        $this->line('Agent profiles: '.count($profiles->all()));
        $this->line('');

        $this->table(
            ['Profile', 'Max Steps', 'Max Tool Calls', 'Retrieval', 'Answer'],
            array_map(static fn ($profile): array => [
                $profile->identifier,
                $profile->maxSteps,
                $profile->maxToolCalls,
                $profile->retrievalProfile,
                $profile->answerProfile,
            ], $profiles->all()),
        );

        $this->line('Execution statistics are emitted per response trace; persistent trace storage is planned for a future sprint.');

        return self::SUCCESS;
    }
}
