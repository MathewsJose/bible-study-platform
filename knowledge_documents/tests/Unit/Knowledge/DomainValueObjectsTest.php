<?php

declare(strict_types=1);

use App\Domain\Knowledge\ValueObjects\DocumentReference;

it('rejects an empty document reference', function (): void {
    new DocumentReference('   ');
})->throws(InvalidArgumentException::class);

it('accepts a meaningful document reference', function (): void {
    $reference = new DocumentReference('CCC 27');

    expect((string) $reference)->toBe('CCC 27');
});
