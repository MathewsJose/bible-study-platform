# Sprint 33 - Deterministic Scripture Routing and Retrieval Fusion

## 1. Executive Decision

PASS

## 2. Objective

Separate explicit Scripture/reference queries from doctrinal semantic queries in an isolated experimental retrieval layer.

## 3. Problem Separation

Exact references are routed through deterministic reference resolution. Doctrinal and general semantic queries continue through read-only production retrieval services.

## 4. Production Baseline

- Documents: `37204`
- Embedded documents: `37204`
- Embedding dimensions: `384`

## 5. Sprint 31 Comparison

Sprint 31 persistent contextual retrieval remained a regression and was not promoted.

## 6. Sprint 32 Comparison

Sprint 32 combined expansion was inconclusive: Hit@5 `0.469`, MRR@5 `0.369`, NDCG@5 `0.377`.

## 7. Query Classification

| Route | Count |
| --- | ---: |
| exact_reference | 11 |
| reference_contextual | 6 |
| doctrinal_semantic | 35 |
| general_semantic | 29 |
| unclassified | 0 |

## 8. Routing Architecture

Modes: `baseline`, `exact_reference_route`, `reference_fusion`, `doctrinal_route`, `hybrid_router`.

## 9. Fusion Scoring

Fusion scores are deterministic and configured in `config/retrieval_sprint33.php`.

## 10. Overall Metrics

| Mode | K | Questions | Hit Rate | Precision | Recall | MRR | NDCG | Source Coverage | Latency |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| baseline | k5 | 81 | 0.432 | 0.089 | 0.401 | 0.362 | 0.360 | 0.938 | 13512 ms |
| baseline | k10 | 81 | 0.481 | 0.051 | 0.457 | 0.369 | 0.379 | 0.951 | 12069 ms |
| exact_reference_route | k5 | 81 | 0.605 | 0.237 | 0.586 | 0.516 | 0.528 | 0.938 | 10940 ms |
| exact_reference_route | k10 | 81 | 0.654 | 0.193 | 0.642 | 0.523 | 0.546 | 0.951 | 11693 ms |
| reference_fusion | k5 | 81 | 0.617 | 0.131 | 0.599 | 0.556 | 0.561 | 0.938 | 11550 ms |
| reference_fusion | k10 | 81 | 0.667 | 0.072 | 0.654 | 0.562 | 0.580 | 0.951 | 11742 ms |
| doctrinal_route | k5 | 81 | 0.481 | 0.099 | 0.457 | 0.372 | 0.382 | 0.932 | 15105 ms |
| doctrinal_route | k10 | 81 | 0.506 | 0.052 | 0.481 | 0.375 | 0.390 | 0.944 | 15072 ms |
| hybrid_router | k5 | 81 | 0.654 | 0.247 | 0.636 | 0.561 | 0.575 | 0.938 | 12647 ms |
| hybrid_router | k10 | 81 | 0.679 | 0.195 | 0.667 | 0.564 | 0.585 | 0.938 | 13107 ms |

## 11. Per-Route Metrics

Per-route metrics are included in JSON output under each mode.

## 12. John 1:1 Diagnostics

John 1:1 diagnostics are included in JSON output.

## 13. False-Positive Tests

- False positives: `0 / 7`

## 14. Legacy Source Resolution

- Default source: `Douay-Rheims Bible`
- Explicit legacy source: `Bible`

## 15. Latency

Latency is command-level diagnostic timing and not a production SLO.

## 16. Production Isolation Verification

No corpus import, embedding generation, graph rebuild, production retrieval promotion, API change, MCP change, agent change, or legacy Bible mutation occurred.

## 17. Tests

See final sprint response for executed commands.

## 18. PHPStan

See final sprint response for executed commands.

## 19. Pint

See final sprint response for executed commands.

## 20. Diff Check

See final sprint response for executed commands.

## 21. Limitations

The experiment still relies on existing query embeddings and lexical search behavior. It does not solve source balancing or theological graph traversal.

## 22. Recommendation for Sprint 34

Keep deterministic reference routing experimental and investigate a production-safe exact-reference pre-router only for direct citation requests.
