<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Security\Services;

use App\Application\Knowledge\Security\DTOs\ToolPolicy;

final readonly class ToolPolicyCatalog
{
    public function policyFor(string $tool): ?ToolPolicy
    {
        $config = config("ai_security.tools.{$tool}");

        return is_array($config) ? ToolPolicy::fromConfig($tool, $config) : null;
    }

    /** @return list<ToolPolicy> */
    public function all(): array
    {
        $tools = [];

        foreach ((array) config('ai_security.tools', []) as $name => $config) {
            if (is_string($name) && is_array($config)) {
                $tools[] = ToolPolicy::fromConfig($name, $config);
            }
        }

        return $tools;
    }
}
