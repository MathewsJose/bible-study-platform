<?php

declare(strict_types=1);

use App\Application\Knowledge\Importing\Contracts\KnowledgeImporterInterface;
use App\Application\Knowledge\Importing\DTOs\RawKnowledgeDocument;
use App\Application\Knowledge\Importing\DTOs\ValidationResult;
use App\Application\Knowledge\Importing\Services\KnowledgeSourceRegistry;

final class RegistryFakeImporter implements KnowledgeImporterInterface
{
    public function __construct(private readonly string $identifier = 'fake') {}

    public function identifier(): string
    {
        return $this->identifier;
    }

    public function displayName(): string
    {
        return 'Fake Source';
    }

    public function version(): string
    {
        return '1.0.0';
    }

    public function supportedLanguages(): array
    {
        return ['en'];
    }

    public function licensing(): array
    {
        return ['license' => null, 'license_url' => null, 'rights_notes' => null];
    }

    public function supports(string $path): bool
    {
        return str_ends_with($path, '.fake');
    }

    public function fetch(string $path, array $metadata = []): RawKnowledgeDocument
    {
        return new RawKnowledgeDocument($this->identifier, $path, 'checksum', 'content', $metadata);
    }

    public function normalize(RawKnowledgeDocument $rawDocument): array
    {
        return [];
    }

    public function validate(RawKnowledgeDocument $rawDocument): ValidationResult
    {
        return ValidationResult::valid();
    }
}

it('registers resolves lists and detects knowledge importers', function (): void {
    $registry = new KnowledgeSourceRegistry();
    $importer = new RegistryFakeImporter();

    $registry->register($importer);

    expect($registry->resolve('fake'))->toBe($importer)
        ->and($registry->detect('sample.fake'))->toBe($importer)
        ->and($registry->all())->toHaveKey('fake');
});

it('rejects duplicate importer identifiers', function (): void {
    $registry = new KnowledgeSourceRegistry();
    $registry->register(new RegistryFakeImporter());

    expect(fn () => $registry->register(new RegistryFakeImporter()))
        ->toThrow(InvalidArgumentException::class, 'already registered');
});
