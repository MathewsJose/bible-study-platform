<?php

declare(strict_types=1);

namespace App\Application\Knowledge\Retrieval\Experiments;

use InvalidArgumentException;

final readonly class DoctrinalQueryExpansionService
{
    /**
     * @return list<string>
     */
    public function modes(): array
    {
        return array_values(array_map('strval', (array) config('retrieval_sprint32.modes', [])));
    }

    public function version(): string
    {
        return (string) config('retrieval_sprint32.experiment_version', 'retrieval-sprint-32-v1');
    }

    public function fingerprint(): string
    {
        return hash('sha256', json_encode(config('retrieval_sprint32'), JSON_THROW_ON_ERROR));
    }

    public function expand(string $query, string $mode): QueryExpansionResult
    {
        $mode = trim($mode);

        if (! in_array($mode, $this->modes(), true)) {
            throw new InvalidArgumentException('Unsupported query expansion mode: '.$mode);
        }

        $terms = [];
        $reasons = [];
        $profiles = [];

        if ($mode === 'reference_expansion' || $mode === 'combined') {
            [$referenceTerms, $referenceReasons] = $this->referenceExpansionTerms($query);
            $terms = [...$terms, ...$referenceTerms];
            $reasons = [...$reasons, ...$referenceReasons];
        }

        if ($mode === 'lexical_expansion' || $mode === 'combined') {
            [$lexicalTerms, $lexicalReasons] = $this->lexicalExpansionTerms($query);
            $terms = [...$terms, ...$lexicalTerms];
            $reasons = [...$reasons, ...$lexicalReasons];
        }

        if ($mode === 'doctrinal_bridge' || $mode === 'combined') {
            foreach ($this->matchingProfiles($query) as $profile) {
                $terms = [...$terms, ...$profile->terms];
                $reasons[] = $profile->reason;
                $profiles[] = $profile->identifier;
            }
        }

        $terms = $this->uniqueLimited($terms);
        $reasons = $this->unique($reasons);
        $profiles = $this->unique($profiles);
        $expandedQuery = trim(implode(' ', array_filter([$query, implode(' ', $terms)])));

        return new QueryExpansionResult(
            originalQuery: $query,
            mode: $mode,
            expandedQuery: $expandedQuery,
            terms: $terms,
            reasons: $reasons,
            profiles: $profiles,
            configVersion: $this->version(),
            configFingerprint: $this->fingerprint(),
            queryDriftScore: $this->queryDriftScore($query, $terms),
        );
    }

    /**
     * @return array{0: list<string>, 1: list<string>}
     */
    private function referenceExpansionTerms(string $query): array
    {
        $terms = [];

        foreach ((array) config('retrieval_sprint32.reference_patterns', []) as $pattern) {
            preg_match_all((string) $pattern, $query, $matches);
            $terms = [...$terms, ...array_values(array_map('strval', $matches[0] ?? []))];
        }

        foreach ($terms as $term) {
            if (preg_match('/^(.+)\s+\d+:\d+$/', $term, $matches) === 1) {
                $chapter = preg_replace('/:\d+$/', '', $term);
                if (is_string($chapter)) {
                    $terms[] = $chapter;
                }
            }
        }

        return [
            $this->unique($terms),
            $terms === [] ? [] : ['Preserved explicit references from the original query.'],
        ];
    }

    /**
     * @return array{0: list<string>, 1: list<string>}
     */
    private function lexicalExpansionTerms(string $query): array
    {
        preg_match_all('/[A-Za-z][A-Za-z0-9]+/', mb_strtolower($query), $matches);
        $minimumLength = (int) config('retrieval_sprint32.lexical.minimum_token_length', 3);
        $stopwords = array_fill_keys(array_map('strval', (array) config('retrieval_sprint32.lexical.stopwords', [])), true);

        $tokens = array_values(array_filter(
            array_map('strval', $matches[0] ?? []),
            static fn (string $token): bool => mb_strlen($token) >= $minimumLength && ! isset($stopwords[$token]),
        ));

        return [
            $this->unique($tokens),
            $tokens === [] ? [] : ['Added deterministic lexical variants already present in the query.'],
        ];
    }

    /**
     * @return list<QueryExpansionProfile>
     */
    private function matchingProfiles(string $query): array
    {
        $normalizedQuery = mb_strtolower($query);
        $matches = [];

        foreach ((array) config('retrieval_sprint32.profiles', []) as $profileConfig) {
            $profile = QueryExpansionProfile::fromConfig((array) $profileConfig);

            foreach ($profile->triggers as $trigger) {
                if ($trigger !== '' && str_contains($normalizedQuery, mb_strtolower($trigger))) {
                    $matches[] = $profile;
                    break;
                }
            }
        }

        return $matches;
    }

    /**
     * @param  list<string>  $terms
     * @return list<string>
     */
    private function uniqueLimited(array $terms): array
    {
        return array_slice($this->unique($terms), 0, max(0, (int) config('retrieval_sprint32.max_expansion_terms', 18)));
    }

    /**
     * @param  list<string>  $values
     * @return list<string>
     */
    private function unique(array $values): array
    {
        $seen = [];
        $unique = [];

        foreach ($values as $value) {
            $value = trim($value);
            $key = mb_strtolower($value);

            if ($value === '' || isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $unique[] = $value;
        }

        return $unique;
    }

    /**
     * @param  list<string>  $terms
     */
    private function queryDriftScore(string $query, array $terms): float
    {
        preg_match_all('/[A-Za-z][A-Za-z0-9]+/', $query, $matches);
        $originalTokenCount = max(1, count($this->unique(array_map('strval', $matches[0] ?? []))));

        return round(count($terms) / $originalTokenCount, 6);
    }
}
