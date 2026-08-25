# Sprint 29 - Retrieval Regression Diagnosis

## 1. Executive Decision

**REGRESSION - regression confirmed and primary root causes identified.**

The full-corpus retrieval baseline is worse than the 59-document baseline for the existing six-question evaluation set. Exact reference resolution and direct reference-content retrieval still work, including deuterocanonical references. The regression appears to come from evaluation/query-context mismatch after adding 37k Bible documents, verse-level short-document competition, chapter-level lexical competition, and sparse theological context in standalone verse embeddings. Production retrieval behavior was not changed.

## 2. Sprint 28 Baseline

Current Docker/Postgres corpus:

| Metric | Value |
| --- | ---: |
| Total documents | 37,204 |
| Bible verses | 35,860 |
| Bible chapters | 1,334 |
| Catechism documents | 7 |
| Church Father documents | 3 |
| Embeddings | 37,204 / 37,204 |
| Embedding provider | local |
| Embedding model | sentence-transformers/all-MiniLM-L6-v2 |
| Embedding dimensions | 384 |

Retrieval configuration:

| Setting | Value |
| --- | ---: |
| Hybrid vector weight | 0.70 |
| Hybrid lexical weight | 0.30 |
| Hybrid fetch multiplier | 3 |
| Hybrid minimum score | 0.0 |

Re-run baseline, unsaved:

| Strategy | K | Hit@K | Precision | Recall | MRR | NDCG | Source Coverage | Avg Latency |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| vector | 5 | 0.500 | 0.167 | 0.250 | 0.500 | 0.436 | 0.750 | 109 ms |
| vector | 10 | 0.500 | 0.083 | 0.250 | 0.500 | 0.436 | 0.750 | 22 ms |
| lexical | 5 | 0.333 | 0.100 | 0.250 | 0.208 | 0.186 | 0.250 | 89 ms |
| lexical | 10 | 0.333 | 0.083 | 0.250 | 0.208 | 0.256 | 0.250 | 90 ms |
| hybrid | 5 | 0.500 | 0.167 | 0.250 | 0.417 | 0.384 | 0.750 | 121 ms |
| hybrid | 10 | 0.667 | 0.100 | 0.417 | 0.440 | 0.440 | 0.750 | 129 ms |

Small-corpus baseline was approximately `83.3%` Hit@K for vector/hybrid, so the regression is confirmed for this evaluation set.

## 3. Failed-Question Analysis

Expected references exist in the corpus. `rank` below is rank within top 1000 where found.

| Query | Strategy | Expected | Rank | Dominant observed issue |
| --- | --- | --- | ---: | --- |
| Why did Jesus become man? | lexical | John 1:14, CCC 456 | not top 1000 | Lexical terms match long Bible chapters about man/God more than expected refs. |
| What is grace? | vector | John 1:16 | 19 | Short grace-related verses outrank expected John verse. |
| What is grace? | lexical | John 1:16 | 42 | Chapter documents dominate lexical ranking. |
| What does John say about the Word being God? | vector | John 1:1 | 39 | Related John 1 legacy/nearby verses outrank expected text. |
| What does John say about the Word being God? | vector | John 1:14 | not top 1000 | Query asks doctrine; verse text lacks enough contextual terms. |
| What does John say about the Word being God? | lexical | John 1:1, John 1:14 | not top 1000 | Chapter-level lexical matches dominate. |
| What does John say about the Word being God? | hybrid | John 1:1 | 43 | Hybrid mostly inherits vector miss. |
| What does John say about the Word being God? | hybrid | John 1:14 | 244 | Expected verse is present but weak. |
| What does the Catholic Church teach about the Trinity? | vector | John 1:1 | not top 1000 | Query contains theological topic absent from John 1:1 text. |
| What does the Catholic Church teach about the Trinity? | lexical | John 1:1 | not top 1000 | No lexical match. |
| What does the Catholic Church teach about the Trinity? | hybrid | John 1:1 | not top 1000 | Lexical contributes nothing and vector does not surface John 1:1. |

Top results for the Trinity query:

