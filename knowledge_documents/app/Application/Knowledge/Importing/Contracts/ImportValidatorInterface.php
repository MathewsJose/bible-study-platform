<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Importing\Contracts;

use App\Application\Knowledge\Importing\DTOs\RawKnowledgeDocument;
use App\Application\Knowledge\Importing\DTOs\ValidationResult;

interface ImportValidatorInterface
{
    public function validate(RawKnowledgeDocument $rawDocument): ValidationResult;
}
