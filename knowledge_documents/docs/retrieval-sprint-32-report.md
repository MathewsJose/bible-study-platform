# Sprint 32 - Query Expansion and Doctrinal Semantic Bridge Experiment

## Decision

INCONCLUSIVE

The isolated combined expansion mode improved full-dataset Hit@5 versus the current hybrid baseline, but it did not beat the Sprint 28 vector baseline MRR target and it introduced visible source concentration. This should not be promoted into production retrieval.

## Scope

This sprint added an isolated experiment only. It does not modify production retrieval algorithms, ranking thresholds, embeddings, corpus records, graph behavior, answer generation, agents, MCP, security, observability, evaluation thresholds, or Core API contracts.

## Reproducibility

- Experiment version: `retrieval-sprint-32-v1`
- Config fingerprint: `3de7d8986e56c60bb971af77418b1b426580bf1b6de3af3334bb644221f80767`
- Dataset version: `retrieval-sprint-30-v1`
- Dataset fingerprint: `3a89e8781d0ecf6cf6d455bc37d73fce3a05627d88afdfaa224c5802ad000fa0`
- Corpus fingerprint: `4857dfb3d31ebf60928a641cbeb25e023401e5ac5d2a32604cb549f9a66dad0a`
- Retriever used by the experiment: `hybrid`
- Minimum score: `0`

## Full 81-Question Metrics

| Mode | K | Questions | Hit Rate | Precision | Recall | MRR | NDCG | Source Coverage | Latency |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| baseline | K5 | 81 | 0.432 | 0.089 | 0.401 | 0.360 | 0.359 | 0.938 | 11507 ms |
| baseline | K10 | 81 | 0.481 | 0.051 | 0.457 | 0.364 | 0.375 | 0.951 | 11430 ms |
| reference_expansion | K5 | 81 | 0.432 | 0.089 | 0.401 | 0.360 | 0.359 | 0.938 | 11405 ms |
| reference_expansion | K10 | 81 | 0.481 | 0.051 | 0.457 | 0.364 | 0.375 | 0.951 | 11653 ms |
| lexical_expansion | K5 | 81 | 0.457 | 0.094 | 0.432 | 0.379 | 0.382 | 0.932 | 11949 ms |
| lexical_expansion | K10 | 81 | 0.519 | 0.053 | 0.494 | 0.387 | 0.402 | 0.944 | 12536 ms |
| doctrinal_bridge | K5 | 81 | 0.444 | 0.091 | 0.414 | 0.353 | 0.356 | 0.938 | 13079 ms |
| doctrinal_bridge | K10 | 81 | 0.469 | 0.049 | 0.444 | 0.354 | 0.365 | 0.938 | 16291 ms |
| combined | K5 | 81 | 0.469 | 0.096 | 0.444 | 0.369 | 0.377 | 0.932 | 14219 ms |
| combined | K10 | 81 | 0.506 | 0.052 | 0.481 | 0.374 | 0.389 | 0.944 | 15369 ms |

## Interpretation

Lexical expansion is the strongest isolated mode in this pass. The doctrinal bridge improved Hit@5 slightly over baseline but reduced MRR, which means some relevant references were found but pushed lower. Combined expansion produced the best Hit@5, Recall@5, and NDCG@5 among Sprint 32 modes, but its MRR stayed below the Sprint 28 vector baseline target.

Reference expansion was neutral in aggregate. It preserves explicit references in the query but does not currently perform exact-reference boosting, so it should not be treated as a reference resolver.

## Combined-Mode Diagnostics

- Source concentration: `0.85679`
- Top source bucket: `bible_verse|Douay-Rheims Bible` with `347 / 405` top-5 result slots
- Average query drift: `1.044575`
- Maximum query drift: `2.75`
- Drift warnings: `q066`, `q069`, `q070`, `q071`, `q072`, `q073`

Representative false positives:

- `q003`: expected `CCC 456`, top result was `John 1:14`
- `q004`: expected `CCC 454`, top result was `John 3:6`
- `q014`: expected `John 6:51`, top result was `John 6:34`
- `q015`: expected `John 6:53`, top result was `John 6:54`
- `q019`: expected `1 Corinthians 11:24`, top result was `Acts 23:6`

## John 1:1 Diagnostic

Combined mode finds `John 1:1` for doctrinal Word/divinity prompts but still fails direct exact-reference prompts:

| Query | John 1:1 Rank | Top Reference | Top Source |
| --- | ---: | --- | --- |
| What does John 1:1 say? | not found in top 10 | John 1:32 | Bible |
| Explain John 1:1. | not found in top 10 | John 1:6 | Douay-Rheims Bible |
| Why does John 1:1 teach that the Word is God? | 1 | John 1:1 | Bible |
| Why do Christians believe that the Word is divine? | 1 | John 1:1 | Bible |
| What does the Bible teach about the divinity of the Word? | 1 | John 1:1 | Bible |
| How does the Gospel of John present the Word? | not found in top 10 | Mark 1:14 | Douay-Rheims Bible |

This confirms query expansion alone is not enough for exact reference lookup. The existing deterministic reference-resolution path remains the correct production path for direct citation requests.

## Production State

- Knowledge documents: `37204`
- Embedded documents: `37204`
- Embedding dimensions: `384`
- Bible verses: `35860`
- Bible chapters: `1334`
- Catechism documents: `7`
- Church Father documents: `3`

No corpus import, embedding generation, graph rebuild, or production retrieval promotion occurred during this sprint.

## Commands

```bash
php artisan retrieval:doctrinal-expand --query="Why does John teach that the Word was God?" --mode=combined --format=json
docker compose exec app php artisan evaluate:doctrinal-bridge --mode=all
docker compose exec app php artisan evaluate:doctrinal-bridge --mode=combined --format=json
```

## Recommendation

Do not promote Sprint 32 query expansion to production. Keep it as a diagnostic tool and use the results to design a safer next experiment around explicit reference routing, source-type balancing, and Catechism-aware filtering.
