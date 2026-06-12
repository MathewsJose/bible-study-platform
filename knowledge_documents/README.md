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
- `app/Application`: DTOs, contracts, and orchestration services such as document CRUD, full-text search, and semantic search.
- `app/Infrastructure`: Eloquent persistence, pgvector SQL, dummy embeddings, and extensible importers.
- `app/Presentation`: HTTP controllers and request validation.

Eloquent models are treated as persistence records and are never returned directly from controllers. API responses are built from DTOs.

Repository abstraction is used only at the persistence boundary because the service has database-specific query behavior for full-text search and pgvector similarity search.

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

Semantic search uses `EmbeddingProviderInterface`. The current implementation is deterministic and local (`DummyEmbeddingProvider`) so the service can run without vendor credentials. OpenAI, Gemini, or local model providers can be added by binding a new provider to the interface.

## Database

The `knowledge_documents` table uses:

- UUID primary key
- JSONB metadata
- pgvector `vector(1536)` embeddings
- GIN metadata index
- GIN full-text search index
- HNSW cosine vector index

SQLite-compatible fallbacks exist only so the automated test suite can run quickly without a PostgreSQL container.

## Import Pipeline

The import boundary starts with `DocumentImporterInterface` and concrete importers:

- `BibleImporter`
- `CatechismImporter`
- `ChurchFatherImporter`

They share `AbstractDocumentImporter`, which keeps ingestion extensible while still relying on the same application service used by the API.

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
