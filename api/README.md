# Bible API

Laravel backend for a Bible study application. The service exposes Bible chapter lookup, historical context lookup, Church teaching lookup, and combined study reader endpoints backed by MongoDB-oriented repositories.

## Architecture

The API follows a layered, Clean Architecture style with Domain-Driven Design boundaries:

```text
+---------------------------+
| Interface Layer           | HTTP controllers, routes, response envelope
+---------------------------+
| Application Layer         | DTOs, use cases, application services
+---------------------------+
| Domain Layer              | Entities and repository contracts
+---------------------------+
| Infrastructure Layer      | MongoDB models and repository implementations
+---------------------------+
```

Dependency flow points inward:

- Controllers validate and translate HTTP requests.
- Application services orchestrate use cases and DTOs.
- Domain entities and repository interfaces define the business model.
- Infrastructure repositories implement persistence without leaking MongoDB details into controllers.

Current source layout:

```text
app/
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
```

The original project direction included Elasticsearch-backed search. The current code and Docker Compose setup do not run Elasticsearch yet, so search indexing should be treated as a future infrastructure adapter rather than an active runtime dependency.

## Stack

- Laravel 13
- PHP 8.5
- MongoDB via `mongodb/laravel-mongodb`
- Laravel Sanctum dependency is installed, although the current public endpoints are unauthenticated
- Docker Compose with PHP-FPM, Nginx, and MongoDB

## Runtime Services

`docker-compose.yml` starts:

- `bible-app`: PHP-FPM container built from `docker/Dockerfile`
- `bible-nginx`: Nginx container listening on host port `80`
- `bible-mongo`: MongoDB 6.0 container listening on host port `27017`

There is no Elasticsearch service or search route in the current project state.

## Setup

```bash
cp .env.example .env
docker-compose up -d
docker exec -it bible-app composer install
docker exec -it bible-app php artisan key:generate
docker exec -it bible-app php artisan migrate --force
docker exec -it bible-app php artisan db:seed
```

The application is served through Nginx at:

```text
http://localhost
```

For local CLI usage outside Docker, PHP 8.5 and the MongoDB extension are required.

The bundled seed data includes a small public-domain Douay-Rheims sample under `version=drb`. The preferred Catholic Bible import target is the Catholic Public Domain Version under `version=cpdv`, which can be loaded with `php artisan bible:import-cpdv --fresh`.

## API Contract

All new API endpoints return a consistent JSON envelope.

Success:

```json
{
  "success": true,
  "data": {}
}
```

Error:

```json
{
  "success": false,
  "message": "Descriptive error message.",
  "errors": {}
}
```

Validation errors return `400`. Missing Bible chapters return `404`. Missing history or teaching content returns a safe empty payload instead of a server error.

## Endpoints

### Get Bible Chapter

```http
GET /bible?book={book}&chapter={chapter}
```

Required query parameters:

- `book`: string
- `chapter`: integer, minimum `1`

Optional internal defaults:

- `language`: defaults to `en`
- `version`: defaults to `cpdv`

Example:

```bash
curl "http://localhost/bible?book=john&chapter=3&version=cpdv"
```

Example response:

```json
{
  "success": true,
  "data": {
    "book": "john",
    "chapter": 3,
    "version": "cpdv",
    "language": "en",
    "verses": [
      {
        "verse": 1,
        "text": "And there was a man of the Pharisees, named Nicodemus, a ruler of the Jews."
      }
    ]
  }
}
```

### Get Historical Context

```http
GET /history?book={book}&chapter={chapter}&verse={verse}
```

Required query parameters:

- `book`: string
- `chapter`: integer, minimum `1`
- `verse`: integer, minimum `1`

Example:

```bash
curl "http://localhost/history?book=john&chapter=3&verse=16&version=cpdv"
```

Example empty-content response:

```json
{
  "success": true,
  "data": {
    "book": "john",
    "chapter": 3,
    "verse": 16,
    "history": {
      "summary": null,
      "details": null,
      "references": []
    }
  }
}
```

### Get Church Teachings

```http
GET /teachings?book={book}&chapter={chapter}&verse={verse}
```

Required query parameters:

- `book`: string
- `chapter`: integer, minimum `1`
- `verse`: integer, minimum `1`

