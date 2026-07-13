<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Contracts;

use App\Application\Knowledge\DTOs\ImportResult;

interface KnowledgeFileImporterInterface
{
    public function supports(string $path): bool;

    public function fileType(): string;

    public function sourceName(string $path): string;

    public function import(string $path): ImportResult;
}
