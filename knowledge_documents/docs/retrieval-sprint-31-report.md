# Sprint 31 - Persistent Contextual Retrieval Experiment

## 1. Executive Decision

REGRESSION

The persistent `plus_minus_1` contextual retrieval experiment completed successfully, but it does not recover the Sprint 28 full-corpus retrieval regression. Contextual K=5 hit rate, precision, MRR, and NDCG are below the Sprint 28 vector and hybrid baselines.

The experiment remains isolated and must not be promoted to production retrieval.

## 2. Contextual Index Size

- Window: `plus_minus_1`
- Indexed contextual documents: `35,809`
- Embedded contextual documents: `35,809`
- Failed contextual embeddings: `0`
- Fingerprint: `3f93f85c1ba79d373bace37249640aca595c1f2ba79d22c6495602c59d2ce2bb`

## 3. Embedding Model

- Provider: `local`
- Model: `sentence-transformers/all-MiniLM-L6-v2`
- Dimensions: `384`
- Contextual embeddings are stored only in `retrieval_contextual_documents`.

## 4. Benchmark Dataset

- Dataset version: `retrieval-sprint-30-v1`
- Defined questions: `81`
- Evaluated questions: `81`
- Missing expected references: `0`

## 5. Contextual Retrieval Metrics

| K | Hit Rate | Precision | Recall | MRR | NDCG | Source Coverage | Latency |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| K5 | 0.321 | 0.067 | 0.321 | 0.229 | 0.251 | 0.889 | 14278 ms |
| K10 | 0.346 | 0.036 | 0.346 | 0.233 | 0.259 | 0.889 | 2397 ms |

## 6. Sprint 28 Baseline

| Method | K | Hit Rate | Precision | Recall | MRR | NDCG | Source Coverage | Latency |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| Vector | K5 | 0.500 | 0.167 | 0.250 | 0.500 | 0.436 | 0.750 | 107 ms |
| Lexical | K5 | 0.333 | 0.100 | 0.250 | 0.208 | 0.186 | 0.750 | 106 ms |
| Hybrid | K5 | 0.500 | 0.167 | 0.250 | 0.417 | 0.384 | 0.750 | 251 ms |

## 7. Side-by-Side Comparison

| Metric | Contextual K5 | Sprint 28 Vector K5 | Sprint 28 Hybrid K5 | Result |
| --- | ---: | ---: | ---: | --- |
| Hit Rate | 0.321 | 0.500 | 0.500 | worse |
| Precision | 0.067 | 0.167 | 0.167 | worse |
| Recall | 0.321 | 0.250 | 0.250 | better |
| MRR | 0.229 | 0.500 | 0.417 | worse |
| NDCG | 0.251 | 0.436 | 0.384 | worse |
| Source Coverage | 0.889 | 0.750 | 0.750 | better |

Contextual retrieval retrieves a wider source mix and has slightly higher recall, but it ranks expected answers too low. The ranking weakness is severe enough to classify the experiment as a regression.

## 8. Category-Level Behavior

| Category | Questions | Hit Rate | Recall | MRR | NDCG | Source Coverage |
| --- | ---: | ---: | ---: | ---: | ---: | ---: |
| baptism | 4 | 0.250 | 0.250 | 0.042 | 0.089 | 1.000 |
| christology | 8 | 0.125 | 0.125 | 0.063 | 0.079 | 0.750 |
| church | 5 | 0.000 | 0.000 | 0.000 | 0.000 | 1.000 |
| cross_context | 8 | 0.000 | 0.000 | 0.000 | 0.000 | 0.375 |
| deuterocanonical | 7 | 0.714 | 0.714 | 0.493 | 0.545 | 1.000 |
| eucharist | 6 | 0.333 | 0.333 | 0.222 | 0.250 | 1.000 |
| exact_scripture | 15 | 0.600 | 0.600 | 0.427 | 0.469 | 1.000 |
| mary | 5 | 0.400 | 0.400 | 0.400 | 0.384 | 1.000 |
| old_testament | 5 | 0.600 | 0.600 | 0.440 | 0.477 | 1.000 |
| prayer | 4 | 0.250 | 0.250 | 0.250 | 0.250 | 1.000 |
| sacraments | 4 | 0.250 | 0.250 | 0.050 | 0.097 | 1.000 |
| salvation | 5 | 0.400 | 0.400 | 0.220 | 0.258 | 1.000 |
| trinity | 5 | 0.200 | 0.200 | 0.100 | 0.126 | 0.600 |

Best category: `deuterocanonical`.

Worst categories: `church` and `cross_context`.

Contextualization primarily helps direct Bible/reference-adjacent categories, especially deuterocanonical and exact Scripture questions. It does not solve cross-source theological retrieval.

## 9. John 1:1 Result

`John 1:1` did not appear in the top 10 for the three John 1:1 diagnostic queries.