Example:

```bash
curl "http://localhost/teachings?book=john&chapter=3&verse=16&version=cpdv"
```

Example empty-content response:

```json
{
  "success": true,
  "data": {
    "book": "john",
    "chapter": 3,
    "verse": 16,
    "teachings": {
      "summary": null,
      "details": null,
      "tradition": "Catholic",
      "references": []
    }
  }
}
```

### Get Combined Study Payload

```http
GET /study?book={book}&chapter={chapter}&verse={verse}
```

Required query parameters:

- `book`: string
- `chapter`: integer, minimum `1`

Optional query parameters:

- `verse`: integer, minimum `1`
- `language`: defaults to `en`
- `version`: defaults to `cpdv`

Example:

```bash
curl "http://localhost/study?book=john&chapter=3&verse=16&version=cpdv"
```

Example response:

```json
{
  "success": true,
  "data": {
    "bible": {
      "book": "john",
      "chapter": 3,
      "version": "cpdv",
      "language": "en",
      "verses": []
    },
    "history": {
      "book": "john",
      "chapter": 3,
      "verse": 16,
      "history": {
        "summary": null,
        "details": null,
        "references": []
      },
      "items": []
    },
    "teachings": {
      "book": "john",
      "chapter": 3,
      "verse": 16,
      "teachings": {
        "summary": null,
        "details": null,
        "tradition": "Catholic",
        "references": []
      },
      "items": []
    }
  }
}
```

### Legacy Verse Lookup

```http
GET /api/verse/{book}/{chapter}/{verse}
```

This route is kept for existing clients. New client work should prefer the query-based endpoints above.

## Knowledge Integration Layer

Sprint 16 adds a clean HTTP boundary from the core Bible Study API to the reusable `knowledge_documents` service. The core API depends on stable application contracts and DTOs, not retrieval, pgvector, graph, LLM, or agent implementation details.

Actual architecture:

```text
Nuxt frontend
  -> Core API routes (/v1/knowledge/*)
  -> KnowledgeServiceClientInterface
  -> HttpKnowledgeServiceClient
  -> knowledge_documents routes (/api/v1/knowledge/*)
```

The core API owns users, Sanctum authentication, user preferences, study progress, bookmarks, and Bible-study workflows. The knowledge service owns knowledge documents, embeddings, retrieval, graph traversal, AI answers, and agent orchestration.

Configuration:

```env
KNOWLEDGE_SERVICE_URL=http://host.docker.internal:8080
KNOWLEDGE_SERVICE_TOKEN=
KNOWLEDGE_SERVICE_CONNECT_TIMEOUT=2
KNOWLEDGE_SERVICE_TIMEOUT=10
KNOWLEDGE_SERVICE_RETRY_ATTEMPTS=2
KNOWLEDGE_SERVICE_RETRY_SLEEP_MS=150
KNOWLEDGE_AI_RATE_LIMIT_PER_MINUTE=10
```

The two Docker Compose projects currently run on separate Docker networks. From the `api` container, use `http://host.docker.internal:8080` on Docker Desktop. From host-based PHP, use `http://localhost:8080`. A future shared Compose/network file could replace this with container DNS.

Core API endpoints for frontend consumers:

```http
GET  /v1/knowledge/search
GET  /v1/knowledge/reference/{reference}
GET  /v1/knowledge/related/{document}
POST /v1/knowledge/retrieve
POST /v1/knowledge/answer
POST /v1/knowledge/agents/run
GET  /v1/knowledge/agents/executions/{id}
POST /v1/knowledge/agents/executions/{id}/replay
GET  /v1/knowledge/agent-replays/{id}
```

Authorization decision:

- `search`, `reference`, `related`, and `retrieve` are read-only and follow the existing public Bible/study endpoint model with `throttle:api`.
- `answer`, `agents/run`, trace inspection, and agent replay require `auth:sanctum` and use the stricter `throttle:knowledge-ai` limiter because they can hit expensive AI/provider workflows.

Error envelope remains consistent with the rest of the API:

```json
{
  "success": false,
  "message": "Knowledge service unavailable.",
  "errors": {
    "service": ["Unable to connect to the knowledge service."]
  }
}
```

