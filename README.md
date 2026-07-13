# Bible Study Platform

A monorepo for a Bible study application built as a split frontend/backend architecture.

The repository contains:

- `frontend/` – Nuxt 4 single-page application for scripture reading, history, and teachings.
- `api/` – Laravel backend implementing a clean, layered API for Bible text, historical context, and church teaching content.
- `knowledge_documents/` – Specialized Laravel backend for theological document indexing and RAG support (PostgreSQL + pgvector).

## Architecture Overview

This project is intentionally organized as a monorepo with separate frontend and backend concerns.

### Frontend

- Framework: Nuxt 4 with Vue 3
- State management: Pinia
- Styling: Tailwind CSS v4
- Purpose: present an uninterrupted Bible reading experience, selectable verses, and context panels for history and teaching notes.

### Backend (Core & Knowledge)

- Framework: Laravel 13
- Language: PHP 8.5
- Core Data: MongoDB-backed repositories (verses, history)
- Knowledge Data: PostgreSQL 17 + pgvector (theological docs, embeddings)
- Pattern: Clean Architecture with Domain-Driven Design (DDD) boundaries

The backend is intentionally structured as a DDD-friendly, layered API service:

- `app/Interfaces/Http` handles HTTP controllers, input validation, response envelopes, and routing
- `app/Application` contains use cases, DTOs, application services, and orchestration logic
- `app/Domain` defines business entities, value objects, and repository contracts for Bible, history, and teaching domains
- `app/Infrastructure` implements persistence adapters, MongoDB models, and repository implementations

### Knowledge Service (`knowledge_documents/`)

- Purpose: specialized indexing of theological documents, RAG support, and semantic search.
- Tech: PostgreSQL 17 with `pgvector` for similarity search.
- Integration: OpenAI for generating document embeddings.

This architecture makes the backend easier to reason about, test, and extend:

- domain rules live in the Domain layer, not in controllers
- application workflows are explicit and reusable
- persistence details are isolated behind repository contracts
- new content sources or search adapters can be added without rewriting business logic

The API is designed for a stable client contract and clean separation between business behavior and infrastructure.

## Technology Stack

- Root: Node.js / npm
- Frontend: Nuxt 4, Vue 3, Pinia, Tailwind CSS, ofetch
- Backend: Laravel 13, PHP 8.5, MongoDB, Sanctum installed for possible future auth
- Knowledge Service: PostgreSQL 17, pgvector, OpenAI API
- Infrastructure: Docker Compose with PHP-FPM, Nginx, MongoDB, and PostgreSQL

## Repository Layout

```text
.
├── api/                Laravel backend service
│   ├── app/            Cleanly layered application code
│   ├── bootstrap/
│   ├── config/
│   ├── database/
│   ├── docker/
│   ├── public/
│   ├── resources/
│   ├── routes/
│   ├── storage/
│   └── tests/
├── frontend/           Nuxt frontend application
│   ├── public/
│   ├── src/
│   ├── tests/
│   ├── nuxt.config.ts
│   └── package.json
├── knowledge_documents/ Specialized knowledge backend
│   ├── app/            Cleanly layered application code
│   ├── database/       PostgreSQL migrations
│   ├── docker/
│   └── tests/          Pest PHP tests
├── package.json        Root convenience scripts
└── README.md           Project overview and guide
```

### API Source Layout

The backend app is arranged by layers:

```text
api/app/
+-- Domain/
|   +-- Bible/
|   +-- History/
|   +-- Teachings/
+-- Application/
|   +-- Bible/
|   +-- History/
|   +-- Teachings/
+-- Infrastructure/
|   +-- Bible/Persistence/Mongo/
|   +-- History/Persistence/Mongo/
|   +-- Teachings/Persistence/Mongo/
+-- Interfaces/
    +-- Http/

knowledge_documents/app/
+-- Domain/
|   +-- Knowledge/ (Entities, Enums, ValueObjects)
+-- Application/
|   +-- Knowledge/ (Services, DTOs, Contracts)
+-- Infrastructure/
|   +-- Knowledge/ (Persistence, Embedding, Importers)
+-- Presentation/
    +-- Http/ (Controllers, Requests)
```

## Run and Develop Locally

### Install dependencies

```bash
# Root and Frontend
npm install
npm --prefix frontend install

# Backend Services
cd api && composer install
cd ../knowledge_documents && composer install
```

### Frontend development

```bash
npm run frontend:dev
```

### Backend development

```bash
npm run api:dev
```

### Run tests