For `What does John 1:1 say?`, top competitors were:

| Rank | Reference | Score |
| ---: | --- | ---: |
| 1 | 1 John 1:2 | 0.743 |
| 2 | 1 John 1:4 | 0.738 |
| 3 | John 1:22 | 0.738 |
| 4 | John 1:51 | 0.724 |
| 5 | John 1:23 | 0.717 |
| 9 | 1 John 1:1 | 0.692 |

For semantic Word/divinity queries, top competitors were mostly Psalms references. This confirms the known Sprint 29 finding: short verse semantic retrieval still lacks enough theological grounding, and neighboring verse context alone does not fix John 1:1 retrieval.

## 10. Deuterocanonical Behavior

Deuterocanonical questions were the strongest category:

- Hit rate: `0.714`
- Recall: `0.714`
- MRR: `0.493`
- NDCG: `0.545`

Failures remained for:

- `Where does Wisdom say love justice you that judge the earth?`
- `Where does Sirach begin all wisdom is from the Lord God?`

## 11. Latency

- Contextual K5 benchmark latency: `14278 ms`
- Contextual K10 benchmark latency: `2397 ms`

These are command-level diagnostic timings and include local query embedding and pgvector search. They are not production SLOs.

## 12. Citation Integrity

- Invalid contextual references: `0`
- Contextual search returns the original `source_document_id`, `reference`, `source_name`, and `source_type`.
- No synthetic citation identifier is used as the answer citation target.

## 13. Production Isolation Verification

Production corpus after benchmark:

- `knowledge_documents`: `37,204`
- Production embeddings: `37,204`
- Production embedding dimensions: min `384`, max `384`
- Bible books: `73`
- Bible chapters: `1,334`
- Bible verses: `35,860`
- Douay-Rheims verses: `35,809`
- Legacy John 1 verses: `51`

Duplicate status:

- Within-source Bible verse duplicates: `0`
- Across-source duplicates: `51`
- Across-source duplicates are the known legitimate legacy `Bible` plus `Douay-Rheims Bible` John 1 overlap.

Graph status:

- Nodes: `37,204`
- Edges: `0`
- Broken references: `0`
- Duplicate relationships: `0`

Reference ordering check:

- `John 1:1` returns `Douay-Rheims Bible` before legacy `Bible`.
- `John 3:16` exists in `Douay-Rheims Bible`.
- `Tobit 1:1` exists in `Douay-Rheims Bible`.

No production retrieval services, production ranking configuration, `knowledge_documents` schema, answer generation, agents, MCP, graph logic, or evaluation thresholds were changed during this completion pass.

## 14. Test and Static Analysis Results

Focused Sprint 31 tests:

```bash
php artisan test --compact tests/Feature/ContextualRetrievalIndexTest.php tests/Unit/ContextualRetrievalExperimentTest.php
```

Result:

```json
{"tool":"pest","result":"passed","tests":8,"passed":8,"assertions":38}
```

Scoped Sprint 31 PHPStan:

```bash
php vendor/bin/phpstan analyse app/Application/Knowledge/Retrieval/Experiments/ContextualIndexBenchmarkService.php app/Application/Knowledge/Retrieval/Experiments/ContextualRetrievalEmbeddingService.php app/Console/Commands/EvaluateContextualIndexCommand.php tests/Feature/ContextualRetrievalIndexTest.php --memory-limit=1G
```

Result:

```json
{"tool":"phpstan","result":"passed","errors":0}
```

Pint:

```bash
docker compose exec app vendor/bin/pint --dirty --format agent
```

Result:

```json
{"tool":"pint","result":"passed"}
```

Diff check:

```bash
git diff --check
```

Result: passed.

Full PHPStan:

```bash
php vendor/bin/phpstan analyse --memory-limit=1G
```

Result: failed with unrelated pre-existing typing issues in agent replay, evaluation, regression comparison, source inventory, and related services. Sprint 31 scoped files pass.

## 15. Known Limitations

- `plus_minus_1` context is still Bible-only and does not include Catechism or Church Father enrichment.
- The experiment improves source coverage and recall but harms ranking quality.
- Exact-reference behavior is still better handled by deterministic reference resolution/boosting than by contextual vector search alone.
- Cross-source questions fail because the contextual index contains Bible contextual rows only.
- John 1:1 semantic retrieval is not fixed by adjacent verse context.

## 16. Recommendation for Sprint 32

Sprint 32 should focus on retrieval architecture rather than more blind contextual windows:

1. Add deterministic exact-reference routing before semantic retrieval.
2. Build source-aware multi-stage retrieval: exact reference, lexical candidate recall, vector recall, then reranking.
3. Add cross-source contextual units that explicitly connect Bible, Catechism, and Church Father references only where provenance already supports the relationship.
4. Evaluate reranking or query decomposition for theological questions before promoting any contextual retrieval path.
