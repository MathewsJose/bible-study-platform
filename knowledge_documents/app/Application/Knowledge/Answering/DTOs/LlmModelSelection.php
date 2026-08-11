<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Answering\DTOs;

final readonly class LlmModelSelection
{
    /**
     * @param  array<string, mixed>  $profile
     * @param  array<string, mixed>  $capabilities
     */
    public function __construct(
        public string $task,
        public string $profileName,
        public string $provider,
        public string $model,
        public array $profile = [],
        public array $capabilities = [],
        public ?string $fallbackProfile = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'task' => $this->task,
            'profile' => $this->profileName,
            'provider' => $this->provider,
            'model' => $this->model,
            'capabilities' => $this->capabilities,
            'fallback_profile' => $this->fallbackProfile,
        ];
    }
}