| Rank | Vector Reference | Type | Length | Score |
| ---: | --- | --- | ---: | ---: |
| 1 | Luke 20:21 | bible_verse | 156 | 0.478 |
| 2 | John 6:70 | bible_verse | 66 | 0.477 |
| 3 | 1 John 5:7 | bible_verse | 116 | 0.473 |
| 4 | James 2:19 | bible_verse | 91 | 0.472 |
| 5 | Acts 15:8 | bible_verse | 99 | 0.457 |
| 6 | 1 John 5 | bible_chapter | 2586 | 0.453 |
| 7 | 1 Timothy 3:15 | bible_verse | 164 | 0.451 |
| 8 | Ephesians 4:5 | bible_verse | 33 | 0.446 |
| 9 | Exodus 30:29 | bible_verse | 104 | 0.442 |
| 10 | Acts 18:25 | bible_verse | 168 | 0.441 |

Lexical returned no Trinity results. Hybrid returned the same vector-led results with normalized vector scores and zero lexical score.

## 4. Document-Length Analysis

| Length Bucket | Source Type | Count |
| --- | --- | ---: |
| <40 | bible_verse | 518 |
| 40-79 | bible_verse | 6,624 |
| 40-79 | catechism | 1 |
| 80-159 | bible_chapter | 1 |
| 80-159 | bible_verse | 19,589 |
| 80-159 | catechism | 1 |
| 80-159 | church_father | 1 |
| 160-319 | bible_chapter | 1 |
| 160-319 | bible_verse | 8,977 |
| 160-319 | catechism | 2 |
| 160-319 | church_father | 1 |
| 320+ | bible_chapter | 1,332 |
| 320+ | bible_verse | 152 |
| 320+ | catechism | 3 |
| 320+ | church_father | 1 |

Short-document facts:

- Documents under 80 chars: `7,143`.
- Bible verses under 80 chars: `7,142`.
- Share of Bible verse documents under 40 chars: `518 / 35,860 = 1.44%`.
- Share of Bible verse documents 40-79 chars: `6,624 / 35,860 = 18.47%`.
- Share of Bible verse documents under 80 chars: `19.91%`.

Representative short documents:

| Reference | Length | Content |
| --- | ---: | --- |
| Job 3:2 | 10 | and spake. |
| John 11:35 | 15 | And Jesus wept. |
| 1 Thessalonians 5:16 | 15 | Always rejoice. |
| Numbers 27:5 | 16 | Who said to him: |
| 1 Chronicles 1:1 | 17 | Adam, Seth, Enos, |

Evidence is mixed, but short verses do appear disproportionately in some failed vector results. For “What is grace?”, 7 of the vector top 10 are under 80 chars. For “What does John say about the Word being God?”, 5 of the vector top 10 are under 80 chars. The Trinity top 10 is less short-heavy, so short length is a contributor rather than the only cause.

## 5. Verse/Chapter Competition

| Query | Strategy | Verses | Chapters | Catechism | Expected Types |
| --- | --- | ---: | ---: | ---: | --- |
| Who is the Lamb of God? | vector | 8 | 1 | 1 | bible_verse |
| Who is the Lamb of God? | lexical | 5 | 4 | 1 | bible_verse |
| Why did Jesus become man? | vector | 9 | 0 | 1 | catechism, bible_verse |
| Why did Jesus become man? | lexical | 0 | 10 | 0 | catechism, bible_verse |
| Why did the Word become flesh? | vector | 10 | 0 | 0 | catechism, bible_verse |
| Why did the Word become flesh? | lexical | 0 | 9 | 1 | catechism, bible_verse |
| What is grace? | vector | 10 | 0 | 0 | catechism, bible_verse |
| What is grace? | lexical | 0 | 10 | 0 | catechism, bible_verse |
| What does John say about the Word being God? | vector | 10 | 0 | 0 | bible_verse |
| What does John say about the Word being God? | lexical | 0 | 10 | 0 | bible_verse |
| Trinity | vector | 9 | 1 | 0 | catechism, bible_verse |
| Trinity | lexical | 0 | 0 | 0 | catechism, bible_verse |

Chapter and verse documents do compete in the same production ranking pool. Lexical search is especially vulnerable to chapter dominance because long chapters contain more matching terms.

