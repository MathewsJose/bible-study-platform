<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers;

use App\Application\Knowledge\Agents\Contracts\AgentInterface;
use App\Application\Knowledge\Agents\DTOs\AgentRequest;
use App\Http\Controllers\Controller;
use App\Presentation\Http\Requests\RunAgentRequest;
use Illuminate\Http\JsonResponse;

final class AgentController extends Controller
{
    public function __construct(private readonly AgentInterface $agent) {}

    public function store(RunAgentRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $response = $this->agent->execute(new AgentRequest(
            input: (string) $validated['input'],
            profile: (string) ($validated['profile'] ?? config('agents.default_profile', 'catholic_research')),
            filters: (array) ($validated['filters'] ?? []),
            allowedTools: array_values(array_map('strval', (array) ($validated['allowed_tools'] ?? []))),
            maxSteps: isset($validated['max_steps']) ? (int) $validated['max_steps'] : null,
            timeoutSeconds: isset($validated['timeout_seconds']) ? (int) $validated['timeout_seconds'] : null,
            metadata: (array) ($validated['metadata'] ?? []),
        ));

        return response()->json(['data' => $response->toArray()]);
    }
}
