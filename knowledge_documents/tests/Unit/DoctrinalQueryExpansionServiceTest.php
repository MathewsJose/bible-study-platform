<?php

declare(strict_types=1);

use App\Application\Knowledge\Retrieval\Experiments\DoctrinalQueryExpansionService;
use Tests\TestCase;

uses(TestCase::class);

it('leaves baseline queries unchanged', function (): void {
    $result = app(DoctrinalQueryExpansionService::class)
        ->expand('Why does John teach that the Word is God?', 'baseline');

    expect($result->expandedQuery)->toBe('Why does John teach that the Word is God?')
        ->and($result->terms)->toBe([])
        ->and($result->queryDriftScore)->toBe(0.0);
});

it('preserves explicit scripture references without forcing hidden answer references', function (): void {
    $result = app(DoctrinalQueryExpansionService::class)
        ->expand('Explain John 1:1.', 'reference_expansion');

    expect($result->terms)->toContain('John 1:1')
        ->and($result->terms)->toContain('John 1')
        ->and($result->expandedQuery)->toContain('Explain John 1:1.');
});

it('adds only deterministic query-local lexical terms in lexical mode', function (): void {
    $result = app(DoctrinalQueryExpansionService::class)
        ->expand('Where does Scripture say the Word was God?', 'lexical_expansion');

    expect($result->terms)->toContain('scripture')
        ->and($result->terms)->toContain('word')
        ->and($result->terms)->toContain('god')
        ->and($result->terms)->not->toContain('John 1:1');
});

it('adds configured doctrinal bridge terms when a profile is triggered', function (): void {
    $result = app(DoctrinalQueryExpansionService::class)
        ->expand('Why do Christians believe in the divinity of the Word?', 'doctrinal_bridge');

    expect($result->profiles)->toContain('logos_divinity')
        ->and($result->terms)->toContain('Logos')
        ->and($result->terms)->toContain('John 1')
        ->and($result->terms)->not->toContain('John 1:1');
});

it('deduplicates combined expansion terms and records drift', function (): void {
    $result = app(DoctrinalQueryExpansionService::class)
        ->expand('What does John 1:1 teach about the Word was God?', 'combined');

    expect($result->terms)->toHaveCount(count(array_unique(array_map('mb_strtolower', $result->terms))))
        ->and($result->queryDriftScore)->toBeGreaterThan(0.0)
        ->and($result->configVersion)->toBe('retrieval-sprint-32-v1');
});

it('rejects unsupported expansion modes', function (): void {
    app(DoctrinalQueryExpansionService::class)
        ->expand('What does John teach?', 'made_up_mode');
})->throws(InvalidArgumentException::class);

it('renders doctrinal expansion from the artisan command', function (): void {
    $this->artisan('retrieval:doctrinal-expand', [
        '--query' => 'Why does John teach that the Word was God?',
        '--mode' => 'combined',
    ])
        ->expectsOutputToContain('Sprint 32 Doctrinal Query Expansion')
        ->expectsOutputToContain('Mode: combined')
        ->assertSuccessful();
});
