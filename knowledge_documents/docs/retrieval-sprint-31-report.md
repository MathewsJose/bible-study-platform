# Sprint 31 - Persistent Contextual Retrieval Index

## 1. Overall Decision

BLOCKED

The experimental contextual index implementation is present and focused tests pass. The full 81-question contextual benchmark is blocked until the contextual index is built and contextual embeddings are generated for at least one selected window.

## 2. Scope

This sprint adds an isolated experimental retrieval index. It does not replace or mutate production retrieval, production embeddings, document content, public API contracts, answer generation, agents, MCP, security policies, evaluation thresholds, or hybrid ranking.

## 3. Architecture Overview

Production `knowledge_documents` remain the source of truth. The new `retrieval_contextual_documents` table stores derived contextual retrieval units keyed by source document and context window.

Flow:

```text
knowledge_documents
  -> ContextualRetrievalIndexService
  -> retrieval_contextual_documents
  -> ContextualRetrievalEmbeddingService
  -> ContextualIndexSearchService
  -> ContextualIndexBenchmarkService
```

Each contextual row preserves `source_document_id`, `source_type`, `source_name`, `reference`, Bible coordinates, document type, context window, context text, checksum, embedding metadata, and the experimental embedding vector.

## 4. New Components

```text
app/Application/Knowledge/Retrieval/Experiments/ContextualBibleTextBuilder.php
app/Application/Knowledge/Retrieval/Experiments/ContextualIndexBenchmarkService.php
app/Application/Knowledge/Retrieval/Experiments/ContextualIndexSearchService.php
app/Application/Knowledge/Retrieval/Experiments/ContextualRetrievalEmbeddingService.php
app/Application/Knowledge/Retrieval/Experiments/ContextualRetrievalIndexService.php
app/Console/Commands/EvaluateContextualIndexCommand.php
app/Console/Commands/RetrievalContextualEmbeddingsCommand.php
app/Console/Commands/RetrievalContextualIndexCommand.php
app/Infrastructure/Knowledge/Persistence/RetrievalContextualDocumentRecord.php
database/factories/RetrievalContextualDocumentRecordFactory.php
database/migrations/2026_08_25_190535_create_retrieval_contextual_documents_table.php
tests/Feature/ContextualRetrievalIndexTest.php
```

Sprint 30 experimental files remain uncommitted in the worktree and are still part of the broader contextual retrieval work.

## 5. Context Windows

Supported windows:

- `verse`
- `verse_only`
- `plus_minus_1`
- `previous_and_next`
- `adjacent`
- `plus_minus_3`
- `window_3`
- `chapter`
- `chapter_context`

Aliases are normalized before persistence so benchmark runs can be reproduced consistently.

## 6. Commands

```bash
php artisan retrieval:contextual-index --window=plus_minus_1 --batch=100
php artisan retrieval:contextual-index --window=plus_minus_1 --dry-run --limit=10 --format=json
php artisan retrieval:contextual-embeddings --window=plus_minus_1 --batch=100
php artisan retrieval:contextual-embeddings --window=plus_minus_1 --dry-run --format=json
php artisan evaluate:contextual-index --window=plus_minus_1 --format=json
php artisan evaluate:contextual-index --window=plus_minus_1 --write-report
```

The `--limit` option exists for bounded smoke tests and does not change the default full-index command behavior.

## 7. Benchmark Dataset

The persistent-index benchmark uses the existing 81-question Sprint 30 dataset:

- Version: `retrieval-sprint-30-v1`
- Defined questions: `81`
- Validation in local run: `81` valid, `0` missing references

## 8. Local Command Output

Bounded index dry run:

```json
{
  "processed": 10,
  "created": 10,
  "updated": 0,
  "skipped": 0,
  "failed": 0,
  "dry_run": true,
  "window": "verse"
}
```

Embedding dry run before building rows:

```json
{
  "processed": 0,
  "embedded": 0,
  "skipped": 0,
  "failed": 0,
  "dry_run": true,
  "window": "verse"
}
```

Benchmark before contextual embeddings:

```json
{
  "decision": "BLOCKED",
  "index": {
    "window": "verse",
    "indexed_documents": 0,
    "embedded_documents": 0
  },
  "blocking_reason": "Contextual index embeddings are not generated for the selected window."
}
```

## 9. Citation Integrity

The experimental table stores the original production reference and source document ID for each contextual row. Search results return the production `source_document_id`, not a synthetic contextual ID, so citations remain anchored to existing knowledge documents.

## 10. Test Results

Passed:

```bash
php artisan test --compact tests/Feature/ContextualRetrievalIndexTest.php tests/Unit/ContextualRetrievalExperimentTest.php
```

Result:

```json
{"tool":"pest","result":"passed","tests":8,"passed":8,"assertions":38}
```

Scoped PHPStan passed for Sprint 31 files:

```bash
php vendor/bin/phpstan analyse app/Application/Knowledge/Retrieval/Experiments/ContextualRetrievalIndexService.php app/Application/Knowledge/Retrieval/Experiments/ContextualRetrievalEmbeddingService.php app/Application/Knowledge/Retrieval/Experiments/ContextualIndexSearchService.php app/Application/Knowledge/Retrieval/Experiments/ContextualIndexBenchmarkService.php app/Application/Knowledge/Retrieval/Experiments/ContextualBibleTextBuilder.php app/Console/Commands/RetrievalContextualIndexCommand.php app/Console/Commands/RetrievalContextualEmbeddingsCommand.php app/Console/Commands/EvaluateContextualIndexCommand.php app/Infrastructure/Knowledge/Persistence/RetrievalContextualDocumentRecord.php tests/Feature/ContextualRetrievalIndexTest.php --memory-limit=1G
```

Result:

```json
{"tool":"phpstan","result":"passed","errors":0}
```

`git diff --check` passed.

## 11. Verification Caveats

Full `php artisan test --compact` was started but did not complete after several minutes and was stopped.

Full `php vendor/bin/phpstan analyse --memory-limit=1G` currently fails on pre-existing non-Sprint-31 typing issues in agent replay, AI evaluation, regression comparison, and source inventory classes.

Pint did not complete in this PowerShell environment when run through both Composer proxy forms:

```bash
php vendor/bin/pint --dirty --format agent
php vendor/laravel/pint/builds/pint --dirty --format agent
```

## 12. Exact Next Step

Do not promote this experimental retrieval path to production yet.

To produce the first real contextual benchmark:

```bash
php artisan migrate --force
php artisan retrieval:contextual-index --window=plus_minus_1 --batch=100
php artisan retrieval:contextual-embeddings --window=plus_minus_1 --batch=100
php artisan evaluate:contextual-index --window=plus_minus_1 --format=json
php artisan evaluate:contextual-index --window=plus_minus_1 --write-report
```

After that, repeat for `verse`, `plus_minus_3`, and `chapter` if resource usage is acceptable.
