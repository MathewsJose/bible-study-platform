<?php

declare(strict_types=1);

namespace App\Presentation\Http\Requests;

use App\Domain\Knowledge\Enums\RelationshipType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class KnowledgeRelatedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'relationship_types' => ['sometimes', 'array'],
            'relationship_types.*' => ['string', Rule::in(RelationshipType::values())],
            'depth' => ['sometimes', 'integer', 'min:1', 'max:2'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
