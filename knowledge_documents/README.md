# Catholic Bible AI Knowledge Service
Laravel service for storing and retrieving Catholic theological knowledge documents. This service is designed as the knowledge foundation for future RAG, vector search, embedding, and agentic workflows. It does not generate AI answers.

## Stack

- PHP 8.5 target runtime
- Laravel 13
- PostgreSQL 17 with pgvector
- Docker Compose with app, nginx, postgres, and pgAdmin
- Pest PHP tests
- Larastan/PHPStan and Laravel Pint
- REST API with OpenAPI documentation in `docs/openapi.yaml`

## Architecture

The code follows a pragmatic clean architecture shape:

- `app/Domain`: domain concepts, native enums, value objects, and the `KnowledgeDocument` domain entity.
- `app/Application`: DTOs, contracts, and orchestration services such as document CRUD, embedding generation, full-text search, and semantic search.
- `app/Infrastructure`: Eloquent persistence, pgvector SQL, embedding providers, and extensible importers.
- `app/Presentation`: HTTP controllers and request validation.

Eloquent models are treated as persistence records and are never returned directly from controllers. API responses are built from DTOs.

Repository abstraction is used at persistence boundaries because the service has database-specific behavior for full-text search, embedding storage, and pgvector similarity search.

## Knowledge Sources

Supported `source_type` values:

- `bible_verse`
- `bible_chapter`
- `catechism`
- `church_father`
- `papal_document`
- `council_document`
- `commentary`
- `article`

Supported `tradition` values are modeled as a native PHP enum and can be extended as the platform grows.

## Running Locally

```bash
cp .env.example .env
php artisan key:generate
docker compose up -d --build
docker compose exec app php artisan migrate --force
```

API base URL:

```text
http://localhost:8080/api
```

pgAdmin:

```text
http://localhost:5050
```

## API Endpoints

- `POST /api/documents`
- `GET /api/documents/{id}`
- `PUT /api/documents/{id}`
- `DELETE /api/documents/{id}`
- `GET /api/documents`
- `POST /api/documents/search`
- `POST /api/documents/semantic-search`
- `POST /api/documents/hybrid-search`

Semantic search uses `EmbeddingProviderInterface` and `EmbeddingRepositoryInterface`. Providers are selected through configuration, so OpenAI, Gemini, Ollama, or local sentence-transformer adapters can be added without changing generation or search business logic.

Example semantic search request:

```json
{
  "query": "Why did Jesus become man?",
  "top_k": 10,
  "source_types": ["catechism", "bible_verse"],
  "minimum_score": 0.4
}
```

Hybrid search combines vector similarity with PostgreSQL lexical ranking. It normalizes each candidate list to a `0..1` range, applies configured weights, deduplicates documents, and returns a combined score plus score breakdowns.

```env
HYBRID_VECTOR_WEIGHT=0.70
HYBRID_LEXICAL_WEIGHT=0.30
HYBRID_FETCH_MULTIPLIER=3
HYBRID_MINIMUM_SCORE=0.0
```

Example hybrid search request:

```json
{
  "query": "Why did Jesus become man?",
  "top_k": 10,
  "minimum_score": 0.1,
  "source_types": ["catechism", "bible_verse"],
  "tradition": "catholic"
}
```

Example hybrid search response:

```json
{
  "data": [
    {
      "id": "0197f4c2-8f25-73f2-8a88-1fdf3c11395d",
      "source_type": "catechism",
      "source_name": "Catechism of the Catholic Church",
      "tradition": "catholic",
      "reference": "CCC 457",
      "title": "Why the Word became Flesh",
      "content": "The Word became flesh for us in order to save us by reconciling us with God...",
      "vector_score": 0.91,
      "lexical_score": 1,
      "combined_score": 0.937
    }
  ],
  "meta": {
    "top_k": 10,
    "total": 1,
    "minimum_score": 0.1,
    "vector_weight": 0.7,
    "lexical_weight": 0.3
  }
}
```

Example response:

