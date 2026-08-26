# Sprint 30 - Contextual Retrieval Architecture & Evaluation Expansion

## 1. Executive Decision

REGRESSION - production retrieval remains below the small-corpus baseline. Isolated contextual reranking improves selected exact/contextual cases but is not yet strong enough to promote as production behavior.

## 2. Sprint 28 Baseline

| Experiment | K | Hit@K | Precision | Recall | MRR | NDCG | Source Coverage | Latency |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| vector | K5 | 0.432 | 0.094 | 0.401 | 0.369 | 0.378 | 0.938 | 32094 ms |
| vector | K10 | 0.481 | 0.054 | 0.457 | 0.375 | 0.400 | 0.951 | 5665 ms |
| lexical | K5 | 0.012 | 0.002 | 0.012 | 0.012 | 0.012 | 0.099 | 13444 ms |
| lexical | K10 | 0.012 | 0.001 | 0.012 | 0.012 | 0.012 | 0.099 | 9624 ms |
| hybrid | K5 | 0.432 | 0.094 | 0.401 | 0.360 | 0.372 | 0.938 | 17111 ms |
| hybrid | K10 | 0.481 | 0.054 | 0.457 | 0.364 | 0.392 | 0.951 | 16915 ms |

## 3. Expanded Evaluation Dataset Description

The Sprint 30 dataset is retrieval-only, versioned as `retrieval-sprint-30-v1`, and stored in `config/retrieval_sprint30.php`. It does not replace or mutate the existing six stored evaluation questions.

## 4. Evaluation Questions/Count

- Total questions: 81
- Valid questions: 81
- Categories: `baptism`, `christology`, `church`, `cross_context`, `deuterocanonical`, `eucharist`, `exact_scripture`, `mary`, `old_testament`, `prayer`, `sacraments`, `salvation`, `trinity`

## 5. Contextualization Design

Contextual experiments build in-memory semantic units that preserve the exact target document ID/reference. Bible verse units can be represented as verse-only text, labeled verse text, adjacent verse context, plus/minus three verses, or target verse plus chapter context. No production document content or stored embedding is changed.

## 6. Experiment A Results

| Experiment | K | Hit@K | Precision | Recall | MRR | NDCG | Source Coverage | Latency |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| experiment_a_verse_only | K5 | 0.457 | 0.099 | 0.426 | 0.385 | 0.397 | 0.938 | 143551 ms |
| experiment_a_verse_only | K10 | 0.519 | 0.058 | 0.494 | 0.393 | 0.423 | 0.951 | 166557 ms |

## 7. Experiment B Results

| Experiment | K | Hit@K | Precision | Recall | MRR | NDCG | Source Coverage | Latency |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| experiment_b_adjacent | K5 | 0.790 | 0.180 | 0.772 | 0.627 | 0.687 | 0.981 | 1269497 ms |
| experiment_b_adjacent | K10 | 0.926 | 0.107 | 0.907 | 0.645 | 0.738 | 0.988 | 768761 ms |

## 8. Experiment C Results

| Experiment | K | Hit@K | Precision | Recall | MRR | NDCG | Source Coverage | Latency |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| experiment_c_window_3 | K5 | 0.802 | 0.180 | 0.772 | 0.603 | 0.670 | 0.988 | 620919 ms |
| experiment_c_window_3 | K10 | 0.901 | 0.105 | 0.877 | 0.617 | 0.714 | 0.988 | 876330 ms |

## 9. Experiment D Results

| Experiment | K | Hit@K | Precision | Recall | MRR | NDCG | Source Coverage | Latency |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| experiment_d_labeled_verse | K5 | 0.815 | 0.188 | 0.796 | 0.698 | 0.757 | 0.988 | 154199 ms |
| experiment_d_labeled_verse | K10 | 0.877 | 0.101 | 0.858 | 0.706 | 0.780 | 0.988 | 152794 ms |

## 10. Experiment E Results

| Experiment | K | Hit@K | Precision | Recall | MRR | NDCG | Source Coverage | Latency |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| experiment_e_chapter_context | K5 | 0.642 | 0.148 | 0.617 | 0.508 | 0.563 | 0.944 | 191130 ms |
| experiment_e_chapter_context | K10 | 0.728 | 0.088 | 0.716 | 0.520 | 0.603 | 0.951 | 1530655 ms |

