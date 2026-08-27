<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Retrieval\Experiments;

use App\Infrastructure\Knowledge\Importing\BibleCanon;
use Illuminate\Support\Str;

final readonly class DeterministicScriptureQueryRouter
{
    public function __construct(private BibleCanon $canon) {}

    public function classify(string $query): ScriptureRoutingClassification
    {
        $references = $this->detectReferences($query);
        $normalized = $this->normalize($query);

        if ($references !== []) {
            if ($this->isExactReferenceQuery($query, $references)) {
                return new ScriptureRoutingClassification(
                    route: 'exact_reference',
                    references: $references,
                    reasons: ['Detected an explicit Scripture reference with exact-reference wording.'],
                );
            }

            return new ScriptureRoutingClassification(
                route: 'reference_contextual',
                references: $references,
                reasons: ['Detected an explicit Scripture reference embedded in a contextual question.'],
            );
        }

        if ($this->isDoctrinal($normalized)) {
            return new ScriptureRoutingClassification(
                route: 'doctrinal_semantic',
                references: [],
                reasons: ['Detected doctrinal vocabulary without an explicit Scripture reference.'],
            );
        }

        return new ScriptureRoutingClassification(
            route: 'general_semantic',
            references: [],
            reasons: ['No explicit Scripture reference or doctrinal routing cue was detected.'],
        );
    }

    /**
     * @return list<string>
     */
    public function detectReferences(string $query): array
    {
        $references = [];
        preg_match_all('/\b('.$this->bookPattern().')\s+(\d+):(\d+)\b/i', $query, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $reference = $this->normalizeReference((string) $match[0]);
            if ($reference !== null) {
                $references[] = $reference;
            }
        }

        return array_values(array_unique($references));
    }

    public function normalizeReference(string $reference): ?string
    {
        $reference = trim(preg_replace('/\s+/', ' ', $reference) ?? $reference);

        if (preg_match('/^((?:[1-3]\s+)?[A-Za-z]+(?:\s+[A-Za-z]+)*)\s+(\d+):(\d+)$/', $reference, $matches) !== 1) {
            return null;
        }

        $book = trim((string) $matches[1]);
        $book = $this->normalizeBookAlias($book);

        if (! $this->canon->isValidBook($book)) {
            return null;
        }

        $chapter = max(1, (int) $matches[2]);
        $verse = max(1, (int) $matches[3]);

        return $this->canon->canonicalBook($book).' '.$chapter.':'.$verse;
    }

    /**
     * @param  list<string>  $references
     */
    private function isExactReferenceQuery(string $query, array $references): bool
    {
        $withoutReferences = $this->normalize($query);

        foreach ($references as $reference) {
            $withoutReferences = trim(str_replace($this->normalize($reference), '', $withoutReferences));
        }

        $withoutReferences = trim(preg_replace('/\s+/', ' ', $withoutReferences) ?? $withoutReferences);

        if ($withoutReferences === '') {
            return true;
        }

        foreach ((array) config('retrieval_sprint33.classification.contextual_reference_cues', []) as $cue) {
            if (str_contains($withoutReferences, $this->normalize((string) $cue))) {
                return false;
            }
        }

        foreach ((array) config('retrieval_sprint33.classification.exact_reference_cues', []) as $cue) {
            if (str_contains($withoutReferences, $this->normalize((string) $cue))) {
                return true;
            }
        }

        return false;
    }

    private function isDoctrinal(string $normalizedQuery): bool
    {
        foreach ((array) config('retrieval_sprint33.classification.doctrinal_terms', []) as $term) {
            if (str_contains($normalizedQuery, $this->normalize((string) $term))) {
                return true;
            }
        }

        foreach ((array) config('retrieval_sprint32.profiles', []) as $profile) {
            foreach ((array) ($profile['triggers'] ?? []) as $trigger) {
                if (str_contains($normalizedQuery, $this->normalize((string) $trigger))) {
                    return true;
                }
            }
        }

        return false;
    }

    private function normalizeBookAlias(string $book): string
    {
        $normalized = Str::of($book)->lower()->replaceMatches('/\s+/', ' ')->trim()->toString();

        return match ($normalized) {
            'apocalypse' => 'revelation',
            'canticle of canticles', 'song of solomon' => 'song of songs',
            'ecclesiasticus' => 'sirach',
            'josue' => 'joshua',
            'ezechiel' => 'ezekiel',
            'osee' => 'hosea',
            'abdias' => 'obadiah',
            'jonas' => 'jonah',
            'micheas' => 'micah',
            'habacuc' => 'habakkuk',
            'sophonias' => 'zephaniah',
            'aggeus' => 'haggai',
            'zacharias' => 'zechariah',
            'malachias' => 'malachi',
            default => $normalized,
        };
    }

    private function bookPattern(): string
    {
        $books = [
            ...$this->canon->books(),
            'Apocalypse',
            'Canticle of Canticles',
            'Ecclesiasticus',
            'Josue',
            'Ezechiel',
            'Osee',
            'Abdias',
            'Jonas',
            'Micheas',
            'Habacuc',
            'Sophonias',
            'Aggeus',
            'Zacharias',
            'Malachias',
        ];

        usort($books, static fn (string $first, string $second): int => mb_strlen($second) <=> mb_strlen($first));

        return implode('|', array_map(
            static fn (string $book): string => str_replace('\ ', '\s+', preg_quote($book, '/')),
            array_values(array_unique($books)),
        ));
    }

    private function normalize(string $value): string
    {
        return Str::of($value)
            ->lower()
            ->replaceMatches('/[^a-z0-9:]+/', ' ')
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->toString();
    }
}