```bash
npm run frontend:test
npm run api:test
```

## Docker Setup

Each backend service includes its own Docker Compose configuration.

### Core API

```bash
cd api
docker-compose up -d
docker exec -it bible-app composer install
docker exec -it bible-app php artisan key:generate
docker exec -it bible-app php artisan migrate --force
docker exec -it bible-app php artisan db:seed
```

### Knowledge Service

```bash
cd knowledge_documents
docker-compose up -d
docker exec -it knowledge_documents-app-1 composer install
docker exec -it knowledge_documents-app-1 php artisan key:generate
docker exec -it knowledge_documents-app-1 php artisan migrate --force
```

Note: Container names may vary depending on your Docker version. Check `docker ps` to verify.

Availability:
- **Core API**: Available at `http://localhost`
- **Knowledge Service**: Available at `http://localhost:8080`
- **PgAdmin**: Available at `http://localhost:5050` (for Knowledge Service)

## API Contract

The backend exposes a consistent JSON envelope and stable query-driven API for the study reader.

### Endpoints

#### Bible chapter lookup

```http
GET /bible?book={book}&chapter={chapter}&version={version}
```

- `book`: string
- `chapter`: integer, minimum `1`
- `version`: optional, defaults to `cpdv`
- `language`: optional, defaults to `en`

#### Historical context lookup

```http
GET /history?book={book}&chapter={chapter}&verse={verse}&version={version}
```

- `book`: string
- `chapter`: integer, minimum `1`
- `verse`: integer, minimum `1`

#### Church teachings lookup

```http
GET /teachings?book={book}&chapter={chapter}&verse={verse}&version={version}
```

#### Combined study payload

```http
GET /study?book={book}&chapter={chapter}&verse={verse}
```

- `book`: string
- `chapter`: integer, minimum `1`
- `verse`: optional, integer minimum `1`
- `language`: optional, defaults to `en`
- `version`: optional, defaults to `cpdv`

#### Legacy verse lookup

```http
GET /api/verse/{book}/{chapter}/{verse}
```

This route is maintained for compatibility, but new clients should prefer the query-based endpoints above.

### Knowledge Service Endpoints

These endpoints are available on the Knowledge Service (default port `8080`).

#### Search documents (Keyword)

```http
POST /api/documents/search
```

- `query`: string
- `limit`: optional, integer

#### Semantic search (AI-based)

```http
POST /api/documents/semantic-search
```

- `embedding`: array of floats
- `limit`: optional, integer
- `threshold`: optional, float

#### Document management

- `GET /api/documents`: List documents
- `GET /api/documents/{id}`: Get document details
- `POST /api/documents`: Create document
- `PUT /api/documents/{id}`: Update document
- `DELETE /api/documents/{id}`: Delete document

### Standard response envelope

Success:

```json
{
  "success": true,
  "data": { ... }
}
```

Error:

```json
{
  "success": false,
  "message": "Descriptive error message.",
  "errors": { ... }
}
```

Validation errors return `400`. Missing Bible chapters return `404`. Missing history or teaching content returns a safe empty payload instead of a server error.

## Frontend Runtime

The Nuxt app expects an API base URL via runtime configuration:

- `NUXT_PUBLIC_API_BASE_URL`
- fallback: `VITE_API_BASE_URL`

If no API URL is configured, the frontend uses local sample content for demo chapters.

## Commands

### Root scripts

```bash
npm run frontend:dev
npm run frontend:build
npm run frontend:test
npm run frontend:check
npm run api:dev
npm run api:test
npm run knowledge:dev
npm run knowledge:test
```

### Frontend commands

From `frontend/`:

```bash
npm run dev
npm run build
npm run test
npm run check
npm run preview
```

### Core API commands

From `api/`:

```bash
composer install
php artisan serve
php artisan test
```

### Knowledge Service commands

From `knowledge_documents/`:

```bash
composer install
php artisan serve
php artisan test
```

## Notes for Architects

- The backend design isolates HTTP and persistence adapters from domain rules.
- The frontend separates UI state, selection orchestration, and API/service access.
- The monorepo structure supports independent frontend and backend evolution while keeping shared project context in one workspace.
- Advanced search and indexing are implemented in the `knowledge_documents` service using `pgvector`.

## Future Opportunities

- Add authenticated user bookmarks and cross-device sync
- Introduce deeper route linking for book/chapter/verse state
- Upgrade the API to support broader translation licensing workflows
- Expand frontend studies with annotations, reading plans, and notes