```json
{
  "data": [
    {
      "id": "0197f4c2-8f25-73f2-8a88-1fdf3c11395d",
      "source_type": "catechism",
      "source_name": "Catechism of the Catholic Church",
      "tradition": "catholic",
      "reference": "CCC 457",
      "title": "Why the Word became Flesh",
      "content": "The Word became flesh for us in order to save us by reconciling us with God...",
      "score": 0.95
    }
  ],
  "meta": {
    "top_k": 10,
    "total": 1,
    "minimum_score": 0.4
  }
}
```

## Database

The `knowledge_documents` table uses:

- UUID primary key
- JSONB metadata
- pgvector `vector(1536)` embeddings
- GIN metadata index
- GIN full-text search index
- HNSW cosine vector index

SQLite-compatible fallbacks exist only so the automated test suite can run quickly without a PostgreSQL container.

## Embedding Pipeline

The embedding pipeline is the foundation for RAG and future agent workflows.

Core classes:

- `EmbeddingProviderInterface`: provider boundary used by search and generation.
- `OpenAIEmbeddingProvider`: OpenAI implementation using Laravel's HTTP client with timeouts and retries.
- `NullEmbeddingProvider`: safe default that fails clearly when no provider is configured.
- `EmbeddingRepositoryInterface`: persistence boundary for selecting documents, storing vectors, marking failures, and vector search.
- `EloquentEmbeddingRepository`: PostgreSQL pgvector implementation with a SQLite fallback for tests.
- `EmbeddingGenerationService`: dispatches queue batches and validates provider output.
- `EmbeddingJob`: queue job that generates embeddings for a chunk of document IDs.

Configuration lives in `config/embeddings.php` and is environment-driven:

```env
EMBEDDINGS_PROVIDER=openai
EMBEDDINGS_MODEL=text-embedding-3-small
EMBEDDINGS_DIMENSIONS=1536
EMBEDDINGS_BATCH_SIZE=100
EMBEDDINGS_TIMEOUT=30
EMBEDDINGS_RETRY_ATTEMPTS=3
EMBEDDINGS_RETRY_SLEEP_MS=200
EMBEDDINGS_QUEUE_CONNECTION=database
OPENAI_API_KEY=
OPENAI_EMBEDDINGS_URL=https://api.openai.com/v1/embeddings
```

For local tests or deterministic development runs, bind a fake provider in tests or set the provider to `dummy`. Production should use a real provider and dimensions that match the existing `vector(1536)` column.

Generate embeddings:

```bash
php artisan embeddings --batch=100
```

Useful options:

```bash
php artisan embeddings --document-id=UUID
php artisan embeddings --force
php artisan embeddings --retry-failed
php artisan embeddings --dry-run
```

The old `php artisan embeddings:generate` name is still available as an alias. The command finds documents that need embeddings, chunks IDs into queue jobs, dispatches them as a Laravel batch, displays a progress bar while dispatching, and prints a summary containing total candidates, processed, succeeded, skipped, failed, and duration. With `EMBEDDINGS_QUEUE_CONNECTION=sync`, jobs run immediately. With `database`, `redis`, or another async connection, start a worker:

```bash
php artisan queue:work database --tries=3
```

Failures are recorded on each document through `embedding_status=failed` and `embedding_error`. Jobs also implement a `failed()` hook so permanent job failures are logged and stored by Laravel's failed job system.

Vector validation:

- Vectors must match `EMBEDDINGS_DIMENSIONS`.
- Values must be numeric and finite.
- Invalid vectors are rejected and the document is marked failed.

Troubleshooting:

- Empty semantic results usually mean no documents have `embedding_status=ready`, the score threshold is too high, or filters exclude matches.
- `503 Semantic search is unavailable` means the provider is not configured or failed to generate a query embedding.
- PostgreSQL production search requires pgvector and stored vectors in the existing `embedding` column.
- If jobs remain queued, run `php artisan queue:work` with the same connection configured by `EMBEDDINGS_QUEUE_CONNECTION`.

## Real Embeddings

Dummy embeddings were useful while building the pipeline because tests and local development could exercise pgvector storage without external API calls or cost. They are not semantically meaningful. A dummy vector for `John 1:14` does not encode that "the Word was made flesh" relates to the Incarnation, so vector and hybrid retrieval quality cannot be judged from dummy-model results.

