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

## Retrieval Evaluation

Retrieval evaluation measures whether semantic search returns the Catholic sources we expected before any future LLM answer generation is added. This matters because RAG quality depends first on retrieval quality: if the system fails to retrieve `CCC 457` or `John 1:14` for "Why did Jesus become man?", a later answer generator will be grounded in the wrong material.

Evaluation data is stored in `evaluation_questions`, not hardcoded in services. Each question stores expected references and expected source types. Detailed runs are stored in `retrieval_evaluation_runs`, and aggregate summaries are stored in `retrieval_evaluation_summaries` with the embedding model, provider, dimensions, top K, minimum score, and filters used for reproducibility.

Seed development evaluation questions:

```bash
php artisan db:seed --class=EvaluationQuestionSeeder
```

The seeder contains about 20 Catholic retrieval questions across Christology, Sacraments, Grace, Trinity, Mary, Salvation, and Scripture. It only stores expected references that currently exist in `knowledge_documents`; missing references are printed as warnings instead of invented.

Run a baseline evaluation:

```bash
php artisan evaluate --top-k=5 --minimum-score=0 --save
```

Useful filters:

```bash
php artisan evaluate --category=christology
php artisan evaluate --question-id=UUID
php artisan evaluate --limit=10
```

API evaluation endpoint:

```http
POST /api/evaluations/retrieval
```

```json
{
  "top_k": 5,
  "minimum_score": 0.7,
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
      "retrieval": "semantic_vector",
      "embedding_model": "text-embedding-3-small",
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
docker compose exec app php artisan evaluate --top-k=5 --minimum-score=0 --save
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

The import boundary starts with `DocumentImporterInterface` and concrete importers:

- `BibleImporter`
- `DouayRheimsImporter`
- `CatechismImporter`
- `ChurchFatherImporter`

They share `AbstractDocumentImporter`, which keeps ingestion extensible while still relying on the same application service used by the API.

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
