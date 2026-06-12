<?php

declare(strict_types=1);

namespace App\Domain\Knowledge\ValueObjects;

use InvalidArgumentException;

final readonly class DocumentReference
{
    public function __construct(public string $value)
    {
        $value = trim($this->value);

        if ($value === '') {
            throw new InvalidArgumentException('Document reference cannot be empty.');
        }

        if (mb_strlen($value) > 255) {
            throw new InvalidArgumentException('Document reference cannot exceed 255 characters.');
        }
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
