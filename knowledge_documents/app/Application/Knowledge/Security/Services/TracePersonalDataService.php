<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Security\Services;

use App\Application\Knowledge\Security\Contracts\PersonalDataDeletionInterface;
use App\Application\Knowledge\Security\Contracts\PersonalDataLocatorInterface;

final readonly class TracePersonalDataService implements PersonalDataLocatorInterface, PersonalDataDeletionInterface
{
    public function locate(string $subjectIdentifier): array
    {
        return [
            'subject_identifier_hash' => hash('sha256', $subjectIdentifier),
            'trace_storage' => 'anonymous_by_default',
            'requires_store_inputs' => (bool) config('agent_observability.tracing.store_inputs', false),
            'message' => 'Knowledge Service traces do not store user accounts. Stored payloads are redacted and can only be searched by an external subject mapping.',
        ];
    }

    public function delete(string $subjectIdentifier, bool $dryRun = true): array
    {
        return [
            'subject_identifier_hash' => hash('sha256', $subjectIdentifier),
            'dry_run' => $dryRun,
            'deleted_records' => 0,
            'message' => 'No direct subject-linked records were deleted because this service does not maintain user identity records.',
        ];
    }
}