## 6. John 1:1 Deep Analysis

Exact reference resolution works. Direct retrieval with the Douay-Rheims `John 1:1` verse content returns:

| Strategy | Rank | Top Reference |
| --- | ---: | --- |
| vector | 1 | John 1:1 |
| lexical | 2 | Hebrews 5:12 |
| hybrid | 1 | John 1:1 |

The failure is not missing data and not deterministic reference resolution. The Trinity query fails because the natural-language theological phrase does not align strongly with the short John 1:1 verse embedding. Lexical contributes no signal because the query does not contain the words in the verse or the reference. Hybrid cannot recover when lexical is empty and vector ranks other Trinitarian-adjacent verses higher.

## 7. Isolated Experiment Results

No production settings were changed.

| Experiment | K | Hit@K | Precision | Recall | MRR | NDCG | Latency | Failed Questions |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: | --- |
| Vector, bible_verse only | 5 | 0.333 | 0.133 | 0.167 | 0.333 | 0.333 | 20 ms | Jesus become man; grace; Word being God; Trinity |
| Vector, bible_verse only | 10 | 0.333 | 0.067 | 0.167 | 0.333 | 0.333 | 22 ms | Jesus become man; grace; Word being God; Trinity |
| Vector, bible_chapter only | 5 | 0.000 | 0.000 | 0.000 | 0.000 | 0.000 | 20 ms | all six |
| Vector, bible_chapter only | 10 | 0.000 | 0.000 | 0.000 | 0.000 | 0.000 | 20 ms | all six |
| Diagnostic verse/chapter weighting | 5 | 0.500 | 0.167 | 0.250 | 0.306 | 0.333 | 114 ms | grace; Word being God; Trinity |
| Diagnostic verse/chapter weighting | 10 | 0.667 | 0.100 | 0.417 | 0.329 | 0.389 | 121 ms | Word being God; Trinity |
| Diagnostic exact-reference boost | 5 | 0.500 | 0.167 | 0.250 | 0.417 | 0.384 | 117 ms | grace; Word being God; Trinity |
| Diagnostic exact-reference boost | 10 | 0.667 | 0.100 | 0.417 | 0.440 | 0.440 | 124 ms | Word being God; Trinity |

Experiment 4, contextual text embeddings, was not run at full corpus scale. The stored pgvector index contains embeddings for current persisted content only. A fair contextual-text experiment would require embedding contextualized text for many documents. Doing that in memory for 37k documents would be slow and would blur the “no embedding regeneration” rule. A future controlled sample experiment is reasonable.

## 8. Root-Cause Hypotheses Ranked By Evidence

1. **Evaluation set is underspecified for the expanded corpus.** Six questions were enough for a 59-document smoke baseline but are not enough for a 37k-document Catholic corpus.
2. **The expected references are sometimes theologically correct but lexically/semantically sparse.** `John 1:1` does not contain “Trinity”; `John 1:14` does not contain “Jesus became man.”
3. **Verse-level embeddings lack surrounding context.** Short verse documents can be meaningful but embed weakly for theological concepts.
4. **Chapter documents dominate lexical retrieval.** Lexical top 10 for several failed questions is almost entirely chapters.
5. **Hybrid fusion inherits vector misses when lexical is empty or chapter-heavy.** Current `0.70 / 0.30` fusion does not add theological expansion or reference inference.
6. **Known legacy John 1 duplicates add ambiguity but are not the primary cause.** They appear in “Word being God” results, but Trinity fails even with Douay-Rheims candidates present.

## 9. Recommended Sprint 30 Changes

Do not immediately tune thresholds. Recommended next work:

1. Build a larger full-corpus retrieval evaluation dataset with exact-lookup, semantic Bible, Catechism, Church Father, deuterocanonical, and cross-source categories.
2. Add diagnostic-only query expansion experiments for doctrinal categories already present in `config/retrieval.php`.
3. Run a controlled contextual-embedding experiment on a small sample, such as reference + title + book/chapter + verse text, without changing production embeddings.
4. Add retrieval filters or profiles for exact Scripture lookup versus theological research, then evaluate them separately.
5. Investigate lexical chapter dominance before altering hybrid weights.

