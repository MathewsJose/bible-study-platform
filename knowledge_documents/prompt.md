# Prompt 9 — Improve Embedding Generation Command

Use after status fields exist.

```plain text
Improve the embedding generation workflow.

Goal:
Generate embeddings only for documents that need them and update embedding status safely.

Requirements:
1. Inspect the existing GenerateEmbeddingsCommand.
2. Update it to process documents where embedding_status is pending or failed, optionally controlled by a --retry-failed flag.
3. Add options:
   - --limit
   - --source-type
   - --source-name
   - --dry-run
4. On success:
   - save embedding
   - set embedding_status to ready
   - set embedding_model
   - set embedded_at
   - clear embedding_error
5. On failure:
   - set embedding_status to failed
   - save a short error message
6. Avoid loading all documents into memory.
7. Add tests with a fake embedding provider.

Constraints:
- Do not call real external APIs in tests.
- Keep provider abstraction intact.
- Keep PostgreSQL and SQLite compatibility.
```


---

# Prompt 10 — Add Search Filters for Source-Aware Retrieval

Use this after data exists.

```plain text
Add source-aware filters to full-text and semantic search endpoints.

Goal:
Clients should be able to search within specific Catholic source categories.

Requirements:
1. Extend search request validation to accept optional:
   - source_type
   - source_name
   - tradition
   - book
   - chapter
2. Apply these filters to full-text search.
3. Apply these filters to semantic search.
4. For book/chapter, filter against metadata JSON in a database-compatible way.
5. If JSON filtering differs between PostgreSQL and SQLite, implement safe fallbacks.
6. Add tests for full-text and semantic search filtering.

Constraints:
- Preserve existing response shape unless already intentionally changed.
- Keep validation strict.
- Keep implementation readable.
```


---

# Prompt 11 — Add Hybrid Retrieval Endpoint

This is a more advanced step.

```plain text
Add a hybrid search endpoint for RAG retrieval.

Goal:
Combine full-text search and semantic search results into one ranked response.

Requirements:
1. Add POST /api/documents/hybrid-search.
2. Accept:
   - query
   - limit
   - score_threshold
   - source_type
   - source_name
   - tradition
   - book
   - chapter
3. Run both full-text search and semantic search.
4. Merge results by document id.
5. Compute a combined score using configurable weights:
   - semantic weight default 0.65
   - full-text weight default 0.25
   - source priority weight default 0.10
6. Return ranked document DTOs with:
   - document
   - score
   - score_breakdown
7. Add tests.

Constraints:
- Keep existing search endpoints working.
- Do not over-engineer.
- Make weights configurable.
```


---

# Prompt 12 — Prepare Baltimore Catechism Importer

Use after Bible import is stable.

```plain text
Prepare a Baltimore Catechism importer.

Goal:
Import a public-domain Baltimore Catechism source from a local structured file.

Requirements:
1. Use local source files only.
2. Support a small JSON fixture with lessons/questions/answers.
3. Normalize each question/answer pair or lesson section into a knowledge document:
   - source_type: catechism
   - source_name: Baltimore Catechism
   - tradition: catholic
   - reference: e.g. Lesson 1, Question 1
   - title
   - content
   - metadata:
     - catechism: Baltimore Catechism
     - lesson
     - question_number
     - language
     - source_url
     - license
     - license_url
4. Make import idempotent.
5. Record import manifest counts.
6. Add tests using a tiny artificial fixture.

Constraints:
- Do not import the modern Catechism of the Catholic Church.
- Add documentation warning about verifying licenses before import.
- Preserve architecture and test compatibility.
```


---

# Prompt 13 — Prepare Church Fathers Importer

Use after catechism import.

```plain text
Prepare a Church Fathers importer for public-domain source files.

Goal:
Import structured Church Fathers texts from local JSON files.

Requirements:
1. Use local source files only.
2. Support a JSON structure with:
   - author
   - work
   - sections
   - title
   - reference
   - content
   - source_url
   - license
3. Normalize each logical section into:
   - source_type: church_father
   - source_name: "{Author}, {Work}"
   - tradition: catholic
   - reference
   - title
   - content
   - metadata:
     - author
     - work
     - section
     - century if available
     - original_language if available
     - translator if available
     - source_url
     - license
     - license_url
4. Make import idempotent.
5. Record import manifest counts.
6. Add tests with tiny artificial fixtures.

Constraints:
- Do not scrape CCEL/New Advent directly.
- Use only local files with confirmed license metadata.
- Preserve architecture and test compatibility.
```


---

# Prompt 14 — Add Corpus Quality/Audit Command

This is useful once imports grow.

```plain text
Add a corpus audit console command.

Goal:
Provide a quick report about imported knowledge documents and data quality.

Requirements:
1. Add a command such as php artisan knowledge:audit.
2. Report:
   - total documents
   - counts by source_type
   - counts by source_name
   - counts by tradition
   - documents missing content
   - documents missing source_url metadata
   - documents missing license metadata
   - documents pending embeddings
   - documents failed embeddings
3. Add optional filters:
   - --source-type
   - --source-name
4. Add tests for the command output.

Constraints:
- Read-only command.
- Do not change data.
- Keep it efficient for large datasets.
```


---

# Prompt 15 — Final Integration and Documentation Review

Use after several features are implemented.

```plain text
Review the implemented Catholic knowledge import system end-to-end.

Do not make code changes until after the review summary.

Please check:
1. Import architecture consistency.
2. Idempotent behavior.
3. Manifest accuracy.
4. License metadata coverage.
5. Bible verse and chapter import correctness.
6. Embedding status workflow.
7. Search/filter behavior.
8. Test coverage.
9. Documentation accuracy.
10. Any security or data-quality risks.

Then propose a prioritized fix list:
- Critical
- High
- Medium
- Low

After I approve, implement only the approved fixes in small commits/changes.
```


---

# Recommended Actual Execution Order

I would run them in this exact order:

```plain text
1. Prompt 1 — Project Review Before Changes
2. Prompt 2 — Import Manifest Tracking
3. Prompt 3 — Idempotent Imports
4. Prompt 4 — Source and License Metadata
5. Prompt 5 — Douay-Rheims Verse Import
6. Prompt 6 — Bible Chapter Documents
7. Prompt 7 — Import Documentation
8. Prompt 8 — Embedding Status Fields
9. Prompt 9 — Embedding Generation Command
10. Prompt 10 — Search Filters
11. Prompt 14 — Corpus Audit Command
12. Prompt 12 — Baltimore Catechism Importer
13. Prompt 13 — Church Fathers Importer
14. Prompt 15 — Final Review
```


I would delay hybrid search until the project has real imported data and basic search filters working.

---

# Short “Master Prompt” for Junie

If you want to give Junie a high-level instruction before the step prompts, use this:

```plain text
We are building a Laravel Catholic theological knowledge document service for future RAG and semantic search.

The immediate goal is to add a safe, auditable, idempotent import pipeline for public-domain Catholic sources, beginning with the Douay-Rheims Bible.

Important constraints:
- Preserve the existing clean architecture style.
- Keep PostgreSQL + pgvector production support.
- Keep SQLite-compatible tests.
- Do not scrape websites directly from import commands.
- Use local source files with explicit license metadata.
- Do not import copyrighted modern Bible translations or the full modern Catechism without permission.
- Every imported document should include source/license metadata.
- Imports must be idempotent.
- Prefer small, reviewable changes with tests.

Work step by step. Before each implementation, inspect the existing code and explain the planned changes. After implementation, summarize changed files, tests added/updated, and any remaining risks.
```
