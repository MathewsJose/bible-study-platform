<?php

declare(strict_types=1);

namespace App\Presentation\Http\Requests;

use App\Domain\Knowledge\Enums\SourceType;
use App\Domain\Knowledge\Enums\Tradition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreKnowledgeDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'source_type' => ['required', 'string', Rule::in(SourceType::values())],
            'source_name' => ['required', 'string', 'max:255'],
            'tradition' => ['required', 'string', Rule::in(Tradition::values())],
            'reference' => ['required', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'metadata' => ['sometimes', 'array'],
            'embedding' => ['sometimes', 'array', 'size:1536'],
            'embedding.*' => ['numeric', 'between:-1,1'],
        ];
    }
}