Production semantic retrieval can use either local open-source embeddings or OpenAI embeddings. Local embeddings are the recommended no-cost development path when OpenAI API credits are unavailable.

```env
EMBEDDING_PROVIDER=local
EMBEDDINGS_PROVIDER=local
EMBEDDINGS_MODEL=sentence-transformers/all-MiniLM-L6-v2
EMBEDDINGS_DIMENSIONS=384
LOCAL_EMBEDDING_URL=http://embedding-service:8000
LOCAL_EMBEDDING_MODEL=sentence-transformers/all-MiniLM-L6-v2
LOCAL_EMBEDDING_DIMENSIONS=384
EMBEDDINGS_QUEUE_CONNECTION=sync
```

The local model is `sentence-transformers/all-MiniLM-L6-v2`. It is an English Sentence Transformers model for sentence similarity and semantic search, uses the Apache 2.0 license, and outputs `384` dimensions. The PostgreSQL column is therefore `vector(384)` in the local embedding profile. Existing dummy or OpenAI vectors are not compatible with this model and must be regenerated.

OpenAI remains available:

```env
EMBEDDING_PROVIDER=openai
EMBEDDINGS_PROVIDER=openai
OPENAI_API_KEY=
OPENAI_EMBEDDING_MODEL=text-embedding-3-small
OPENAI_EMBEDDING_DIMENSIONS=1536
EMBEDDINGS_MODEL=text-embedding-3-small
EMBEDDINGS_DIMENSIONS=1536
```

Never commit `OPENAI_API_KEY`, print it, or paste it into logs. Switching between providers with different vector dimensions requires a compatible pgvector column and a full re-index.

## Local Embeddings

The local embedding service is a small FastAPI application under `embedding-service/`. It is the only Python component. Laravel remains the main backend, PostgreSQL + pgvector remains the vector database, and the provider abstraction keeps the application independent from a specific embedding vendor.

Docker architecture:

- `app`: Laravel API and Artisan commands.
- `postgres`: PostgreSQL 17 with pgvector.
- `embedding-service`: CPU-only FastAPI + Sentence Transformers model server.
- `embedding-model-cache`: named Docker volume mounted at `/models` so model files are reused after the first download.

The local service exposes only the internal Docker network endpoint:

```http
GET http://embedding-service:8000/health
POST http://embedding-service:8000/embed
```

Example embedding request:

```json
{
  "texts": [
    "Why did Jesus become man?",
    "The Word became flesh for us in order to save us."
  ]
}
```

Example response:

```json
{
  "embeddings": [[0.0123, -0.0345]],
  "model": "sentence-transformers/all-MiniLM-L6-v2",
  "dimensions": 384
}
```

CPU inference is slower than GPU inference but works on normal development machines. GPU support can be added later as an optimization; it is not required.

Check the provider before writing embeddings:

```bash
php artisan embeddings:health
```

Expected shape:

```text
Embedding Provider Health
Provider: local
Model: sentence-transformers/all-MiniLM-L6-v2
Dimensions: 384
API Key: not required
API Connection: OK
Model loaded: YES

Database Embeddings
Total: 59
With embeddings: 59
Without embeddings: 0
Embedding Model | Count
sentence-transformers/all-MiniLM-L6-v2 | 59
```

Cost-safe test run:

```bash
php artisan embeddings:generate --force --limit=5 --dry-run
php artisan embeddings:generate --force --limit=5
```

Full re-index:

```bash
php artisan embeddings:generate --force
```

`--force` replaces the existing vector and metadata on each selected document. It does not create duplicate `KnowledgeDocument` rows. Running it again is idempotent, but it will call the provider again, so use `--limit` and `--dry-run` before a full run.

Changing embedding models requires re-embedding the whole compatible dataset. Documents and queries must use the same model because vector distance only has meaning inside the same embedding space. Mixing `dummy-model`, `text-embedding-3-small`, and `sentence-transformers/all-MiniLM-L6-v2` vectors makes semantic search compare unrelated coordinate systems.

Project examples:

