<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Answering\Contracts;

use App\Application\Knowledge\Answering\DTOs\LLMCompletionRequest;
use App\Application\Knowledge\Answering\DTOs\LlmGatewayResponse;

interface LLMGatewayInterface
{
    public function complete(string $task, LLMCompletionRequest $request, ?string $profile = null): LlmGatewayResponse;
}
