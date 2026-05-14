<?php

namespace App\Application\Study\Services;

use App\Application\Bible\DTOs\BibleChapterDTO;
use App\Application\Bible\Services\BibleService;
use App\Application\History\DTOs\HistoricalContextDTO;
use App\Application\History\Services\HistoryService;
use App\Application\Teachings\DTOs\ChurchTeachingDTO;
use App\Application\Teachings\Services\TeachingsService;

class StudyService
{
    public function __construct(
        private readonly BibleService $bibleService,
        private readonly HistoryService $historyService,
        private readonly TeachingsService $teachingsService
    ) {}

    /**
     * @return array{bible: array, history: array, teachings: array}|null
     */
    public function getStudyPayload(
        string $language,
        string $version,
        string $book,
        int $chapter,
        ?int $verse = null
    ): ?array {
        $bible = $this->bibleService->getBibleChapter($language, $version, $book, $chapter);

        if ($bible === null) {
            return null;
        }

        return [
            'bible' => $this->bibleToArray($bible),
            'history' => $this->historyToArray(
                $this->historyService->getHistoricalContext($language, $version, $book, $chapter, $verse)
            ),
            'teachings' => $this->teachingsToArray(
                $this->teachingsService->getTeachings($language, $version, $book, $chapter, $verse)
            ),
        ];
    }

    private function bibleToArray(BibleChapterDTO $bible): array
    {
        return [
            'book' => $bible->book,
            'chapter' => $bible->chapter,
            'version' => $bible->version,
            'language' => $bible->language,
            'verses' => $bible->verses,
        ];
    }

    private function historyToArray(HistoricalContextDTO $history): array
    {
        return [
            'book' => $history->book,
            'chapter' => $history->chapter,
            'verse' => $history->verse,
            'history' => $history->history,
            'items' => $history->items,
        ];
    }

    private function teachingsToArray(ChurchTeachingDTO $teachings): array
    {
        return [
            'book' => $teachings->book,
            'chapter' => $teachings->chapter,
            'verse' => $teachings->verse,
            'teachings' => $teachings->teachings,
            'items' => $teachings->items,
        ];
    }
}