- Bible verses are often short. `John 1:16` may contain only a brief phrase about grace, so the embedding needs the real language signal; a dummy vector cannot connect "What is grace?" to that verse.
- Catechism paragraphs such as `CCC 456` contain doctrinal explanations. Real embeddings can connect "Why did the Word become flesh?" to "The Word became flesh for us..." even when the wording is not identical.
- Church Father passages can use older theological language. A real embedding model can place "Augustine and grace" near related patristic content better than exact keyword matching alone.

Embedding generation is separated from search because it is slower, can fail independently, costs money, and should run offline through queues. Search should only read already prepared vectors. API failures such as timeouts, 429 rate limits, and 5xx responses are retried with backoff; permanent configuration problems such as a missing key fail clearly.

Local verification workflow:

```bash
docker compose build
docker compose up -d
docker compose ps embedding-service
docker compose exec embedding-service python -c "import urllib.request; print(urllib.request.urlopen('http://127.0.0.1:8000/health').read().decode())"
docker compose exec app php artisan embeddings:health
docker compose exec app php artisan embeddings:generate --force --limit=5 --dry-run
docker compose exec app php artisan embeddings:generate --force --limit=5
docker compose exec app php artisan retrieval:health
docker compose exec app php artisan evaluate:retrieval --strategy=vector --top-k=5
docker compose exec app php artisan evaluate:retrieval --strategy=hybrid --top-k=5
docker compose exec app php artisan embeddings:generate --force
docker compose exec app php artisan evaluate:retrieval --top-k=5 --compare
```

## Retrieval Evaluation

Retrieval evaluation measures whether semantic search returns the Catholic sources we expected before any future LLM answer generation is added. This matters because RAG quality depends first on retrieval quality: if the system fails to retrieve `CCC 457` or `John 1:14` for "Why did Jesus become man?", a later answer generator will be grounded in the wrong material.

Evaluation data is stored in `evaluation_questions`, not hardcoded in services. Each question stores intended references, currently evaluable references, missing references, expected source types, and coverage status. Detailed runs are stored in `retrieval_evaluation_runs`, and aggregate summaries are stored in `retrieval_evaluation_summaries` with the embedding model, provider, dimensions, top K, minimum score, and filters used for reproducibility.

Coverage fields:

- `intended_references`: the full ground truth from the evaluation design.
- `expected_references`: references that currently exist in `knowledge_documents` and are used for metrics.
- `missing_references`: intended references absent from the current corpus.
- `coverage_status`: `fully_covered`, `partially_covered`, or `unavailable`.

This keeps corpus gaps visible without changing retrieval metrics to expect documents that do not exist yet.

Seed development evaluation questions:

```bash
php artisan db:seed --class=EvaluationQuestionSeeder
```

The seeder contains about 20 Catholic retrieval questions across Christology, Sacraments, Grace, Trinity, Mary, Salvation, and Scripture. It only stores expected references that currently exist in `knowledge_documents`; missing references are printed as warnings instead of invented.

Run a baseline evaluation:

```bash
php artisan evaluate:retrieval --top-k=5 --minimum-score=0 --strategy=vector --save
```

The old `php artisan evaluate` command name is still available as an alias.

Useful filters:

```bash
php artisan evaluate:retrieval --category=christology
php artisan evaluate:retrieval --question-id=UUID
php artisan evaluate:retrieval --limit=10
php artisan evaluate:retrieval --strategy=lexical
php artisan evaluate:retrieval --strategy=hybrid
php artisan evaluate:retrieval --compare
php artisan evaluate:retrieval --weight-grid
```

Diagnostic commands:

```bash
php artisan evaluate:diagnose
php artisan evaluate:diagnose --question-id=UUID
php artisan evaluate:diagnose --strategy=vector
php artisan evaluate:diagnose --top-k=10
php artisan retrieval:health
```

`evaluate:diagnose` does not change retrieval behavior. It prints dataset coverage counts, each evaluation question, intended references, currently evaluable references, missing references, source types, content lengths, query embedding dimensions, and the top ranked vector, lexical, and hybrid results with expected-hit markers. Unavailable questions are shown as coverage gaps and skipped for retrieval diagnostics.

