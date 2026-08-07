<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Importing\Services;

use App\Application\Knowledge\Importing\Contracts\KnowledgeImporterInterface;
use InvalidArgumentException;

final class KnowledgeSourceRegistry
{
    /** @var array<string, KnowledgeImporterInterface> */
    private array $importers = [];

    public function register(KnowledgeImporterInterface $importer): void
    {
        $identifier = $importer->identifier();

        if (isset($this->importers[$identifier])) {
            throw new InvalidArgumentException("Knowledge source [{$identifier}] is already registered.");
        }

        $this->importers[$identifier] = $importer;
    }

    public function resolve(string $identifier): KnowledgeImporterInterface
    {
        return $this->importers[$identifier]
            ?? throw new InvalidArgumentException("Knowledge source [{$identifier}] is not registered.");
    }

    public function detect(string $path): ?KnowledgeImporterInterface
    {
        foreach ($this->importers as $importer) {
            if ($importer->supports($path)) {
                return $importer;
            }
        }

        return null;
    }

    /**
     * @return array<string, KnowledgeImporterInterface>
     */
    public function all(): array
    {
        return $this->importers;
    }
}
