<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Security\DTOs;

use App\Application\Knowledge\Security\Enums\RiskLevel;

final readonly class ToolPolicy
{
    public function __construct(
        public string $name,
        public string $permission,
        public bool $readOnly,
        public string $dataAccess,
        public RiskLevel $riskLevel,
        public bool $requiresAuthentication,
        public bool $requiresApproval,
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromConfig(string $name, array $config): self
    {
        return new self(
            name: $name,
            permission: (string) ($config['permission'] ?? 'READ_KNOWLEDGE'),
            readOnly: (bool) ($config['read_only'] ?? true),
            dataAccess: (string) ($config['data_access'] ?? 'public_knowledge'),
            riskLevel: RiskLevel::tryFrom((string) ($config['risk'] ?? 'low')) ?? RiskLevel::Low,
            requiresAuthentication: (bool) ($config['requires_authentication'] ?? true),
            requiresApproval: (bool) ($config['requires_approval'] ?? false),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'permission' => $this->permission,
            'read_only' => $this->readOnly,
            'data_access' => $this->dataAccess,
            'risk_level' => $this->riskLevel->value,
            'requires_authentication' => $this->requiresAuthentication,
            'requires_approval' => $this->requiresApproval,
        ];
    }
}