## 10. Risks

- Changing weights before expanding the evaluation set could improve six questions while harming broader retrieval.
- Contextualizing embeddings would require a planned embedding migration and full re-baseline.
- Removing short verses would damage exact Scripture lookup and is not recommended.
- Legacy John 1 duplicates should remain until a migration plan exists for source identity and evaluation expectations.

## 11. Reproducibility Information

Fingerprints from Sprint 28 full corpus:

| Fingerprint | Value |
| --- | --- |
| Evaluation hash | d81dc4dc8e735268c4add8f375c0f72c918e0ad1aea80ce8a985d78a7021a884 |
| Execution hash | 5675867c640a78956a5dc57de47b514881c3ae82f040effad651cd34188f6ef3 |
| Corpus hash | e1d552068d17bd2ae6c6def951440189756fa7a3877d48be627594483f879e3d |
| Security hash | 9865a1cc174c71da3891e72854ddcf5081442c44733f1d25a678753649145be1 |

Implementation inspection:

- Vector scoring: `1 - (embedding <=> query_vector)` with pgvector cosine distance.
- Vector threshold: `whereVectorSimilarTo`.
- Lexical scoring: `ts_rank_cd(search_vector, websearch_to_tsquery('english', query))` plus exact reference/source boosts.
- Lexical index: `knowledge_documents_search_vector_gin`.
- Vector index: `knowledge_documents_embedding_hnsw`.
- Hybrid: fetches `topK * HYBRID_FETCH_MULTIPLIER`, normalizes vector and lexical scores independently by max score, then weighted fusion.
- Source weighting: none in the simple vector/lexical/hybrid services.
- Document-type weighting: none in production.
- Ranking pool: verse and chapter documents compete unless caller supplies filters.

Answer and safety evaluation remained blocked by the environment approval layer and was not bypassed.

## 12. Exact Commands Used

```bash
docker compose exec -T app php artisan embeddings:health
docker compose exec -T app php artisan graph:verify
docker compose exec -T app php artisan retrieval:health
docker compose exec -T app php artisan knowledge:duplicates --source-type=bible_verse --format=json
docker compose exec -T app php artisan evaluate:retrieval --strategy=vector --top-k=5 --save
docker compose exec -T app php artisan evaluate:retrieval --strategy=lexical --top-k=5 --save
docker compose exec -T app php artisan evaluate:retrieval --strategy=hybrid --top-k=5 --save
docker compose exec -T app php artisan evaluate:retrieval --strategy=vector --top-k=10 --save
docker compose exec -T app php artisan evaluate:retrieval --strategy=lexical --top-k=10 --save
docker compose exec -T app php artisan evaluate:retrieval --strategy=hybrid --top-k=10 --save
docker compose exec -T app php artisan evaluate:diagnose --question-id=019fd14b-9719-714d-801c-94a9f9b01f60 --top-k=10 --strategy=all
docker compose exec -T app php -d memory_limit=1G storage/app/sprint29_diagnostics.php
docker compose exec -T app php -d memory_limit=1G storage/app/sprint29_rank_probe.php
docker compose exec -T app php artisan test --compact tests/Feature/RetrievalEvaluationFeatureTest.php tests/Unit/RetrievalEvaluationServiceTest.php tests/Feature/RetrievalDiagnosticsCommandTest.php tests/Feature/ProductionAiEvaluationPlatformTest.php tests/Feature/AgenticAIFrameworkTest.php
docker compose exec -T app php -d memory_limit=1G artisan test --compact tests/Feature/RetrievalEvaluationFeatureTest.php tests/Unit/RetrievalEvaluationServiceTest.php tests/Feature/RetrievalDiagnosticsCommandTest.php tests/Feature/ProductionAiEvaluationPlatformTest.php tests/Feature/AgenticAIFrameworkTest.php
docker compose exec -T app vendor/bin/phpstan analyse --memory-limit=1G
docker compose exec -T app vendor/bin/pint --dirty --format agent
git diff --check
```

Temporary probe scripts were removed after generating this report.
