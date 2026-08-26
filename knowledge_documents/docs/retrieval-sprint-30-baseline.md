# Sprint 30 Preserved Retrieval Baseline

This document preserves the known full-corpus retrieval baseline before Sprint 30 experimental work.

No production retrieval behavior, embeddings, corpus documents, graph behavior, API contracts, agents, MCP behavior, or security policies were changed to create this baseline.

## Corpus

| Metric | Value |
| --- | ---: |
| Total knowledge documents | 37,204 |
| Douay-Rheims Bible documents | 37,143 |
| Bible verses | 35,860 |
| Bible chapters | 1,334 |
| Catechism documents | 7 |
| Church Father documents | 3 |
| Embedding coverage | 100% |
| Embedding provider | local |
| Embedding model | sentence-transformers/all-MiniLM-L6-v2 |
| Embedding dimensions | 384 |
| Graph nodes | 37,204 |
| Graph edges | 0 |
| Broken graph references | 0 |

## Retrieval Configuration

| Setting | Value |
| --- | ---: |
| Hybrid vector weight | 0.70 |
| Hybrid lexical weight | 0.30 |
| Hybrid fetch multiplier | 3 |
| Hybrid minimum score | 0.0 |

## Sprint 28 Six-Question Baseline

| Strategy | K | Hit@K | Precision | Recall | MRR | NDCG | Source Coverage | Avg Latency |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| vector | 5 | 0.500 | 0.167 | 0.250 | 0.500 | 0.436 | 0.750 | 107 ms |
| vector | 10 | 0.500 | 0.083 | 0.250 | 0.500 | 0.436 | 0.750 | 109 ms |
| lexical | 5 | 0.333 | 0.100 | 0.250 | 0.208 | 0.186 | 0.250 | 106 ms |
| lexical | 10 | 0.333 | 0.083 | 0.250 | 0.208 | 0.256 | 0.250 | 115 ms |
| hybrid | 5 | 0.500 | 0.167 | 0.250 | 0.417 | 0.384 | 0.750 | 251 ms |
| hybrid | 10 | 0.500 | 0.083 | 0.250 | 0.417 | 0.384 | 0.750 | 270 ms |

## Fingerprints

| Fingerprint | Value |
| --- | --- |
| Evaluation hash | d81dc4dc8e735268c4add8f375c0f72c918e0ad1aea80ce8a985d78a7021a884 |
| Execution hash | 5675867c640a78956a5dc57de47b514881c3ae82f040effad651cd34188f6ef3 |
| Corpus hash | e1d552068d17bd2ae6c6def951440189756fa7a3877d48be627594483f879e3d |
| Security hash | 9865a1cc174c71da3891e72854ddcf5081442c44733f1d25a678753649145be1 |

## Known Regression

`John 1:1` is present and exact reference resolution works, but semantic theological queries such as “What does the Catholic Church teach about the Trinity?” do not retrieve `John 1:1` in the top 10 for vector, lexical, or hybrid retrieval.