Correlation IDs:

- The frontend may send `X-Request-ID`.
- The core API generates one if missing.
- `HttpKnowledgeServiceClient` forwards it to the knowledge service as `X-Request-ID`.
- The response includes `data.request_id`.

Frontend request examples:

```bash
curl "http://localhost/v1/knowledge/search?query=incarnation&source_type=catechism&limit=5"
curl "http://localhost/v1/knowledge/reference/CCC%20456"
curl "http://localhost/v1/knowledge/related/John%201%3A14?depth=1"
```

Authenticated AI answer example:

```bash
curl -X POST "http://localhost/v1/knowledge/answer" \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "question": "Why did Jesus become man?",
    "filters": {
      "source_types": ["catechism", "bible_verse"],
      "tradition": "catholic"
    }
  }'
```

Authenticated agent example:

```bash
curl -X POST "http://localhost/v1/knowledge/agents/run" \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "input": "Explain why Jesus became man according to Scripture, Catechism, and the Fathers.",
    "profile": "catholic_research",
    "max_steps": 8
  }'
```

Authenticated trace inspection example:

```bash
curl "http://localhost/v1/knowledge/agents/executions/TRACE_ID" \
  -H "Authorization: Bearer TOKEN"
```

Trace responses include persisted execution metadata, timing, tools used, provider/model metrics when available, and redacted step metadata. The frontend should display trace summaries for diagnostics without assuming internal agent state shape.

Replay example:

```bash
curl -X POST "http://localhost/v1/knowledge/agents/executions/TRACE_ID/replay" \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "dry_run": true,
    "strict": false
  }'
```

Replay status:

```bash
curl "http://localhost/v1/knowledge/agent-replays/REPLAY_ID" \
  -H "Authorization: Bearer TOKEN"
```

The core API only forwards `strict` and `dry_run`; provider/model overrides are not exposed over HTTP. Replay compares persisted traces against the current knowledge service configuration and corpus. It does not guarantee identical LLM text.

## Knowledge Service MCP

The MCP server belongs to `knowledge_documents/`, not the core API. External MCP-compatible clients should connect directly to the Knowledge Service MCP endpoint:

```http
POST http://localhost:8080/mcp/knowledge
```

The endpoint is bearer-token protected by `MCP_TOKEN`, disabled by default, and exposes only read-only knowledge tools. The core API continues to serve frontend-facing authenticated REST endpoints and does not proxy MCP traffic.

The client does not accept arbitrary URLs from users, so this integration does not introduce SSRF exposure. Secrets are read from config only and must not be logged or committed.

## Validation Example

Request:

```bash
curl "http://localhost/bible?book=john"
```

Response:

```json
{
  "success": false,
  "message": "Invalid bible chapter request.",
  "errors": {
    "chapter": [
      "The chapter field is required."
    ]
  }
}
```

## Extensibility Notes

The application services already accept `language` and `version` arguments internally:

- `getBibleChapter(language, version, book, chapter)`
- `getHistoricalContext(language, version, book, chapter, verse)`
- `getTeachings(language, version, book, chapter, verse)`
- `getStudyPayload(language, version, book, chapter, verse)`

The current default version is `cpdv` and the current default language is `en`. The repository/model boundaries are intended to support additional Bible versions, languages, and content sources without rewriting the controllers.

Import CPDV Bible text:

```bash
php artisan bible:import-cpdv --fresh
```

Planned extension points include:

- Full-text search through a dedicated search adapter
- Additional Bible translations loaded from licensed sources
- User-specific study notes and saved passages
- Cross-references, maps, timelines, and commentary modules
- Caching for high-traffic lookup endpoints

## Knowledge Service AI Security

The Core API remains separate from `knowledge_documents`; it does not share Eloquent models or proxy MCP. AI security controls live in the Knowledge Service at the application boundary for retrieval, answer generation, agents, replay, and MCP tools.

Implemented in the Knowledge Service:

- deterministic PII detection with allow/redact/block policy
- deterministic prompt-injection blocking
- explicit tool permissions, read-only status, risk level, and approval boundary
- separate rate limits for retrieval, answer, agent, replay, and MCP
- external LLM provider policy through `AI_ALLOW_EXTERNAL_PROCESSING`
- trace/log redaction for secrets and personal data

