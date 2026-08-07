<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Answering\Contracts;

use App\Application\Knowledge\Answering\DTOs\LLMCompletionRequest;
use App\Application\Knowledge\Answering\DTOs\LLMCompletionResponse;

interface LLMProviderInterface
{
    public function complete(LLMCompletionRequest $request): LLMCompletionResponse;

    /**
     * @return iterable<string>
     */
    public function stream(LLMCompletionRequest $request): iterable;

    public function countTokens(string $text): ?int;

    /** @return array<string, mixed> */
    public function metadata(): array;

    public function identifier(): string;
}