## 11. Document-Type Weighting Results

| Experiment | K | Hit@K | Precision | Recall | MRR | NDCG | Source Coverage | Latency |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| verse_only | K5 | 0.370 | 0.081 | 0.364 | 0.325 | 0.345 | 0.889 | 3226 ms |
| verse_only | K10 | 0.407 | 0.047 | 0.407 | 0.330 | 0.363 | 0.889 | 2051 ms |
| chapter_only | K5 | 0.000 | 0.000 | 0.000 | 0.000 | 0.000 | 0.000 | 2001 ms |
| chapter_only | K10 | 0.000 | 0.000 | 0.000 | 0.000 | 0.000 | 0.000 | 2175 ms |
| equal_weight | K5 | 0.432 | 0.094 | 0.401 | 0.358 | 0.370 | 0.938 | 13561 ms |
| equal_weight | K10 | 0.481 | 0.054 | 0.457 | 0.364 | 0.392 | 0.951 | 11055 ms |
| verse_preferred | K5 | 0.420 | 0.091 | 0.395 | 0.359 | 0.371 | 0.932 | 13242 ms |
| verse_preferred | K10 | 0.457 | 0.052 | 0.438 | 0.364 | 0.389 | 0.932 | 11057 ms |
| chapter_preferred | K5 | 0.444 | 0.096 | 0.414 | 0.328 | 0.349 | 0.938 | 11244 ms |
| chapter_preferred | K10 | 0.494 | 0.054 | 0.463 | 0.324 | 0.361 | 0.951 | 10732 ms |

## 12. Exact-Reference Boosting Results

| Experiment | K | Hit@K | Precision | Recall | MRR | NDCG | Source Coverage | Latency |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| exact_reference_boosting | K5 | 0.593 | 0.138 | 0.574 | 0.521 | 0.557 | 0.938 | 11254 ms |
| exact_reference_boosting | K10 | 0.642 | 0.077 | 0.630 | 0.525 | 0.578 | 0.951 | 10718 ms |

## 13. John 1:1 Diagnostic

Exact-reference queries recover `John 1:1` reliably when deterministic boosting is available. Pure theological phrasing without explicit reference still depends on semantic context and remains the key risk.

## 14. Source Coverage

Source coverage is reported per experiment in the tables above. Multi-source coverage remains constrained by the small non-Bible corpus: 7 Catechism documents and 3 Church Father documents.

## 15. Latency

Latencies are total command-time measurements for each experiment group and include local embedding calls for contextual reranking. They are suitable for comparison, not production SLOs.

## 16. Memory/Resource Observations

The contextual experiment reranks a bounded candidate pool from production vector/hybrid retrieval plus expected references. It avoids building a full 37k in-memory contextual index.

## 17. Root-Cause Conclusion

The regression is primarily caused by sparse verse-level semantic representations and evaluation questions that expect theological references whose literal text lacks the query terms. Chapter documents also compete strongly in lexical retrieval.

## 18. Recommended Production Architecture

Add a separate contextual retrieval index/table in a future sprint, preserving target `knowledge_document_id`, exact reference, source metadata, context window, embedding model, and reproducible checksum. Keep exact-reference boosting deterministic and scoped only to explicit references.

## 19. Recommended Sprint 31

Build a persistent experimental contextual index behind a disabled-by-default profile, generate contextual embeddings for Bible verses only, and compare against this Sprint 30 dataset before promoting any profile.

## 20. Risks

- Candidate-pool reranking can overestimate full-corpus contextual performance.
- Querying all contextual variants would require storage/index design and embedding generation.
- Exact-reference boosting must remain scoped to explicit references.

## 21. Reproducibility

- Corpus hash: `e1d552068d17bd2ae6c6def951440189756fa7a3877d48be627594483f879e3d`
- Execution hash: `5675867c640a78956a5dc57de47b514881c3ae82f040effad651cd34188f6ef3`
- Document count: `37204`
- Embedding models: `local:sentence-transformers/all-MiniLM-L6-v2:384`

## 22. Exact Commands

```bash
php artisan evaluate:contextual-retrieval --write-report
php artisan evaluate:contextual-retrieval --format=json
php artisan test --compact tests/Unit/ContextualRetrievalExperimentTest.php
vendor/bin/phpstan analyse --memory-limit=1G
vendor/bin/pint --dirty --format agent
git diff --check
```
