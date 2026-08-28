# Sprint 35 - Controlled Production Activation of Deterministic Scripture Routing

## 1. Executive Decision

PASS

## 2. Activation Configuration

- Router state: controlled diagnostic activation only
- Feature flag: `RETRIEVAL_SCRIPTURE_ROUTER_ENABLED`
- Config key: `retrieval.scripture_router.enabled`
- Default: `false`
- Mode: `hybrid_router`
- Global default changed: `false`

## 3. Rollback Procedure

Set RETRIEVAL_SCRIPTURE_ROUTER_ENABLED=false and restart or reload the application if configuration is cached. No database, corpus, embedding, graph, or code rollback is required.

## 4. Architecture

When the flag is disabled, `RetrievalEngine` uses the existing production retrieval path. When enabled, `ScriptureRoutingRetrievalAdapter` calls the Sprint 33 `hybrid_router`, converts routed candidates back into normal retrieval context, and keeps the existing answer and citation pipeline. Router failures are logged and fall back to the original retrieval path.

## 5. Baseline Metrics

- Hit@5: `0.444444`
- MRR@5: `0.38107`
- NDCG@5: `0.378002`
- Hit@10: `0.493827`

## 6. Integrated Metrics

- Hit@5: `0.654321`
- MRR@5: `0.561111`
- NDCG@5: `0.574908`
- Hit@10: `0.679012`

## 7. Sprint 33 Comparison

| Metric | Production | Sprint 33 | Activated |
| --- | ---: | ---: | ---: |
| Hit@5 | 0.444444 | 0.654 | 0.654321 |
| MRR@5 | 0.38107 | 0.561 | 0.561111 |
| NDCG@5 | 0.378002 | 0.575 | 0.574908 |
| Hit@10 | 0.493827 | 0.679 | 0.679012 |
| Latency K5 ms | 13374 | n/a | 20790 |

## 8. Category Metrics

```json
{
    "doctrinal_semantic": {
        "questions": 35,
        "hit_rate": 0.657143,
        "precision": 0.131429,
        "recall": 0.642857,
        "mrr": 0.508095,
        "ndcg": 0.539599,
        "source_coverage": 0.885714,
        "latency_ms": 0,
        "failed_questions": [
            "Where does the Catechism explain why the Word became flesh?",
            "Which Catechism paragraph is about the Incarnation?",
            "Where does the Catechism connect creation to the Holy Trinity?",
            "Where is Jesus identified as God the Son in the Catechism?",
            "Where is Mary called blessed among women?",
            "Where does Elizabeth call Mary the mother of my Lord?",
            "Where does Paul call the Church the pillar and ground of truth?",
            "Where does Tobit begin with Tobit of the tribe of Nephtali?",
            "Where does Judith begin with Arphaxad king of the Medes?",
            "Where does First Maccabees begin after Alexander son of Philip?",
            "Which Bible and Catechism sources support the Incarnation?",
            "Which sources speak about God the Son and the Word?"
        ]
    },
    "exact_reference": {
        "questions": 11,
        "hit_rate": 1,
        "precision": 1,
        "recall": 1,
        "mrr": 1,
        "ndcg": 1,
        "source_coverage": 1,
        "latency_ms": 0,
        "failed_questions": []
    },
    "general_semantic": {
        "questions": 29,
        "hit_rate": 0.448276,
        "precision": 0.098276,
        "recall": 0.413793,
        "mrr": 0.367816,
        "ndcg": 0.36833,
        "source_coverage": 0.965517,
        "latency_ms": 0,
        "failed_questions": [
            "Where does Jesus say he and the Father are one?",
            "Where does Jesus say the bread he gives is his flesh?",
            "Where does Jesus say unless you eat his flesh and drink his blood?",
            "Where does Jesus say my flesh is meat indeed and my blood is drink indeed?",
            "Where does Jesus say he that eateth my flesh abideth in me?",
            "Where does Paul recount this is my body at the Last Supper?",
            "Where does John say God so loved the world?",
            "Where does James teach calling the priests to anoint the sick?",
            "Where does James say confess your sins one to another?",
            "Where does Jesus teach what God joined together let no man put asunder?",
            "Where does Jesus teach the Our Father?",
            "Where does Jesus teach ask and it shall be given you?",
            "Where does Paul say pray without ceasing?",
            "Where does God promise Abraham that all nations shall be blessed?",
            "Where does Micah prophesy Bethlehem?",
            "Which source introduces Augustine praying to God?"
        ]
    },
    "reference_contextual": {
        "questions": 6,
        "hit_rate": 1,
        "precision": 0.266667,
        "recall": 1,
        "mrr": 1,
        "ndcg": 1,
        "source_coverage": 1,
        "latency_ms": 0,
        "failed_questions": []
    }
}
```

## 9. Exact-Reference Results

