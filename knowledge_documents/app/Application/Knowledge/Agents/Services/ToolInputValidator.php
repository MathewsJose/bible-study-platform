<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Agents\Services;

use Illuminate\Support\Facades\Validator;

final readonly class ToolInputValidator
{
    /**
     * @param  array<string, mixed>  $arguments
     * @param  array<string, mixed>  $schema
     * @return list<string>
     */
    public function errors(array $arguments, array $schema): array
    {
        $allowed = array_keys((array) ($schema['properties'] ?? []));
        $rules = (array) ($schema['rules'] ?? []);
        $unknown = array_values(array_diff(array_keys($arguments), $allowed));

        $validator = Validator::make($arguments, $rules);
        $errors = $validator->errors()->all();

        foreach ($unknown as $key) {
            $errors[] = "The {$key} field is not allowed.";
        }

        return array_values(array_map('strval', $errors));
    }
}
