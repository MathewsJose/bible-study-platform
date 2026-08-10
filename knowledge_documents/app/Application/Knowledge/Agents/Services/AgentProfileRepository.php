<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Agents\Services;

use App\Application\Knowledge\Agents\DTOs\AgentProfile;
use InvalidArgumentException;

final readonly class AgentProfileRepository
{
    public function resolve(?string $identifier = null): AgentProfile
    {
        $profile = $identifier ?: (string) config('agents.default_profile', 'catholic_research');
        $profiles = (array) config('agents.profiles', []);

        if (! isset($profiles[$profile]) || ! is_array($profiles[$profile])) {
            throw new InvalidArgumentException("Unknown agent profile [{$profile}].");
        }

        return AgentProfile::fromConfig($profile, $profiles[$profile]);
    }

    /** @return list<string> */
    public function identifiers(): array
    {
        return array_keys((array) config('agents.profiles', []));
    }

    /** @return list<AgentProfile> */
    public function all(): array
    {
        return array_map(
            fn (string $identifier): AgentProfile => $this->resolve($identifier),
            $this->identifiers(),
        );
    }
}
