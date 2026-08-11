<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Security\Contracts;

interface PersonalDataLocatorInterface
{
    /**
     * @return array<string, mixed>
     */
    public function locate(string $subjectIdentifier): array;
}
