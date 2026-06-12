<?php

declare(strict_types=1);

namespace App\Presentation\Http\Requests;

use App\Domain\Knowledge\Enums\SourceType;
use App\Domain\Knowledge\Enums\Tradition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateKnowledgeDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'source_type' => ['sometimes', 'string', Rule::in(SourceType::values())],
            'source_name' => ['sometimes', 'string', 'max:255'],
            'tradition' => ['sometimes', 'string', Rule::in(Tradition::values())],
            'reference' => ['sometimes', 'string', 'max:255'],
            'title' => ['sometimes', 'string', 'max:255'],
            'content' => ['sometimes', 'string'],
            'metadata' => ['sometimes', 'array'],
            'embedding' => ['sometimes', 'array', 'size:1536'],
            'embedding.*' => ['numeric', 'between:-1,1'],
        ];
    }
}
