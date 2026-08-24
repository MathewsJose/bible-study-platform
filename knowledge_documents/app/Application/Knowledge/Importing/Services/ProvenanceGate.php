<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Importing\Services;

use App\Application\Knowledge\Importing\Contracts\KnowledgeImporterInterface;
use App\Application\Knowledge\Importing\DTOs\ProvenanceGateResult;
use App\Domain\Knowledge\Enums\CopyrightStatus;
use App\Domain\Knowledge\Enums\SourceInventoryStatus;

final readonly class ProvenanceGate
{
    public function __construct(private SourceInventory $inventory) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function evaluate(
        KnowledgeImporterInterface $importer,
        ?string $sourceId = null,
        array $metadata = [],
        bool $allowUnsafeOverride = false,
    ): ProvenanceGateResult {
        if (! (bool) config('knowledge_sources.gate.enabled', true)) {
            $source = $this->inventory->resolve($sourceId, $importer->identifier());
            $provenance = $source?->provenance($importer->version(), $metadata);

            return new ProvenanceGateResult(
                allowed: true,
                source: $source,
                provenance: $provenance,
                warnings: ['Provenance gate is disabled by configuration.'],
            );
        }

        $source = $this->inventory->resolve($sourceId, $importer->identifier());
        if (! $source) {
            $message = $sourceId !== null && trim($sourceId) !== ''
                ? "No source inventory entry is configured for [{$sourceId}]."
                : "No unambiguous source inventory entry is configured for importer [{$importer->identifier()}].";

            return new ProvenanceGateResult(
                allowed: false,
                source: null,
                provenance: null,
                errors: [$message, 'Add a verified source inventory entry before importing.'],
            );
        }

        $warnings = $this->warnings($source->copyrightStatus, $source->license);
        $errors = [];

        if (! $source->importAllowed) {
            $errors[] = "Source [{$source->id}] is not approved for import.";
        }

        if ($source->verificationStatus !== SourceInventoryStatus::Approved) {
            $errors[] = "Source [{$source->id}] verification status is [{$source->verificationStatus->value}].";
        }

        if (! $source->copyrightStatus->importable()) {
            $errors[] = "Source [{$source->id}] copyright status [{$source->copyrightStatus->value}] blocks import.";
        }

        $overrideEnabled = (bool) config('knowledge_sources.gate.allow_unverified_override', false);
        $overrideUsed = $allowUnsafeOverride && $overrideEnabled && $errors !== [];
        if ($overrideUsed) {
            $warnings[] = 'Unsafe development override allowed this source despite provenance errors.';
        }

        return new ProvenanceGateResult(
            allowed: $errors === [] || $overrideUsed,
            source: $source,
            provenance: $source->provenance($importer->version(), $metadata),
            warnings: $warnings,
            errors: $overrideUsed ? $errors : $errors,
            overrideUsed: $overrideUsed,
        );
    }

    /**
     * @return list<string>
     */
    private function warnings(CopyrightStatus $copyrightStatus, ?string $license): array
    {
        $warnings = [];

        if ($copyrightStatus === CopyrightStatus::RequiresVerification) {
            $warnings[] = 'Copyright status requires manual verification; do not assume redistribution rights.';
        }

        if ($copyrightStatus === CopyrightStatus::Unknown) {
            $warnings[] = 'Copyright status is unknown and must be verified before import.';
        }

        if ($license === null || trim($license) === '') {
            $warnings[] = 'License information is missing; no license has been inferred.';
        }

        return $warnings;
    }
}
