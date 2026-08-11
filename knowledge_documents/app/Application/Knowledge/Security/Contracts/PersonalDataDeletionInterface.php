<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Security\Contracts;

interface PersonalDataDeletionInterface
{
    /**
     * @return array<string, mixed>
     */
    public function delete(string $subjectIdentifier, bool $dryRun = true): array;
}
