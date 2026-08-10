<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Knowledge\Agents\Replay\Services\AgentReplayService;
use Illuminate\Console\Command;
use RuntimeException;

final class AgentReplayCommand extends Command
{
    protected $signature = 'agent:replay
        {--id= : Original agent execution id}
        {--strict : Require matching replay fingerprints}
        {--compare : Display the stored comparison report}
        {--dry-run : Compare fingerprints and stored trace without executing tools}
        {--provider= : CLI-only provider override}
        {--model= : CLI-only model override}';

    protected $description = 'Replay a persisted agent execution and compare reproducibility signals.';

    public function handle(AgentReplayService $replays): int
    {
        $id = $this->option('id');

        if (! is_string($id) || $id === '') {
            $this->error('Provide an execution id with --id=...');

            return self::FAILURE;
        }

        try {
            $replay = $replays->replay(
                executionId: $id,
                strict: (bool) $this->option('strict'),
                dryRun: (bool) $this->option('dry-run'),
                provider: is_string($this->option('provider')) ? $this->option('provider') : null,
                model: is_string($this->option('model')) ? $this->option('model') : null,
            );
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->line('Agent Replay');
        $this->line('Replay ID: '.$replay->id);
        $this->line('Original: '.$replay->original_execution_id);
        $this->line('Replay: '.($replay->replay_execution_id ?? 'not executed'));
        $this->line('Status: '.$replay->status);
        $this->line('Comparison: '.($replay->comparison_status ?? 'UNKNOWN'));
        $this->line('Duration: '.$replay->duration_ms.'ms');

        if ((bool) $this->option('compare') && is_array($replay->comparison)) {
            $environment = is_array($replay->comparison['environment'] ?? null) ? $replay->comparison['environment'] : [];
            $this->line('');
            $this->line('Environment');
            $this->table(['Signal', 'Status'], array_map(
                static fn (string $signal, mixed $status): array => [$signal, (string) $status],
                array_keys($environment),
                array_values($environment),
            ));

            $this->line('Tool Sequence: '.($replay->comparison['tool_sequence_status'] ?? 'UNKNOWN'));
            $this->line('Retrieval: '.($replay->comparison['retrieval']['status'] ?? 'UNKNOWN'));
            $this->line('Citations: '.($replay->comparison['citations']['status'] ?? 'UNKNOWN'));
            $this->line('Answer: '.($replay->comparison['answer']['status'] ?? 'UNKNOWN'));

            $causes = (array) ($replay->comparison['possible_causes'] ?? []);

            if ($causes !== []) {
                $this->line('');
                $this->warn('Possible causes');
                foreach ($causes as $cause) {
                    $this->line('- '.(string) $cause);
                }
            }
        }

        return $replay->status === 'failed' ? self::FAILURE : self::SUCCESS;
    }
}
