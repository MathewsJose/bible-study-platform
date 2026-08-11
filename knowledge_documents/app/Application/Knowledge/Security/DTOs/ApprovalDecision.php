<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Security\DTOs;

use App\Application\Knowledge\Security\Enums\RiskLevel;

final readonly class ApprovalDecision
{
    public function __construct(
        public bool $required,
        public string $reason,
        public RiskLevel $riskLevel,
        public string $tool,
        public string $requestedAction,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'required' => $this->required,
            'reason' => $this->reason,
            'risk_level' => $this->riskLevel->value,
            'tool' => $this->tool,
            'requested_action' => $this->requestedAction,
        ];
    }
}
