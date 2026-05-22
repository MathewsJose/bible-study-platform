<?php

namespace App\Console\Commands;

use App\Infrastructure\Bible\Persistence\Mongo\Models\VerseModel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ImportCpdvBible extends Command
{
    protected $signature = 'bible:import-cpdv
        {--source=https://sacredbible.org/catholic/index.htm : Official CPDV index URL}
        {--language=en : Language code to store}
        {--bible-version=cpdv : Bible version key to store}
        {--fresh : Delete existing verses for this language/version before importing}';

    protected $description = 'Import the Catholic Public Domain Version from the official public-domain HTML pages.';

    public function handle(): int
    {
        $source = rtrim((string) $this->option('source'), '/');
        $language = strtolower((string) $this->option('language'));
        $version = strtolower((string) $this->option('bible-version'));
        $baseUrl = Str::beforeLast($source, '/');

        $this->info("Reading CPDV index: $source");
        $index = $this->fetch($source);
        $books = $this->parseIndex($index);

        if ($books === []) {
            $this->error('No CPDV book links were found.');

            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            $deleted = VerseModel::where('language', $language)
                ->where('version', $version)
                ->delete();
            $this->info("Deleted $deleted existing $version verses.");
        }

        $total = 0;

        foreach ($books as $book) {
            $url = "$baseUrl/{$book['href']}";
            $this->line("Importing {$book['name']} from $url");

            $html = $this->fetch($url);
            $verses = $this->parseVerses($html, $book['name'], $language, $version);

            if ($verses === []) {
                $this->warn("No verses found for {$book['name']}.");
                continue;
            }

            if ($this->option('fresh')) {
                foreach (array_chunk($verses, 500) as $chunk) {
                    VerseModel::insert($chunk);
                }
            } else {
                foreach ($verses as $verse) {
                    VerseModel::updateOrCreate(
                        [
                            'book' => $verse['book'],
                            'chapter' => $verse['chapter'],
                            'verse' => $verse['verse'],
                            'language' => $verse['language'],
                            'version' => $verse['version'],
                        ],
                        ['text' => $verse['text']]
                    );
                }
            }

            $total += count($verses);
            $this->line("  Imported ".count($verses).' verses.');
        }

        $this->info("Imported $total CPDV verses as language=$language version=$version.");

        return self::SUCCESS;
    }

    /**
     * @return array<int, array{name: string, href: string}>
     */
    private function parseIndex(string $html): array
    {
        preg_match_all('/<A\s+HREF="(?<href>(?:OT|NT)-(?<number>\d+)[^"]+\.htm)"[^>]*>(?<label>.*?)<\/A>/i', $html, $matches, PREG_SET_ORDER);

        $books = [];
        $seen = [];

        foreach ($matches as $match) {
            if ($match['number'] === '00') {
                continue;
            }

            $label = $this->cleanText($match['label']);
            if ($label === 'in color') {
                continue;
            }

            $name = $this->normalizeBookName($label);
            if (isset($seen[$name])) {
                continue;
            }

            $seen[$name] = true;
            $books[] = [
                'name' => $name,
                'href' => $match['href'],
            ];
        }

        return $books;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function parseVerses(string $html, string $book, string $language, string $version): array
    {
        preg_match_all('/\{(?<chapter>\d+):(?<verse>\d+)\}\s*(?<text>.*?)<BR>/is', $html, $matches, PREG_SET_ORDER);

        return array_map(
            fn (array $match): array => [
                'book' => strtolower($book),
                'chapter' => (int) $match['chapter'],
                'verse' => (int) $match['verse'],
                'language' => $language,
                'version' => $version,
                'text' => $this->cleanText($match['text']),
            ],
            $matches
        );
    }

    private function fetch(string $url): string
    {
        $response = Http::timeout(30)
            ->withHeaders(['User-Agent' => 'Bible Study Platform CPDV Importer'])
            ->get($url);

        $response->throw();

        return mb_convert_encoding($response->body(), 'UTF-8', 'Windows-1252');
    }

    private function cleanText(string $text): string
    {
        $text = str_replace("\xc2\xa0", ' ', $text);

        return trim(preg_replace(
            '/\s+/u',
            ' ',
            html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8')
        ) ?? '');
    }

    private function normalizeBookName(string $book): string
    {
        return match ($book) {
            'Acts of the Apostles' => 'Acts',
            default => $book,
        };
    }
}
