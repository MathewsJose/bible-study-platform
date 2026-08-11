<?php

declare(strict_types=1);

namespace App\Presentation\Http\Requests;

use App\Application\Knowledge\Agents\Services\AgentProfileRepository;
use App\Application\Knowledge\Agents\Services\AgentToolRegistry;
use App\Domain\Knowledge\Enums\SourceType;
use App\Domain\Knowledge\Enums\Tradition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class RunAgentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(AgentProfileRepository $profiles, AgentToolRegistry $tools): array
    {
        return [
            'input' => ['required', 'string', 'min:2', 'max:'.(int) config('ai_security.limits.max_input_characters', 1000)],
            'profile' => ['sometimes', 'string', Rule::in($profiles->identifiers())],
            'allowed_tools' => ['sometimes', 'array'],
            'allowed_tools.*' => ['string', Rule::in($tools->names())],
            'max_steps' => ['sometimes', 'integer', 'min:1', 'max:'.(int) config('ai_security.limits.max_agent_steps', 8)],
            'timeout_seconds' => ['sometimes', 'integer', 'min:1', 'max:120'],
            'metadata' => ['sometimes', 'array'],
            'metadata.document_id' => ['sometimes', 'string'],
            'filters' => ['sometimes', 'array'],
            'filters.source_type' => ['sometimes', 'string', Rule::in(SourceType::values())],
            'filters.source_types' => ['sometimes', 'array'],
            'filters.source_types.*' => ['string', Rule::in(SourceType::values())],
            'filters.source_name' => ['sometimes', 'string'],
            'filters.tradition' => ['sometimes', 'string', Rule::in(Tradition::values())],
            'filters.author' => ['sometimes', 'string'],
            'filters.book' => ['sometimes', 'string'],
            'filters.chapter' => ['sometimes', 'integer'],
            'filters.language' => ['sometimes', 'string'],
            'filters.translation' => ['sometimes', 'string'],
            'filters.century' => ['sometimes', 'string'],
            'filters.theological_topic' => ['sometimes', 'string'],
        ];
    }
}
