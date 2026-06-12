<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Contracts;

interface DocumentImporterInterface
{
    /**
     * @param  iterable<array<string, mixed>>  $records
     */
    public function import(iterable $records): int;
}