`retrieval:health` summarizes knowledge document counts, embedding coverage, content length quality, vector and lexical index status, current evaluation metrics, and evidence-based potential problems. Use it before tuning weights, changing chunking, or changing embedding models.

How to interpret common findings:

- Low embedding coverage means vector and hybrid search cannot retrieve all documents.
- Very short chunks can lack enough semantic context for embeddings.
- Multiple embedding dimensions or models indicate the vector index contains inconsistent data.
- Lexical outperforming vector usually means exact references, source names, or theological terms are carrying more signal than the current embeddings.
- Hybrid precision below lexical precision usually means vector candidates are diluting strong lexical matches, not necessarily that the embedding model is wrong.

API evaluation endpoint:

```http
POST /api/evaluations/retrieval
```

```json
{
  "top_k": 5,
  "minimum_score": 0.7,
  "strategy": "hybrid",
  "save": true
}
```

Example response:

```json
{
  "data": {
    "total_questions": 20,
    "hit_rate": 0.85,
    "precision": 0.61,
    "recall": 0.72,
    "mrr": 0.79,
    "average_latency_ms": 120,
    "configuration": {
      "retrieval": "hybrid",
      "embedding_model": "text-embedding-3-small",
      "vector_weight": 0.7,
      "lexical_weight": 0.3,
      "top_k": 5,
      "minimum_score": 0.7
    }
  }
}
```

Metrics in this project:

- Hit@K: whether at least one expected Catholic source appears in the top K. If `CCC 457` appears anywhere in top 5 for "Why did Jesus become man?", Hit@5 is true.
- Precision@K: how many retrieved results were expected references. If top 5 contains `CCC 457` and `John 1:14`, and only those two were expected, precision is `2 / 5`.
- Recall@K: how many expected references were retrieved. If expected references are `CCC 456`, `CCC 457`, `CCC 458` and top 5 contains two of them, recall is `2 / 3`.
- MRR: reciprocal rank of the first expected reference. If the first expected result is ranked second, reciprocal rank is `0.5`.

Manual Docker verification:

```bash
docker compose up -d
docker compose exec app php artisan migrate --force
docker compose exec app php artisan db:seed --class=EvaluationQuestionSeeder
docker compose exec app php artisan embeddings --force --batch=100
docker compose exec app php artisan evaluate:retrieval --top-k=5 --minimum-score=0 --strategy=hybrid --save
docker compose exec app php artisan evaluate:retrieval --top-k=5 --minimum-score=0 --compare
docker compose exec app php artisan evaluate:retrieval --top-k=5 --minimum-score=0 --weight-grid
docker compose exec app php artisan tinker --execute "dump(DB::table('retrieval_evaluation_summaries')->latest('created_at')->first());"
```

Expected console shape:

```text
Evaluation Dataset Validation
Questions: 20
Valid: 18
Invalid: 2

Retrieval Evaluation
Questions: 18
Top K: 5

Hit@5: 85.0%
Precision@5: 0.610
Recall@5: 0.720
MRR: 0.790
Average latency: 120 ms
```

## Import Pipeline

Sprint 8 introduces a source-agnostic import framework. Importers no longer own persistence in the primary pipeline; they only fetch, normalize, and validate source material.

```text
KnowledgeImporterInterface
  -> fetch RawKnowledgeDocument
  -> normalize NormalizedKnowledgeDocument DTOs
  -> validate ValidationResult
  -> ImportPipeline
  -> KnowledgeDocumentPersistenceService
  -> knowledge_documents
  -> EmbeddingGenerationService queue dispatch
  -> ImportManifest report
```

Registered sources:

- `bible`: Bible chapter JSON files.
- `catechism`: Catechism JSON, CCC JSON, text, and Markdown files.
- `church_fathers`: Church Fathers JSON, text, and Markdown files.

Core classes:

