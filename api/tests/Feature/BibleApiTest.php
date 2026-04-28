<?php

namespace Tests\Feature;

use App\Domain\Bible\Entities\Verse;
use App\Domain\Bible\Repositories\VerseRepositoryInterface;
use App\Domain\History\Entities\HistoricalContext;
use App\Domain\History\Repositories\HistoricalContextRepositoryInterface;
use App\Domain\Teachings\Entities\ChurchTeaching;
use App\Domain\Teachings\Repositories\ChurchTeachingRepositoryInterface;
use Tests\TestCase;

class BibleApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->bind(VerseRepositoryInterface::class, fn () => new class implements VerseRepositoryInterface {
            /**
             * @return array<int, Verse>
             */
            public function findChapter(string $book, int $chapter, ?string $language = null, ?string $version = null): array
            {
                if ($book !== 'john' || $chapter !== 3) {
                    return [];
                }

                return [
                    new Verse('john-3-1', 'john', 3, 1, 'There was a man of the Pharisees.'),
                    new Verse('john-3-2', 'john', 3, 2, 'He came to Jesus by night.'),
                    new Verse('john-3-16', 'john', 3, 16, 'For God so loved the world.'),
                ];
            }

            public function findByReference(string $book, int $chapter, int $verse, ?string $language = null, ?string $version = null): ?Verse
            {
                foreach ($this->findChapter(strtolower($book), $chapter) as $chapterVerse) {
                    if ($chapterVerse->verse === $verse) {
                        return $chapterVerse;
                    }
                }

                return null;
            }

            public function save(Verse $verse): void
            {
                //
            }
        });

        $this->app->bind(HistoricalContextRepositoryInterface::class, fn () => new class implements HistoricalContextRepositoryInterface {
            public function findChapter(
                string $language,
                string $version,
                string $book,
                int $chapter
            ): array {
                if ($book !== 'john' || $chapter !== 3) {
                    return [];
                }

                return [
                    new HistoricalContext(
                        book: 'john',
                        chapter: 3,
                        verse: 16,
                        summary: 'John 3 reflects a first-century Jewish conversation about rebirth and divine salvation.',
                        details: 'The setting draws on Pharisaic teaching, Second Temple Jewish imagery, and Johannine theology.',
                        references: ['John 3:1-21'],
                        language: $language,
                        version: $version
                    ),
                ];
            }

            public function findByReference(
                string $language,
                string $version,
                string $book,
                int $chapter,
                ?int $verse
            ): ?HistoricalContext {
                if ($book !== 'john' || $chapter !== 3 || $verse !== 16) {
                    return null;
                }

                return new HistoricalContext(
                    book: 'john',
                    chapter: 3,
                    verse: 16,
                    summary: 'John 3 reflects a first-century Jewish conversation about rebirth and divine salvation.',
                    details: 'The setting draws on Pharisaic teaching, Second Temple Jewish imagery, and Johannine theology.',
                    references: ['John 3:1-21'],
                    language: $language,
                    version: $version
                );
            }
        });

        $this->app->bind(ChurchTeachingRepositoryInterface::class, fn () => new class implements ChurchTeachingRepositoryInterface {
            public function findChapter(
                string $language,
                string $version,
                string $book,
                int $chapter
            ): array {
                if ($book !== 'john' || $chapter !== 3) {
                    return [];
                }

                return [
                    new ChurchTeaching(
                        book: 'john',
                        chapter: 3,
                        verse: 16,
                        summary: 'This verse is often connected to the Church teaching on salvation through Christ.',
                        details: 'Catholic interpretation reads the passage within the wider economy of grace and baptismal rebirth.',
                        tradition: 'Catholic',
                        references: ['CCC 458', 'CCC 679'],
                        language: $language,
                        version: $version
                    ),
                ];
            }

            public function findByReference(
                string $language,
                string $version,
                string $book,
                int $chapter,
                ?int $verse
            ): ?ChurchTeaching {
                if ($book !== 'john' || $chapter !== 3 || $verse !== 16) {
                    return null;
                }

                return new ChurchTeaching(
                    book: 'john',
                    chapter: 3,
                    verse: 16,
                    summary: 'This verse is often connected to the Church teaching on salvation through Christ.',
                    details: 'Catholic interpretation reads the passage within the wider economy of grace and baptismal rebirth.',
                    tradition: 'Catholic',
                    references: ['CCC 458', 'CCC 679'],
                    language: $language,
                    version: $version
                );
            }
        });
    }

    public function test_bible_endpoint_returns_a_chapter(): void
    {
        $this->getJson('/bible?book=john&chapter=3')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.book', 'john')
            ->assertJsonPath('data.chapter', 3)
            ->assertJsonPath('data.version', 'nrsvce')
            ->assertJsonPath('data.language', 'en')
            ->assertJsonPath('data.verses.0.verse', 1)
            ->assertJsonPath('data.verses.0.text', 'There was a man of the Pharisees.');
    }

    public function test_bible_endpoint_validates_required_query_parameters(): void
    {
        $this->getJson('/bible?book=john')
            ->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Invalid bible chapter request.')
            ->assertJsonStructure(['errors' => ['chapter']]);
    }

    public function test_bible_endpoint_returns_not_found_for_missing_chapter(): void
    {
        $this->getJson('/bible?book=acts&chapter=99')
            ->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Bible chapter not found.');
    }

    public function test_history_endpoint_returns_historical_context(): void
    {
        $this->getJson('/history?book=john&chapter=3&verse=16')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.book', 'john')
            ->assertJsonPath('data.chapter', 3)
            ->assertJsonPath('data.verse', 16)
            ->assertJsonPath('data.history.summary', 'John 3 reflects a first-century Jewish conversation about rebirth and divine salvation.')
            ->assertJsonPath('data.items.0', 'John 3 reflects a first-century Jewish conversation about rebirth and divine salvation.')
            ->assertJsonPath('data.history.references.0', 'John 3:1-21');
    }

    public function test_history_endpoint_validates_required_chapter_query_parameter(): void
    {
        $this->getJson('/history?book=john')
            ->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Invalid historical context request.')
            ->assertJsonStructure(['errors' => ['chapter']]);
    }

    public function test_history_endpoint_returns_chapter_level_payload_without_verse(): void
    {
        $this->getJson('/history?book=john&chapter=3')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.verse', null)
            ->assertJsonPath('data.items.0', 'Verse 16: John 3 reflects a first-century Jewish conversation about rebirth and divine salvation.');
    }

    public function test_history_endpoint_returns_safe_empty_payload_when_content_is_missing(): void
    {
        $this->getJson('/history?book=john&chapter=3&verse=15')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.history.summary', null)
            ->assertJsonPath('data.history.details', null)
            ->assertJsonPath('data.history.references', []);
    }

    public function test_teachings_endpoint_returns_church_teachings(): void
    {
        $this->getJson('/teachings?book=john&chapter=3&verse=16')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.book', 'john')
            ->assertJsonPath('data.chapter', 3)
            ->assertJsonPath('data.verse', 16)
            ->assertJsonPath('data.teachings.tradition', 'Catholic')
            ->assertJsonPath('data.items.0', 'This verse is often connected to the Church teaching on salvation through Christ.')
            ->assertJsonPath('data.teachings.references.0', 'CCC 458');
    }

    public function test_teachings_endpoint_validates_required_chapter_query_parameter(): void
    {
        $this->getJson('/teachings?book=john')
            ->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Invalid teachings request.')
            ->assertJsonStructure(['errors' => ['chapter']]);
    }

    public function test_teachings_endpoint_returns_chapter_level_payload_without_verse(): void
    {
        $this->getJson('/teachings?book=john&chapter=3')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.verse', null)
            ->assertJsonPath('data.items.0', 'Verse 16: This verse is often connected to the Church teaching on salvation through Christ.');
    }

    public function test_teachings_endpoint_returns_safe_empty_payload_when_content_is_missing(): void
    {
        $this->getJson('/teachings?book=john&chapter=3&verse=15')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.teachings.summary', null)
            ->assertJsonPath('data.teachings.details', null)
            ->assertJsonPath('data.teachings.tradition', 'Catholic')
            ->assertJsonPath('data.teachings.references', []);
    }
}
