<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Knowledge\Security\Services\ToolPolicyCatalog;
use Illuminate\Console\Command;

final class AISecurityHealthCommand extends Command
{
    protected $signature = 'ai:security-health';

    protected $description = 'Display AI security, privacy, provider, and guardrail configuration.';

    public function handle(ToolPolicyCatalog $tools): int
    {
        $this->line('AI Security');
        $this->line('Enabled: '.($this->enabled(config('ai_security.enabled'))));
        $this->line('PII action: '.config('ai_security.pii.action'));
        $this->line('Prompt injection action: '.config('ai_security.prompt_injection.action'));
        $this->line('External processing allowed: '.$this->enabled(config('ai_security.external_processing.allow')));
        $this->line('Data policy: '.config('ai_security.external_processing.data_policy'));
        $this->line('Max input characters: '.config('ai_security.limits.max_input_characters'));
        $this->newLine();

        $this->line('Agent Security');
        $this->line('PII detected: diagnostic only until request evaluation');
        $this->line('Prompt injection: deterministic rules');
        $this->line('Tool authorized: evaluated per invocation');
        $this->line('Approval required: policy boundary enabled');
        $this->newLine();

        $this->table(
            ['Tool', 'Permission', 'Read Only', 'Risk', 'Approval'],
            array_map(static fn ($policy): array => [
                $policy->name,
                $policy->permission,
                $policy->readOnly ? 'yes' : 'no',
                $policy->riskLevel->value,
                $policy->requiresApproval ? 'yes' : 'no',
            ], $tools->all()),
        );

        return self::SUCCESS;
    }

    private function enabled(mixed $value): string
    {
        return (bool) $value ? 'yes' : 'no';
    }
}