- `KnowledgeImporterInterface`: source plugin boundary.
- `DocumentNormalizerInterface`: converts raw source files to normalized DTOs.
- `ImportValidatorInterface`: validates source payloads before persistence.
- `KnowledgeSourceRegistry`: registers, lists, resolves, and duplicate-checks importers.
- `ImportPipeline`: orchestrates fetch, normalize, validate, persist, embedding dispatch, structured logging, and report generation.
- `KnowledgeDocumentPersistenceService`: the only import framework class that writes `knowledge_documents`.
- DTOs: `RawKnowledgeDocument`, `NormalizedKnowledgeDocument`, `ValidationResult`, and `ImportPipelineResult`.

Provenance is stored in each document's `metadata` without changing the `knowledge_documents` schema:

- `source_identifier`
- `source_version`
- `source_path`
- `source_checksum`
- `content_checksum`
- `imported_at`
- `language`
- `license`
- `license_url`
- `rights_notes`

CLI usage:

```bash
php artisan knowledge:sources
php artisan knowledge:import all --skip-unchanged
php artisan knowledge:import bible --skip-unchanged
php artisan knowledge:import catechism --force
php artisan knowledge:import church_fathers --no-embeddings
php artisan knowledge:verify
php artisan knowledge:status
```

The legacy `php artisan knowledge` alias still imports all configured directories. Import directories are configured with:

```env
KNOWLEDGE_IMPORT_DIRECTORIES=storage/app/imports
```

Docker manual verification:

```bash
docker compose exec app php artisan knowledge:sources
docker compose exec app php artisan knowledge:import all --skip-unchanged
docker compose exec app php artisan knowledge:verify
docker compose exec app php artisan knowledge:status
docker compose exec app php artisan embeddings:generate
```

To add a new source such as Vatican II documents:

1. Create a class that implements `KnowledgeImporterInterface`, or extend `AbstractFileKnowledgeImporter`.
2. Return a stable `identifier()`, human `displayName()`, `version()`, supported languages, and licensing metadata.
3. Implement `supports()` for source detection.
4. Implement `fetch()`, `normalize()`, and `validate()` so the importer returns `NormalizedKnowledgeDocument` DTOs.
5. Register the class in `config/knowledge.php` under `knowledge.import.sources`.

Example skeleton:

```php
final class VaticanIiKnowledgeImporter extends AbstractFileKnowledgeImporter
{
    public function identifier(): string
    {
        return 'vatican_ii';
    }

    public function displayName(): string
    {
        return 'Vatican II';
    }

    public function supports(string $path): bool
    {
        return str_contains(strtolower(basename($path)), 'vatican-ii');
    }

    public function normalize(RawKnowledgeDocument $rawDocument): array
    {
        return [
            new NormalizedKnowledgeDocument(
                sourceType: 'council_document',
                sourceName: 'Vatican II',
                tradition: 'catholic',
                reference: 'Dei Verbum 1',
                title: 'Dei Verbum',
                content: trim($rawDocument->contents),
                language: $this->language($rawDocument),
                checksum: hash('sha256', trim($rawDocument->contents)),
                metadata: $this->provenance($rawDocument),
            ),
        ];
    }
}
```

Detailed instructions for the import workflow, including file formats and the Douay-Rheims Bible import, can be found in [docs/import-workflow.md](docs/import-workflow.md).

## Quality Commands

```bash
composer test
composer analyse
composer format
```

`composer analyse` runs Larastan/PHPStan. `composer format` runs Laravel Pint.

Feature tests use an in-memory SQLite database for speed. Local `composer test` requires the PHP `pdo_sqlite` extension. If your host PHP does not have it installed, run the same suite inside Docker:

```bash
docker compose up -d
composer test:docker
```

On Windows PHP installations, enable `pdo_sqlite` in `php.ini` or install a PHP build that includes it.

## Example Create Request

```bash
curl -X POST http://localhost:8080/api/documents \
  -H "Content-Type: application/json" \
  -d '{
    "source_type": "bible_verse",
    "source_name": "New American Bible Revised Edition",
    "tradition": "catholic",
    "reference": "John 3:16",
    "title": "The love of God",
    "content": "For God so loved the world that he gave his only Son.",
    "metadata": {
      "book": "John",
      "chapter": 3,
      "verse": 16
    }
  }'
```
