<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Retrieval\Experiments;

use App\Infrastructure\Knowledge\Persistence\KnowledgeDocumentRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final readonly class ContextualBibleTextBuilder
{
    public function build(KnowledgeDocumentRecord $document, int $window): string
    {
        if ($document->source_type !== 'bible_verse') {
            return $this->label($document)."\n\n".$document->content;
        }

        $reference = $this->parseReference($document->reference);
        if ($reference === null || $window <= 0) {
            return $this->label($document)."\n\nTarget {$document->reference}: {$document->content}";
        }

        $neighbors = $this->neighborQuery($reference, $document->source_name, $window)
            ->orderBy($this->verseOrderColumn())
            ->get(['reference', 'content']);

        $lines = [$this->label($document)];

        foreach ($neighbors as $neighbor) {
            $marker = $neighbor->reference === $document->reference ? 'Target' : 'Context';
            $lines[] = "{$marker} {$neighbor->reference}: {$neighbor->content}";
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array{book: string, chapter: int, verse: int}  $reference
     * @return Builder<KnowledgeDocumentRecord>
     */
    private function neighborQuery(array $reference, string $sourceName, int $window): Builder
    {
        $query = KnowledgeDocumentRecord::query()
            ->where('source_type', 'bible_verse')
            ->where('source_name', $sourceName);

        if (DB::getDriverName() === 'pgsql') {
            return $query
                ->whereRaw("metadata->>'book' = ?", [$reference['book']])
                ->whereRaw("metadata->>'chapter' = ?", [(string) $reference['chapter']])
                ->whereRaw("(metadata->>'verse')::int between ? and ?", [
                    max(1, $reference['verse'] - $window),
                    $reference['verse'] + $window,
                ])
                ->orderByRaw("(metadata->>'verse')::int");
        }

        return $query
            ->where('metadata->book', $reference['book'])
            ->where('metadata->chapter', $reference['chapter'])
            ->whereBetween('metadata->verse', [
                max(1, $reference['verse'] - $window),
                $reference['verse'] + $window,
            ]);
    }

    private function verseOrderColumn(): string
    {
        return DB::getDriverName() === 'pgsql' ? 'reference' : 'metadata->verse';
    }

    private function label(KnowledgeDocumentRecord $document): string
    {
        return implode("\n", array_filter([
            'Source: '.$document->source_name,
            'Type: '.$document->source_type,
            'Reference: '.$document->reference,
            $document->title !== '' ? 'Title: '.$document->title : null,
        ]));
    }

    /**
     * @return array{book: string, chapter: int, verse: int}|null
     */
    private function parseReference(string $reference): ?array
    {
        if (! preg_match('/^(?<book>(?:[1-3]\s+)?[A-Za-z]+(?:\s+[A-Za-z]+)*)\s+(?<chapter>\d+):(?<verse>\d+)$/', $reference, $matches)) {
            return null;
        }

        return [
            'book' => $matches['book'],
            'chapter' => (int) $matches['chapter'],
            'verse' => (int) $matches['verse'],
        ];
    }
}
