<?php

declare(strict_types=1);

use App\Application\Knowledge\Retrieval\Experiments\DeterministicScriptureQueryRouter;
use Tests\TestCase;

uses(TestCase::class);

it('detects and normalizes canonical scripture references', function (string $query, string $reference): void {
    $router = app(DeterministicScriptureQueryRouter::class);

    expect($router->detectReferences($query))->toContain($reference);
})->with([
    ['John 1:1', 'John 1:1'],
    ['What does John 3:16 say?', 'John 3:16'],
    ['Explain Tobit 1:1.', 'Tobit 1:1'],
    ['Read 1 Maccabees 1:1', '1 Maccabees 1:1'],
    ['Read 2 Maccabees 1:1', '2 Maccabees 1:1'],
    ['What does Ecclesiasticus 1:1 say?', 'Sirach 1:1'],
]);

it('classifies direct references separately from contextual reference questions', function (): void {
    $router = app(DeterministicScriptureQueryRouter::class);

    expect($router->classify('John 1:1')->route)->toBe('exact_reference')
        ->and($router->classify('What does John 1:1 say?')->route)->toBe('exact_reference')
        ->and($router->classify('What does John 1:1 teach about the Word?')->route)->toBe('reference_contextual');
});

it('classifies doctrinal queries without explicit references', function (): void {
    $router = app(DeterministicScriptureQueryRouter::class);

    expect($router->classify('Why do Christians believe that the Word is divine?')->route)->toBe('doctrinal_semantic')
        ->and($router->classify('What are the three persons of the Trinity?')->route)->toBe('doctrinal_semantic');
});

it('does not falsely detect arbitrary numbers as scripture references', function (string $query): void {
    $router = app(DeterministicScriptureQueryRouter::class);
    $classification = $router->classify($query);

    expect($classification->references)->toBe([]);
})->with([
    'seventy three books' => ['Why are there 73 books in the Catholic Bible?'],
    'first chapter' => ['What does the first chapter teach about creation?'],
    'historical year' => ['What happened in year 325?'],
    'unknown book' => ['What does madeup 1:1 say?'],
    'chapter only' => ['What does John chapter one teach?'],
]);