The Core API should pass user requests over HTTP and treat Knowledge Service security errors such as `PROMPT_INJECTION_DETECTED`, `PII_POLICY_BLOCKED`, `RESOURCE_LIMIT_EXCEEDED`, and `EXTERNAL_PROCESSING_DISABLED` as safe client-facing failures.

This is a technical guardrail layer, not a legal GDPR compliance claim. Legal compliance depends on the full product, hosting, policies, contracts, and operational practices.

## Knowledge Service LLM Providers

LLM provider abstraction belongs to the `knowledge_documents` service. The Core API does not call OpenAI, Anthropic, Google, Ollama, or local model servers directly, and it does not store provider credentials.

Knowledge Service provider flow:

```text
Core API /v1/knowledge/answer or /v1/knowledge/agents/run
  -> Knowledge Service
  -> LLMGatewayInterface / LlmGateway
  -> LlmModelRouter
  -> LlmProviderRegistry
  -> configured provider/model
```

Useful Knowledge Service diagnostics:

```bash
cd ../knowledge_documents
php artisan ai:providers
php artisan ai:llm-health
php artisan ai:providers:health
php artisan ai:model:compare --models=null:null-answer-model,null:null-answer-model --type=safety
```

External providers require Knowledge Service configuration and must pass `AI_ALLOW_EXTERNAL_PROCESSING`. PII handling, prompt-injection checks, and provider policy are enforced before provider calls. Provider availability depends on local endpoints or credentials; the application does not claim GDPR compliance or EU residency from provider routing alone.

## Private Alpha Feedback

The Core API owns the public Private Alpha feedback endpoint:

```http
POST /v1/knowledge/answers/feedback
```

This route is authenticated with Sanctum and rate-limited separately by `KNOWLEDGE_FEEDBACK_RATE_LIMIT_PER_MINUTE`. It accepts the safe answer `request_id`, `rating` (`helpful` or `not_helpful`), optional negative `reason`, and safe telemetry such as provider/model, retrieval strategy, source count, citation count, and latency when the frontend has those values.

Feedback is stored in `ai_answer_feedback`. Duplicate feedback from the same user for the same `request_id` updates the existing row. The table intentionally avoids storing full prompts or full answers. Optional comments are supported by the schema but are not stored unless `KNOWLEDGE_FEEDBACK_STORE_COMMENTS=true`; leave this disabled during early alpha unless a privacy policy and retention process are in place.

Diagnostic command:

```bash
php artisan ai:feedback:health --days=30
php artisan ai:feedback:health --format=json
```

The command prints aggregate counts and top negative reasons only; it does not print personal comments.

## Knowledge Service Evaluation

Production AI evaluation belongs to the `knowledge_documents` service. The Core API does not proxy evaluation mutation endpoints to frontend clients.

Use the Knowledge Service CLI for regression checks:

```bash
cd ../knowledge_documents
php artisan ai:evaluate --type=retrieval --strategy=hybrid --top-k=5 --save
php artisan ai:evaluate --type=answer --limit=5 --save
php artisan ai:evaluate --type=agent --save
php artisan ai:evaluate --type=safety --save
php artisan ai:evaluate:compare --baseline=BASELINE_RUN_ID --current=CURRENT_RUN_ID
```

Saved runs include dataset metadata, retrieval/AI/security configuration, corpus fingerprints, metrics, warnings, and per-question results. The evaluator checks deterministic evidence such as expected references, citation correctness, source coverage, tool selection, and safety policy outcomes; it does not claim to prove theological truth.

## Testing

Tests are configured to prefer the committed `.env.testing` file. If `.env` is missing, the test bootstrap falls back to `.env.example` so `php artisan test` can run from a fresh clone without manual environment setup.

Run the focused API tests:

```bash
php artisan test tests\Feature\BibleApiTest.php
```

Run the full test suite:

```bash
php artisan test
```

The full suite is designed to run from a fresh clone without creating a local `.env` first.

## Useful Commands

```bash
php artisan route:list
php artisan route:list --path=bible
php artisan route:list --path=history
php artisan route:list --path=study
php artisan route:list --path=teachings
```

## License

MIT