| query | top_reference | top_source_name | route | passed |
| --- | --- | --- | --- | --- |
| John 1:1 | John 1:1 | Douay-Rheims Bible | exact_reference | 1 |
| John 3:16 | John 3:16 | Douay-Rheims Bible | exact_reference | 1 |
| John 6:51 | John 6:51 | Douay-Rheims Bible | exact_reference | 1 |
| John 19:30 | John 19:30 | Douay-Rheims Bible | exact_reference | 1 |
| John 20:19 | John 20:19 | Douay-Rheims Bible | exact_reference | 1 |
| Tobit 1:1 | Tobit 1:1 | Douay-Rheims Bible | exact_reference | 1 |
| Judith 1:1 | Judith 1:1 | Douay-Rheims Bible | exact_reference | 1 |
| Wisdom 1:1 | Wisdom 1:1 | Douay-Rheims Bible | exact_reference | 1 |
| Sirach 1:1 | Sirach 1:1 | Douay-Rheims Bible | exact_reference | 1 |
| Baruch 1:1 | Baruch 1:1 | Douay-Rheims Bible | exact_reference | 1 |
| 1 Maccabees 1:1 | 1 Maccabees 1:1 | Douay-Rheims Bible | exact_reference | 1 |
| 2 Maccabees 1:1 | 2 Maccabees 1:1 | Douay-Rheims Bible | exact_reference | 1 |

## 10. John 1:1 Results

```json
{
    "query": "John 1:1",
    "top_reference": "John 1:1",
    "top_source_name": "Douay-Rheims Bible",
    "route": "exact_reference",
    "used_router": true,
    "citation_reference": "John 1:1",
    "citation_source_name": "Douay-Rheims Bible",
    "passed": true
}
```

## 11. False-Positive Results

- False positives: `0 / 7`

## 12. Citation Integrity

- Invalid references: `0`
- Citation mismatches: `0`

## 13. Fallback Results

```json
{
    "router_enabled": true,
    "forced_failure": true,
    "used_fallback": true,
    "context_documents": 3,
    "passed": true
}
```

## 14. API Compatibility

Feature tests cover the public `/api/answers` envelope with the router enabled. The command keeps diagnostics internal and does not add public API fields.

## 15. Latency

- Benchmark runtime ms: `68554`
- Activation diagnostic runtime ms: `73206`
- Production K5 latency ms: `13374`
- Activated K5 latency ms: `20790`

## 16. Security Verification

```json
{
    "prompt_injection_blocked": true,
    "prompt_injection_error_code": "PROMPT_INJECTION_DETECTED",
    "pii_policy_active": true,
    "provider_policy_active": true,
    "external_llm_contacted": false,
    "passed": true
}
```

## 17. Production DB Before/After

- Before: `{"documents":37204,"bible_verses":35860,"bible_chapters":1334,"catechism":7,"church_fathers":3,"embedded_documents":37204,"embedding_dimensions":{"384":37204},"graph_nodes":37204,"graph_edges":0,"broken_graph_references":0,"duplicate_graph_relationships":0}`
- After: `{"documents":37204,"bible_verses":35860,"bible_chapters":1334,"catechism":7,"church_fathers":3,"embedded_documents":37204,"embedding_dimensions":{"384":37204},"graph_nodes":37204,"graph_edges":0,"broken_graph_references":0,"duplicate_graph_relationships":0}`
- Duplicates: `{"within_source_bible_duplicates":0,"known_cross_source_john_1_duplicates":51}`

## 18. Tests

- Host focused routing tests: `php artisan test --compact tests/Unit/ScriptureQueryRouterTest.php tests/Feature/ScriptureRoutingExperimentTest.php tests/Feature/ScriptureRoutingReadinessTest.php`
  - Passed: `37 tests`, `95 assertions`
- Host full suite: `php artisan test --compact`
  - Passed: `285 tests`, `1478 assertions`
- Docker API-level feature checks: `docker compose exec app php artisan test --compact tests/Feature/ScriptureRoutingReadinessTest.php`
  - Passed: `20 tests`, `69 assertions`

## 19. PHPStan

`vendor/bin/phpstan analyse --memory-limit=1G` passed.

## 20. Pint

`vendor/bin/pint --dirty --format agent` passed.

## 21. Diff Check

`git diff --check` passed.

## 22. Docker Verification

- `docker compose exec app php artisan graph:verify` passed.
- `docker compose exec app php artisan retrieval:health` passed.
- `docker compose exec app php artisan evaluate:scripture-routing-readiness --format=json` passed.
- `docker compose exec app php artisan evaluate:scripture-routing-activation --write-report` passed.
- Live default-disabled `/api/answers` request returned the existing answer envelope and used the production retrieval path.

## 23. Remaining Risks

- Multi-reference behavior is deterministic for explicitly detected references, but theological relationship synthesis remains intentionally out of scope.
- Same-book repeated-reference phrasing such as `John 1:1 ... John 3:16` may not capture every repeated reference in the current parser. This is documented as a parser limitation and was not changed in this activation sprint.
- Graph edges remain `0`, so graph-based doctrinal traversal is not part of this activation.

## 24. Final Recommendation

Proceed to Sprint 36 production observation and tuning with the router feature-flagged, reversible, observable, and fallback-capable.
