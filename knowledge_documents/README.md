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

The seeder contains 59 Catholic evaluation questions across Christology, Sacraments, Grace, Trinity, Mary, Salvation, Scripture, Church, saints, Church Fathers, and evaluation-specific cross-source scenarios. It only stores expected references that currently exist in `knowledge_documents`; missing references are printed as warnings instead of invented.

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
    "total_questions": 59,
    "hit_rate": 0.85,
    "precision": 0.61,
    "recall": 0.72,
    "mrr": 0.79,
    "ndcg": 0.82,
    "source_coverage": 0.76,
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
- NDCG@K: rewards expected references appearing higher in the ranked list.
- Source coverage: whether the result set includes the expected source families, such as both `catechism` and `bible_verse` for cross-source questions.

## Production AI Evaluation

Sprint 21 extends the existing retrieval diagnostics into a production regression platform. It reuses the same database-backed `evaluation_questions` dataset and adds deterministic evaluation for answer grounding, citation quality, agent tool planning, and safety guardrails.

```text
evaluation_questions
  -> ai:evaluate
  -> retrieval / answer / agent / safety evaluators
  -> ai_evaluation_runs
  -> ai_evaluation_results
  -> ai:evaluate:compare
```

Dataset fields:

- `category` and `difficulty`
- `intended_references`, `expected_references`, and `missing_references`
- `expected_source_types`
- `expected_answer_facts`
- `required_citations`
- `coverage_status`
- JSON `metadata`, including whether the scenario requires multiple source types

Run evaluation:

```bash
php artisan ai:evaluate --type=retrieval --strategy=hybrid --top-k=5 --limit=10 --save
php artisan ai:evaluate --type=answer --limit=5 --save
php artisan ai:evaluate --type=agent --save
php artisan ai:evaluate --type=safety --save
php artisan ai:evaluate --all --limit=10 --save --name=nightly-ai-eval
```

Machine-readable output:

```bash
php artisan ai:evaluate --type=safety --format=json
```

Saved runs capture configuration and fingerprints for regression analysis:

- retrieval strategy, top K, category, and difficulty filters
- AI provider/model and embedding model
- security policy configuration
- corpus fingerprint from the replay subsystem
- evaluation threshold configuration

Compare two saved runs:

```bash
php artisan ai:evaluate:compare --baseline=BASELINE_RUN_ID --current=CURRENT_RUN_ID
php artisan ai:evaluate:compare --baseline=BASELINE_RUN_ID --current=CURRENT_RUN_ID --format=json
```

Regression thresholds are environment-driven:

```env
EVAL_MINIMUM_AVERAGE_SCORE=0.50
EVAL_MINIMUM_HIT_AT_K=0.50
EVAL_MINIMUM_MRR=0.20
EVAL_MINIMUM_CITATION_CORRECTNESS=0.70
EVAL_MAXIMUM_LATENCY_MS=5000
EVAL_MAXIMUM_FAILURE_RATE=0.30
EVAL_MAXIMUM_SCORE_DROP=0.05
```

Answer evaluation is deterministic. It checks whether cited references exist, whether citations came from retrieved supporting documents, whether required citations are present, whether expected source types are covered, and whether expected answer facts are grounded in the answer or supporting context. It does not judge theological truth beyond the explicit dataset facts and corpus references.

Agent evaluation checks deterministic planner behavior against configured scenarios: expected tools, missing tools, unnecessary tools, duplicate calls, failed tool slots, and security blocks. Safety evaluation checks PII handling, prompt-injection blocking, resource limits, and provider/tool guardrail behavior.

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

## Knowledge Graph Engine

The Knowledge Graph Engine stores explicit relationships between imported `knowledge_documents`. It does not infer relationships with AI and it does not change vector, lexical, hybrid, or evaluation behavior.

Architecture:

```text
knowledge_documents metadata
  -> ReferenceResolverInterface implementations
  -> KnowledgeGraphBuilder
  -> KnowledgeGraphRepositoryInterface
  -> knowledge_document_relationships
  -> diagnostics and related-document services
```

Supported relationship types:

- `SCRIPTURE_REFERENCE`
- `CATECHISM_REFERENCE`
- `CHURCH_FATHER_REFERENCE`
- `RELATED_VERSE`
- `SAME_TOPIC`
- `PART_OF`
- `COMMENTS_ON`
- `REFERENCES`
- `FULFILLS`
- `QUOTES`

Graph edges are stored in `knowledge_document_relationships` with source document, target document, relationship type, confidence, provenance, metadata, and timestamps. A unique edge index prevents duplicate relationships for the same source, target, and type. Relationship type is a string field so future explicit types can be added without a schema change.

Current resolvers:

- `ScriptureReferenceResolver`: resolves `scripture_references` and scripture-shaped `cross_references` to Bible documents.
- `CatechismReferenceResolver`: resolves `internal_references`, `catechism_references`, and `CCC 123` references in `cross_references`.
- `ChurchFatherReferenceResolver`: resolves explicit patristic references to Church Father documents.

CLI usage:

```bash
php artisan graph:rebuild
php artisan graph:rebuild --source-type=catechism
php artisan graph:update --document-id=UUID
php artisan graph:verify
php artisan knowledge:status
```

`graph:rebuild` and `graph:update` rebuild outgoing relationships for the selected documents. This removes stale edges when metadata changes and recreates only relationships whose targets can be resolved in the existing corpus.

Diagnostics include:

- total graph nodes
- total graph edges
- relationship counts
- disconnected nodes
- duplicate relationships
- broken references
- average degree
- graph density

Application services:

- `KnowledgeGraphBuilder`: scans documents, resolves explicit references, persists relationships, logs progress, and dispatches graph events.
- `KnowledgeGraphDiagnosticsService`: produces integrity and connectivity metrics.
- `RelatedDocumentsService`: returns related documents grouped by relationship type for future navigation and RAG workflows.
- `KnowledgeGraphRepositoryInterface`: keeps relationship, traversal, count, and diagnostics queries behind one persistence boundary.

Graph events:

- `DocumentImported`
- `RelationshipCreated`
- `RelationshipRemoved`
- `GraphUpdated`

To add a future graph source, implement `ReferenceResolverInterface`, read only explicit references from metadata or trusted source fields, resolve to existing `knowledge_documents`, and register the resolver in `AppServiceProvider` where `KnowledgeGraphBuilder` is bound.

## Advanced Retrieval Engine

Sprint 13 adds a deterministic retrieval engine as the single entry point for future AI and product retrieval workflows. Existing vector, lexical, and hybrid endpoints remain backwards compatible.

Pipeline:

```text
Query
  -> QueryAnalyzer
  -> QueryExpansionService
  -> MetadataFilterService
  -> SemanticSearchService
  -> LexicalSearchService
  -> GraphExpansionService
  -> RetrievalFusionService
  -> RerankerService
  -> ContextBuilder
  -> RetrievalResult
```

The engine does not call LLMs or external APIs. Expansion and reranking use configured terms, explicit metadata, source authority, and knowledge graph relationships.

API:

```http
POST /api/retrieval
```

```json
{
  "query": "Why did Jesus become man?",
  "profile": "ai_answer",
  "top_k": 10,
  "context_limit": 8,
  "include_explanations": true,
  "filters": {
    "source_types": ["catechism", "bible_verse"],
    "tradition": "catholic",
    "theological_topic": "incarnation"
  }
}
```

Response shape:

```json
{
  "data": {
    "query": {
      "primary_intent": "natural_language_question",
      "references": [],
      "topics": ["incarnation"]
    },
    "profile": "ai_answer",
    "expansion": {
      "terms": ["Word became flesh"],
      "references": ["John 1:14", "CCC 456"]
    },
    "context": [
      {
        "reference": "CCC 456",
        "score": 0.92,
        "score_breakdown": {
          "vector": 0.88,
          "lexical": 0.71,
          "graph": 0.0,
          "metadata": 1,
          "authority": 1,
          "reranked": 0.92
        },
        "stages": ["vector", "lexical", "rerank"],
        "explanations": [
          "Selected by semantic vector similarity.",
          "Boosted because source type is authoritative for Catholic retrieval."
        ]
      }
    ],
    "diagnostics": {
      "timings_ms": {
        "query_analysis": 1,
        "query_expansion": 0,
        "vector_retrieval": 35,
        "lexical_retrieval": 8,
        "graph_expansion": 4,
        "fusion": 0,
        "reranking": 0,
        "context_builder": 0,
        "total": 48
      }
    }
  }
}
```

CLI diagnostics:

```bash
php artisan retrieval:pipeline "Why did Jesus become man?" --profile=ai_answer
php artisan retrieval:pipeline "John 1:14" --profile=cross_references --context-limit=12
```

Profiles live in `config/retrieval.php` and can tune behavior without code changes:

- `ai_answer`: balanced vector, lexical, graph, metadata, and authority scoring.
- `study_mode`: larger context and deeper graph traversal.
- `search`: less expansion and graph usage for direct search UX.
- `cross_references`: graph-heavy traversal for related source navigation.
- `research`: larger context and broader recall.

Query expansion is explicit and explainable. Example configured expansion:

```text
Incarnation
  -> Word became flesh
  -> Jesus became man
  -> John 1:14
  -> CCC 456
  -> CCC 457
  -> Athanasius
```

Metadata filters supported by the engine:

- source type
- source name
- author
- book
- chapter
- tradition
- language
- translation
- century
- theological topic
- relationship type

Diagnostics include stage timings, vector/lexical/graph result counts, expansion statistics, selected profile, and context size. These diagnostics are intended for future retrieval evaluation work such as graph contribution, expansion contribution, reranking improvement, and profile comparison.

## AI Answer Service

Sprint 14 adds a provider-independent AI Answer Engine on top of the retrieval engine. Sprint 22 adds `LLMGatewayInterface` as the application boundary for provider/model selection, so answer and agent workflows do not depend on provider SDKs or infrastructure adapters.

Answer pipeline:

```text
Question
  -> Advanced Retrieval Engine
  -> CitationBuilder
  -> PromptBuilder
  -> LLMGatewayInterface / LlmGateway
  -> LlmModelRouter
  -> LlmProviderRegistry
  -> LLMProviderInterface
  -> ResponseValidator
  -> ConfidenceScorer
  -> AnswerData DTO
```

Provider abstraction:

- `LLMGatewayInterface`: application-level gateway for completion requests, provider policy checks, capability checks, model routing, and safe fallback.
- `LLMProviderInterface`: completion, streaming-ready iterable output, token counting, metadata, provider identifier.
- `LlmModelRouter`: deterministic configuration-based selection for tasks such as answer generation, agent planning, summarization, classification, and evaluation.
- `LlmProviderRegistry`: resolves configured providers through dependency injection.
- `LlmModelRegistry`: records known model capabilities when they are explicitly configured.
- `NullProvider`: deterministic safe default for local development and tests.
- `LocalProvider`: OpenAI-compatible local endpoint adapter for local model servers.
- `OpenAIProvider`: HTTP-based chat completion adapter.
- `OllamaProvider`: local Ollama chat adapter.
- `AnthropicProvider` / `ClaudeProvider`: Anthropic Messages API adapter.
- `GoogleProvider` / `GeminiProvider`: Google Generative Language adapter.

Answer behavior still lives in `config/ai.php`. Provider/model selection lives in `config/llm.php`:

```env
AI_PROVIDER=null
AI_MODEL=null-answer-model
AI_TEMPERATURE=0
AI_MAX_TOKENS=800
AI_TIMEOUT=30
AI_RETRY_ATTEMPTS=2
AI_RETRY_SLEEP_MS=250
AI_ANSWER_ONLY_FROM_CONTEXT=true
AI_REQUIRE_CITATIONS=true
OPENAI_API_KEY=
OPENAI_CHAT_URL=https://api.openai.com/v1/chat/completions
OLLAMA_CHAT_URL=http://ollama:11434/api/chat
LLM_DEFAULT_PROVIDER=null
LLM_DEFAULT_MODEL=null-answer-model
LLM_PROFILE_ANSWER_GENERATION=fast_local
LLM_FAST_LOCAL_PROVIDER=null
LLM_FAST_LOCAL_MODEL=null-answer-model
LLM_LOCAL_BASE_URL=
LLM_LOCAL_MODEL=local-model
LLM_OPENAI_API_KEY=
LLM_OPENAI_MODEL=gpt-4o-mini
LLM_ANTHROPIC_API_KEY=
LLM_ANTHROPIC_MODEL=claude-3-5-haiku-latest
LLM_GOOGLE_API_KEY=
LLM_GOOGLE_MODEL=gemini-1.5-flash
```

Local development does not require paid API credentials. The default `fast_local` profile points at the deterministic `null` provider. To use a local OpenAI-compatible server, set `LLM_FAST_LOCAL_PROVIDER=local` and `LLM_LOCAL_BASE_URL` to the local server base URL.

Provider policy:

- `AI_ALLOW_EXTERNAL_PROCESSING=false` blocks external providers such as OpenAI, Anthropic, and Google.
- Providers listed in `AI_LOCAL_PROVIDERS` are treated as local for policy purposes.
- PII handling uses the existing `AI_PII_ACTION`: `allow`, `redact`, or `block`.
- The provider layer never logs or displays API keys.

Failure behavior:

- Authentication, rate-limit, timeout, configuration, and provider errors are mapped to provider-independent LLM exceptions.
- The answer service fails safely with the configured insufficient-evidence response.
- Configured fallback profiles are attempted only after policy checks. A policy-denied external provider does not silently fall through to another external provider.
- Streaming and provider-specific tool calling are intentionally left as future extension points.

Usage and cost:

- `LLMCompletionResponse` records provider/model, latency, finish reason, token usage when supplied by the provider, and optional estimated cost.
- Cost is `null` unless a pricing table is explicitly configured in `config/llm.php`.
- Token and cost values are not fabricated.

Provider diagnostics:

```bash
php artisan ai:providers
php artisan ai:llm-health
php artisan ai:providers:health
php artisan ai:providers:health --format=json
```

Model comparison reuses the Sprint 21 evaluation engine and persists normal evaluation runs:

```bash
php artisan ai:model:compare --models=null:null-answer-model,null:null-answer-model --type=safety
php artisan ai:model:compare --models=local:local-model,openai:gpt-4o-mini --type=answer --limit=5 --format=json
```

External model comparisons require credentials and must pass `AI_ALLOW_EXTERNAL_PROCESSING`.

## Private Alpha Feedback Loop

The Private Alpha user flow stays behind the Core API boundary:

```text
Nuxt /ask
  -> Core API /v1/knowledge/answer
  -> Knowledge Service /api/v1/knowledge/answer
  -> AI Answer Service
  -> LLM Gateway
  -> Core API /v1/knowledge/answers/feedback
```

The frontend displays the answer, returned citations, supporting Bible/Catechism/Church Father sources, and citation-opening through `GET /v1/knowledge/reference/{reference}`. It never calls the Knowledge Service directly.

Core API feedback stores:

- authenticated `user_id`
- answer `request_id`
- Helpful / Not helpful rating
- optional negative reason
- safe provider/model/retrieval/source/citation telemetry when available

It does not store full prompts or full answers. Optional comments are not persisted by default; keep `KNOWLEDGE_FEEDBACK_STORE_COMMENTS=false` unless a privacy and retention policy is ready.

Useful Core API command:

```bash
php artisan ai:feedback:health
```

Automated evaluation remains deterministic engineering evidence. User feedback is real-world alpha evidence. Neither alone proves theological correctness, and users should verify important conclusions against cited sources and Church teaching.

API:

```http
POST /api/answers
```

```json
{
  "question": "Why did Jesus become man?",
  "profile": "ai_answer",
  "filters": {
    "source_types": ["catechism", "bible_verse"],
    "tradition": "catholic"
  }
}
```

Response shape:

```json
{
  "data": {
    "question": "Why did Jesus become man?",
    "answer": "Jesus became man for our salvation [1].",
    "citations": [
      {
        "number": 1,
        "reference": "CCC 457",
        "source_type": "catechism",
        "source_name": "Catechism of the Catholic Church"
      }
    ],
    "confidence": {
      "score": 0.84,
      "signals": {
        "retrieval_score": 0.91,
        "citation_coverage": 1,
        "source_authority": 1,
        "graph_support": 0.5
      }
    },
    "provider": "openai",
    "model": "gpt-4.1-mini",
    "warnings": [],
    "diagnostics": {
      "timings_ms": {
        "retrieval": 42,
        "prompt_builder": 1,
        "llm_provider": 830,
        "validation_confidence": 0,
        "total": 873
      }
    }
  }
}
```

CLI:

```bash
php artisan ai:answer "Why did Jesus become man?" --profile=ai_answer
```

Prompt Builder:

- inserts system guardrails
- orders retrieved context with citation numbers
- preserves provenance
- estimates prompt tokens
- accepts future conversation history

Citation Builder returns structured objects for Bible verses, Catechism paragraphs, Church Fathers, and future source types. The answer text is expected to cite supporting sources with bracketed numbers such as `[1]`.

Confidence scoring is deterministic and does not ask the LLM to grade itself. It considers retrieval score, citation coverage, source authority, and graph support, returning a normalized `0.0..1.0` score with explanations and signal breakdowns.

Response validation returns warnings for empty responses, missing citations, missing evidence, and prompt-failure or hallucination indicators. Guardrails are provider-independent and configurable.

Conversation readiness interfaces are present for future chat work:

- `ConversationContextInterface`
- `MemoryProviderInterface`
- `SessionContextInterface`

Answer evaluation scaffolding is provided by `AnswerEvaluationService` for groundedness, citation coverage, faithfulness, response completeness, and latency. Future benchmark datasets can build on this without changing provider implementations.

Saved evaluation runs and replay fingerprints now include LLM routing, profiles, provider, and model configuration. This helps explain provider/model changes without claiming exact reproduction of nondeterministic LLM output.

## Agentic AI Framework

Sprint 15 adds a controlled agent orchestration layer on top of retrieval, graph, and answer services. The agent does not call an LLM provider directly and it cannot execute arbitrary code. It selects explicitly registered read-only tools, validates every tool input, records a structured trace, and terminates on completion, failure, timeout, duplicate calls, or maximum steps.

Lifecycle:

```text
User request
  -> AgentInterface
  -> AgentPlannerInterface
  -> AgentToolRegistry
  -> ToolInterface validation
  -> Tool execution
  -> Agent observation
  -> AgentResponse with trace and diagnostics
```

Core components:

- `AgentInterface`: contract for `execute`, `plan`, `observe`, and `finalize`.
- `KnowledgeAgent`: execution loop, guardrails, events, traces, and final response assembly.
- `AgentState`: controlled state containing request, step number, actions, tool results, observations, errors, timestamps, and status.
- `ToolInterface`: MCP-ready internal tool boundary with input schema, output schema, permissions, timeout, read-only flag, and structured execution result.
- `AgentToolRegistry`: registers tools, resolves by name, lists tools, and prevents duplicate names.
- `DeterministicAgentPlanner`: first planner implementation using provider-independent rules.
- `LLMAgentPlanner`: placeholder adapter boundary for a future structured LLM planner.

Initial read-only tools:

- `bible_search`: search Bible verses and chapters.
- `scripture_reference`: resolve explicit references such as `John 1:14`.
- `catechism_search`: search CCC paragraphs.
- `church_father_search`: search patristic writings.
- `knowledge_graph`: traverse explicit document relationships.
- `advanced_retrieval`: run the advanced retrieval engine.
- `answer_generation`: call the existing AI Answer Service.

Agent profiles live in `config/agents.php`:

- `bible_study`
- `scripture_research`
- `catholic_research`
- `theological_research`

Each profile defines allowed tools, maximum steps, maximum tool calls, timeout, retrieval profile, answer profile, and system instructions. Environment controls:

```env
AGENT_DEFAULT_PROFILE=catholic_research
AGENT_PLANNER=deterministic
AGENT_MAX_STEPS=8
AGENT_MAX_TOOL_CALLS=8
AGENT_TIMEOUT=45
AGENT_TOOL_TIMEOUT=15
```

API:

```http
POST /api/agents/run
```

```json
{
  "input": "Explain why Jesus became man according to Scripture, Catechism, and the Fathers.",
  "profile": "catholic_research",
  "filters": {
    "source_types": ["bible_verse", "catechism", "church_father"],
    "tradition": "catholic"
  },
  "max_steps": 8
}
```

Response shape:

```json
{
  "data": {
    "agent_id": "uuid",
    "request_id": "uuid",
    "status": "completed",
    "answer": "Jesus became man for our salvation [1].",
    "tool_results": [
      {
        "tool": "advanced_retrieval",
        "successful": true,
        "status": "success",
        "latency_ms": 42
      }
    ],
    "trace": [
      {
        "event": "agent_started",
        "status": "running",
        "step": 0
      }
    ],
    "diagnostics": {
      "profile": "catholic_research",
      "steps": 2,
      "tool_calls": 2,
      "tools_used": ["advanced_retrieval", "answer_generation"]
    }
  }
}
```

CLI:

```bash
php artisan agent:tools
php artisan agent:health
php artisan agent:run "Why did Jesus become man?" --profile=catholic_research
php artisan agent:evaluate
php artisan agent:trace --id=AGENT_ID
```

Events emitted for observability:

- `AgentStarted`
- `AgentStepStarted`
- `ToolExecutionStarted`
- `ToolExecutionCompleted`
- `ToolExecutionFailed`
- `AgentCompleted`
- `AgentFailed`

Guardrails implemented:

- maximum steps
- maximum tool calls
- profile and request tool allowlists
- schema validation
- unknown parameter rejection
- read-only tool enforcement
- duplicate tool-call detection
- timeout checks
- provider failure handling through existing answer service boundaries

Agent evaluation scenarios are configured in `config/agents.php` and exercised by `php artisan agent:evaluate`. The command reports task success rate, tool selection accuracy, unnecessary tool calls, average steps, average planner latency, and failure rate. Groundedness and citation coverage remain measured by the answer service and future answer-evaluation datasets.

Known limitations:

- Execution traces are returned in API/CLI responses but are not persisted yet.
- `LLMAgentPlanner` is an adapter boundary that currently falls back to deterministic planning.
- All initial tools are read-only; write tools require a future explicit approval and authorization model.

To add a new tool, implement `ToolInterface`, validate a strict input schema, keep the operation read-only unless a future approval workflow exists, and register the class in `config/agents.php`.

## Bible Platform Integration API

Sprint 16 adds stable versioned endpoints for the core Bible Study Platform and future consumers. These endpoints sit on top of the existing services and intentionally hide embeddings, pgvector, retrieval internals, graph records, LLM providers, and agent state.

The actual monorepo architecture is three separate applications:

```text
frontend/ Nuxt app
  -> api/ Laravel core API
  -> knowledge_documents/ Laravel knowledge service
```

The core API owns users, Sanctum authentication, Bible reader workflows, preferences, progress, bookmarks, and application presentation. This service owns knowledge documents, imports, embeddings, retrieval, graph traversal, AI answers, and agent orchestration. The integration mechanism is HTTP; the services do not share application code or persistence models.

Knowledge service endpoints:

```http
GET  /api/v1/knowledge/search
GET  /api/v1/knowledge/reference/{reference}
GET  /api/v1/knowledge/related/{document}
POST /api/v1/knowledge/retrieve
POST /api/v1/knowledge/answer
POST /api/v1/knowledge/agents/run
```

Search example:

```bash
curl "http://localhost:8080/api/v1/knowledge/search?query=Word%20became%20flesh&source_type=bible_verse&book=John&chapter=1&limit=5"
```

Reference lookup:

```bash
curl "http://localhost:8080/api/v1/knowledge/reference/John%201%3A14"
curl "http://localhost:8080/api/v1/knowledge/reference/CCC%20456"
```

Reference resolution is deterministic when more than one source has the same plain reference. If no explicit source is supplied, Bible references prefer the configured canonical Catholic Bible source, `KNOWLEDGE_CANONICAL_BIBLE_SOURCE_NAME`, which defaults to `Douay-Rheims Bible`. The companion `KNOWLEDGE_CANONICAL_BIBLE_TRANSLATION` defaults to `douay_rheims` and is used as a secondary Bible-source preference. Existing legacy Bible records are preserved as historical source records and are still available by explicit source or translation selection.

Explicit source selection:

```bash
curl "http://localhost:8080/api/v1/knowledge/reference/John%201%3A1?source_name=Douay-Rheims%20Bible"
curl "http://localhost:8080/api/v1/knowledge/reference/John%201%3A1?translation=douay_rheims"
```

Related knowledge:

```bash
curl "http://localhost:8080/api/v1/knowledge/related/John%201%3A14?depth=1&limit=25"
```

Advanced retrieval:

```bash
curl -X POST "http://localhost:8080/api/v1/knowledge/retrieve" \
  -H "Content-Type: application/json" \
  -d '{
    "query": "Why did Jesus become man?",
    "profile": "research",
    "filters": {
      "source_types": ["bible_verse", "catechism", "church_father"],
      "tradition": "catholic"
    },
    "top_k": 10
  }'
```

AI answer:

```bash
curl -X POST "http://localhost:8080/api/v1/knowledge/answer" \
  -H "Content-Type: application/json" \
  -d '{
    "question": "Why did Jesus become man?",
    "profile": "ai_answer",
    "filters": {
      "source_types": ["catechism", "bible_verse"],
      "tradition": "catholic"
    }
  }'
```

Agent run:

```bash
curl -X POST "http://localhost:8080/api/v1/knowledge/agents/run" \
  -H "Content-Type: application/json" \
  -d '{
    "input": "Explain why Jesus became man according to Scripture, Catechism, and the Fathers.",
    "profile": "catholic_research",
    "max_steps": 8
  }'
```

Responses are stable DTO arrays. Search and reference responses return document summaries with `id`, `reference`, `title`, `source_type`, `source_name`, `tradition`, `content`, `metadata`, and optional `score`. Related responses expose relationship summaries, not raw graph query models. Agent responses include a trimmed trace suitable for external clients.

Error contract:

```json
{
  "message": "Reference not found.",
  "errors": {
    "reference": ["No knowledge document matched the supplied reference."]
  }
}
```

Request correlation is accepted through `X-Request-ID`. The core API forwards this header when it calls the knowledge service so logs and traces can be tied together without storing unrestricted conversation history.

## AI Observability And Persistent Agent Traces

Sprint 17 persists lightweight agent execution traces for debugging, auditability, and evaluation. The agent framework remains the same controlled read-only tool orchestration layer; persistence is added through `AgentTraceRepositoryInterface` so the application layer does not depend on Eloquent records.

```text
Core API X-Request-ID
  -> Knowledge Service AgentRequest
  -> KnowledgeAgent
  -> AgentTraceRepositoryInterface
  -> agent_executions
  -> agent_execution_steps
```

Tables:

- `agent_executions`: request id, profile, status, failure category, timing, step/tool counts, provider/model, nullable token metrics, retrieval metrics, answer metrics, redacted metadata, and errors.
- `agent_execution_steps`: ordered tool steps with tool name, status, failure category, timing, validation warnings, compact input metadata, compact output metadata, and redacted errors.
- `agent_evaluation_runs` and `agent_evaluation_results`: persisted deterministic agent evaluation summaries and per-scenario results.

Configuration:

```env
AGENT_TRACE_ENABLED=true
AGENT_TRACE_STORE_INPUTS=false
AGENT_TRACE_STORE_OUTPUTS=false
AGENT_TRACE_RETENTION_DAYS=30
AGENT_TRACE_PRUNE_LIMIT=500
AGENT_TRACE_TRACK_TOKENS=true
AGENT_TRACE_API_TOKEN=
AGENT_EVALUATION_DATASET_VERSION=agent-v1
AGENT_EVAL_SUCCESS_RATE_DROP=0.05
AGENT_EVAL_LATENCY_INCREASE_RATIO=0.25
```

Privacy defaults: raw inputs and outputs are not stored unless explicitly enabled; sensitive keys and bearer/API-key patterns are redacted; embeddings and vectors are never persisted in traces.

Trace CLI:

```bash
php artisan agent:health
php artisan agent:health --days=7
php artisan agent:trace --id=EXECUTION_ID
php artisan agent:traces:prune --days=30 --limit=500
php artisan agent:evaluate --save --name=agent-baseline
```

Trace API:

```http
GET /api/v1/knowledge/agents/executions/{id}
```

If `AGENT_TRACE_API_TOKEN` is configured, direct calls to the knowledge service must send `Authorization: Bearer TOKEN`. The core API exposes the same trace through its authenticated integration endpoint: `GET /v1/knowledge/agents/executions/{id}`.

## Deterministic Agent Replay

Sprint 18 adds replay records and reproducibility comparison for persisted agent traces. Replay does not guarantee identical LLM text; it compares the factors that explain why a new run matches or diverges.

Replay persistence:

- `agent_replays` stores original execution id, optional replay execution id, mode, status, strict/dry-run flags, execution fingerprints, corpus snapshot, configuration snapshot, comparison details, divergence summary, and errors.
- Original executions now include replay metadata with an execution fingerprint, corpus fingerprint, replay readiness, and `exact_model_replay_guaranteed=false`.

Fingerprinting:

- Document fingerprint: normalized content hash plus stable source, reference, provenance, and embedding metadata.
- Corpus fingerprint: deterministic hash of document fingerprints, embedding model set, and retrieval profiles.
- Execution fingerprint: agent profile, planner, tool registry, retrieval config, AI provider/model/prompt/generation settings, corpus hash, and app version. Secrets and API keys are excluded.

Replay commands:

```bash
php artisan agent:replay --id=EXECUTION_ID
php artisan agent:replay --id=EXECUTION_ID --strict
php artisan agent:replay --id=EXECUTION_ID --dry-run --compare
php artisan agent:replay --id=EXECUTION_ID --provider=null --model=null-answer-model
```

Strict replay fails when the stored execution/corpus fingerprint does not match the current system. Live replay executes tools again only when the original trace retained inputs. If `AGENT_TRACE_STORE_INPUTS=false` on the original execution, replay will not reconstruct hidden input.

Protected replay API:

```http
POST /api/v1/knowledge/agents/executions/{id}/replay
GET  /api/v1/knowledge/agent-replays/{id}
```

HTTP replay accepts only `strict` and `dry_run`. Provider/model overrides are CLI-only. Set `AGENT_REPLAY_HTTP_LIVE=false` to require HTTP callers to use dry-run replay.

Comparison output covers environment fingerprints, tool sequence, retrieval/citation references, answer structure, latency, and possible causes such as corpus changes, retrieval changes, tool changes, provider/model drift, or nondeterministic model output.

## MCP Tool Protocol

Sprint 19 exposes selected read-only Knowledge Service tools through the Model Context Protocol (MCP). MCP is an interoperability protocol that lets external AI clients discover tool schemas and invoke tools through standard JSON-RPC methods such as `initialize`, `tools/list`, and `tools/call`.

MCP is an adapter, not a replacement for the internal agent:

```text
Internal Agent
  -> AgentToolRegistry
  -> Existing Tools

External MCP Client
  -> Laravel MCP HTTP transport
  -> MCP Tool Adapter
  -> AgentToolRegistry
  -> Same Existing Tools
```

Transport and protocol:

- Implementation: `laravel/mcp`
- Protocol: Model Context Protocol `2025-06-18`
- Transport: HTTP JSON-RPC at `POST /mcp/knowledge`
- Authentication: bearer token from `MCP_TOKEN`
- Rate limit: `MCP_RATE_LIMIT_PER_MINUTE`
- Default: disabled until `MCP_ENABLED=true`

Configuration:

```env
MCP_ENABLED=false
MCP_TRANSPORT=http
MCP_PROTOCOL_VERSION=2025-06-18
MCP_AUTHENTICATION=bearer_token
MCP_TOKEN=
MCP_RATE_LIMIT_PER_MINUTE=30
MCP_ROUTE=mcp/knowledge
MCP_TOOL_ALLOWLIST=bible_search,scripture_reference,catechism_search,church_father_search,knowledge_graph,advanced_retrieval
```

Available tools:

- `bible_search` - `READ_KNOWLEDGE`
- `scripture_reference` - `READ_KNOWLEDGE`
- `catechism_search` - `READ_KNOWLEDGE`
- `church_father_search` - `READ_KNOWLEDGE`
- `knowledge_graph` - `READ_GRAPH`
- `advanced_retrieval` - `READ_RETRIEVAL`

All exposed tools are marked read-only using MCP annotations. Write tools, imports, replay execution, evaluation mutation, shell commands, filesystem access, arbitrary code execution, and database mutation are not exposed.

Discovery example:

```bash
curl -X POST "http://localhost:8080/mcp/knowledge" \
  -H "Authorization: Bearer $MCP_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/list","params":{}}'
```

Tool invocation example:

```bash
curl -X POST "http://localhost:8080/mcp/knowledge" \
  -H "Authorization: Bearer $MCP_TOKEN" \
  -H "X-Request-ID: mcp-client-1" \
  -H "Content-Type: application/json" \
  -d '{
    "jsonrpc": "2.0",
    "id": "call-1",
    "method": "tools/call",
    "params": {
      "name": "bible_search",
      "arguments": {
        "query": "Word became flesh",
        "limit": 5
      }
    }
  }'
```

Diagnostics:

```bash
php artisan mcp:health
php artisan mcp:tools
```

Observability: MCP tool calls create persistent agent execution/step traces with `profile=mcp` when tracing is enabled and trace tables exist. The trace respects `AGENT_TRACE_STORE_INPUTS`, `AGENT_TRACE_STORE_OUTPUTS`, and redaction rules.

## AI Security, Privacy, And Production Guardrails

Sprint 20 adds deterministic application-level controls around AI inputs, tool execution, MCP calls, traces, provider calls, and expensive endpoints. These controls do not rely on prompts or model behavior.

Implemented architecture:

```text
Request
  -> Authentication / route validation
  -> AI Security Policy
  -> PII Policy + Prompt Injection Policy + Resource Limits
  -> Tool Policy + Approval Decision
  -> Agent / MCP / Retrieval / Answer Service
  -> Safe Observability
```

Core components:

- `AISecurityPolicyInterface`: shared boundary for answer, retrieval, agent, and MCP decisions.
- `PiiDetector`: deterministic detection and redaction for emails, phone numbers, IP addresses, personal URLs, common addresses, contextual names, bearer tokens, and API-key-like values.
- `PromptInjectionDetector`: deterministic layered checks for requests to reveal hidden instructions, exfiltrate secrets, bypass policy, impersonate system/developer roles, or execute commands.
- `ToolPolicyCatalog`: explicit tool permission, read/write status, data access, risk level, authentication, and approval metadata.
- `ProviderPolicy`: blocks external LLM providers unless `AI_ALLOW_EXTERNAL_PROCESSING=true` or the provider is listed as local.
- `ApprovalDecision`: future human-approval boundary. Current read-only tools return `approval_required=false`.
- `TracePersonalDataService`: GDPR data locator/deletion foundation for future user identity mapping.

Security error codes:

- `AI_SECURITY_BLOCKED`
- `PII_POLICY_BLOCKED`
- `TOOL_NOT_AUTHORIZED`
- `APPROVAL_REQUIRED`
- `RESOURCE_LIMIT_EXCEEDED`
- `EXTERNAL_PROCESSING_DISABLED`
- `PROMPT_INJECTION_DETECTED`

Configuration:

```env
AI_SECURITY_ENABLED=true
AI_PII_ACTION=redact
AI_PROMPT_INJECTION_ACTION=block
AI_ALLOW_EXTERNAL_PROCESSING=false
AI_DATA_POLICY=local_or_redacted
AI_LOCAL_PROVIDERS=null,ollama,local
AI_SECURITY_MAX_INPUT_CHARACTERS=1000
AI_SECURITY_MAX_RETRIEVAL_TOP_K=50
AI_SECURITY_MAX_AGENT_STEPS=8
AI_SECURITY_MAX_AGENT_TOOL_CALLS=8
AI_SECURITY_MAX_MCP_PAYLOAD_BYTES=32768
AI_RATE_LIMIT_ANSWER_PER_MINUTE=20
AI_RATE_LIMIT_AGENT_PER_MINUTE=10
AI_RATE_LIMIT_RETRIEVAL_PER_MINUTE=60
AI_RATE_LIMIT_REPLAY_PER_MINUTE=10
```

Diagnostics:

```bash
php artisan ai:security-health
```

Data classification:

- `public`: public reference corpus such as Bible, Catechism, and public-domain patristic material.
- `internal`: service diagnostics, retrieval metadata, and non-user operational context.
- `personal`: detected personal data in user prompts, histories, tool arguments, or URLs.
- `sensitive`: secrets, tokens, credentials, or private operational material.
- `restricted`: future high-risk workflow data requiring explicit authorization and approval.

PII actions:

- `allow`: pass through, still detectable in diagnostics.
- `redact`: replace detected values with `[REDACTED]` and add a `PII_REDACTED` warning.
- `block`: reject with `PII_POLICY_BLOCKED`.

MCP security: MCP keeps bearer-token authentication and rate limiting, then calls the same `AISecurityPolicyInterface` used by internal agents. MCP request bodies are size-limited, write tools are not exposed, and policy failures return safe MCP tool errors.

Trace privacy: agent and MCP traces respect `AGENT_TRACE_STORE_INPUTS` and `AGENT_TRACE_STORE_OUTPUTS`. Even when input storage is enabled, the trace sanitizer redacts configured secret patterns and PII. Security events log safe metadata only and never include original sensitive values.

Provider and EU residency notes: `AI_ALLOW_EXTERNAL_PROCESSING=false` prevents external LLM provider calls. Enabling OpenAI, Gemini, Claude, or another remote provider may send redacted prompts, retrieved context, and provider metadata to that provider according to its infrastructure and terms. This application does not claim EU data residency; residency depends on deployment, vendor contracts, subprocessors, and organizational controls.

GDPR note: this service implements technical controls for redaction, minimization, trace retention boundaries, safe events, and future personal-data location/deletion. This is not legal GDPR compliance by itself. Legal compliance also requires organizational processes, lawful basis, notices, DPAs, retention policy, hosting decisions, and operational controls outside this codebase.

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
- `SourceInventory`: reads configured candidate sources before they are imported.
- `ProvenanceGate`: blocks imports whose source provenance is missing, unverified, restricted, or ambiguous.
- `ImportPipeline`: orchestrates fetch, normalize, validate, persist, embedding dispatch, structured logging, and report generation.
- `KnowledgeDocumentPersistenceService`: the only import framework class that writes `knowledge_documents`.
- DTOs: `RawKnowledgeDocument`, `NormalizedKnowledgeDocument`, `SourceInventoryItem`, `SourceProvenance`, `ProvenanceGateResult`, `ValidationResult`, and `ImportPipelineResult`.

### Source Provenance And Licensing Gate

Imports now pass through a read-only provenance gate before any file is fetched, normalized, persisted, or embedded. This is an engineering safeguard, not legal advice. Online availability does not imply redistribution rights.

Candidate sources are configured in `config/knowledge_sources.php`. Default real-world candidates are intentionally marked `requires_verification` and `import_allowed=false` until a human verifies the source, edition, license, and redistribution terms.

The gate uses these explicit copyright statuses:

- `verified`
- `public_domain`
- `licensed`
- `permission_required`
- `requires_verification`
- `restricted`
- `unknown`

Only `verified`, `public_domain`, and `licensed` can pass the gate, and only when the source inventory entry is also `verification_status=approved` and `import_allowed=true`. Missing license information is never inferred. If provenance is unavailable, use `copyright_status=requires_verification`, `license=null`, and rights notes that explain manual verification is required.

Source inventory entries support:

- `id`
- `type`
- `name`
- `author`
- `work`
- `title`
- `language`
- `edition`
- `source_version`
- `source_url`
- `license_url`
- `license`
- `copyright_status`
- `verification_status`
- `rights_notes`
- `expected_document_count`
- `expected_references`
- `import_allowed`

Example future source inventory shape:

```php
[
    'id' => 'bible.douay_rheims',
    'type' => 'bible',
    'name' => 'Douay-Rheims Bible',
    'language' => 'en',
    'edition' => 'Verified edition label',
    'source_url' => 'https://example.test/source',
    'license_url' => 'https://example.test/license',
    'license' => 'Verified license label',
    'copyright_status' => 'public_domain',
    'verification_status' => 'approved',
    'rights_notes' => 'Human-reviewed provenance notes.',
    'import_allowed' => true,
]
```

Provenance is stored in each document's `metadata` without changing the `knowledge_documents` schema:

- `source_identifier`
- `source_version`
- `source_type`
- `source_name`
- `source_path`
- `source_url`
- `source_checksum`
- `content_checksum`
- `imported_at`
- `author`
- `title`
- `work`
- `reference`
- `language`
- `edition`
- `publication`
- `license`
- `license_url`
- `rights_notes`
- `copyright_status`

The existing document uniqueness remains unchanged:

```text
source_type + source_name + reference
```

Multiple Bible translations and editions should be represented through distinct source inventory IDs, source names, translations, editions, and provenance metadata. The current schema supports this without changing API contracts, but source/reference resolution can become ambiguous if multiple editions use the same `reference`; verify this before importing more than one translation.

Current importers read files into memory. This is acceptable for small verified batches and fixtures, but full Bible, CCC, and large Church Father corpora should be imported through pre-split files or a future streaming import sprint.

Production import readiness checks are deliberately separate from import execution. They do not persist documents, queue embeddings, or approve licensing.

CLI usage:

```bash
php artisan knowledge:sources
php artisan knowledge:bible-audit
php artisan knowledge:bible-audit --format=json
php artisan knowledge:duplicates --source-type=bible_verse
php artisan knowledge:import all --skip-unchanged
php artisan knowledge:import bible --source-id=bible.douay_rheims --skip-unchanged
php artisan knowledge:import catechism --source-id=catechism.ccc --force
php artisan knowledge:import church_fathers --source-id=church_fathers.public_domain_candidate --no-embeddings
php artisan knowledge:verify
php artisan knowledge:verify --format=json
php artisan knowledge:status
```

`php artisan knowledge:import` refuses unsafe imports by default. The `--force` option only bypasses unchanged-file skipping; it does not bypass provenance checks. A deliberately named `--allow-unverified-source` option exists for development-only workflows and only works when `KNOWLEDGE_PROVENANCE_ALLOW_UNVERIFIED_OVERRIDE=true`.

The legacy `php artisan knowledge` alias still imports all configured directories. Import directories are configured with:

```env
KNOWLEDGE_IMPORT_DIRECTORIES=storage/app/imports
KNOWLEDGE_PROVENANCE_GATE_ENABLED=true
KNOWLEDGE_PROVENANCE_ALLOW_UNVERIFIED_OVERRIDE=false
```

Docker manual verification:

```bash
docker compose exec app php artisan knowledge:sources
docker compose exec app php artisan knowledge:bible-audit
docker compose exec app php artisan knowledge:duplicates --source-type=bible_verse
docker compose exec app php artisan knowledge:import all --skip-unchanged
docker compose exec app php artisan knowledge:verify
docker compose exec app php artisan knowledge:status
docker compose exec app php artisan embeddings:generate
```

### Bible Importer

The Bible source importer implements `KnowledgeImporterInterface` and participates in the same DTO pipeline as every other knowledge source. It never writes directly to the database.

Supported Bible JSON formats:

Single chapter:

```json
{
  "translation": "douay-rheims",
  "language": "en",
  "source_edition": "Public domain edition",
  "book": "John",
  "book_abbreviation": "Jn",
  "testament": "New Testament",
  "chapter": 1,
  "verses": [
    {
      "verse": 14,
      "text": "And the Word was made flesh.",
      "cross_references": ["John 1:1", "Philippians 2:6-11"]
    }
  ]
}
```

Complete or multi-book file:

```json
{
  "translation": "public-domain-test",
  "language": "en",
  "books": [
    {
      "book": "Genesis",
      "abbreviation": "Gen",
      "chapters": [
        {
          "chapter": 1,
          "verses": [
            { "verse": 1, "text": "In the beginning..." }
          ]
        }
      ]
    }
  ]
}
```

For every chapter, the importer creates:

- `bible_verse` documents for each verse.
- `bible_chapter` documents containing ordered full chapter text.

Verse metadata includes:

- `book`
- `book_abbreviation`
- `chapter`
- `verse`
- `testament`
- `translation`
- `language`
- `tradition`
- `canonical_book_order`
- `canonical_order`
- `source_edition`
- `import_version`
- `checksum`
- `cross_references`

Chapter metadata includes the same source and canonical fields plus `verse_count`, ordered `verses`, and aggregated supplied `cross_references`.

### Full Catholic Bible Readiness

Before importing a full Bible corpus, run the audit command against the configured import directories or explicit source files:

```bash
php artisan knowledge:bible-audit
php artisan knowledge:bible-audit --path=storage/app/imports/sources/bible/douay-rheims/john.json --format=json
```

The audit verifies readiness without importing data. It reports:

- number of files, books, chapters, and verses found
- expected Catholic 73-book canon coverage
- missing deuterocanonical books
- unexpected book names
- duplicate references within a source file
- duplicate references across files
- malformed references
- invalid chapter or verse numbers
- empty, suspiciously short, and suspiciously long verse text
- invalid canonical ordering
- missing source identity fields such as translation, language, source URL, license URL, and edition
- provenance gate status

`import_ready` is `true` only when the source passes the provenance gate and appears to contain the complete Catholic canon. A partial source can still be useful for development fixtures, but it should not be treated as the full Catholic Bible corpus.

Large Bible corpora should be prepared as split JSON files by book, chapter, or another manageable source unit. The readiness audit processes each file independently so a future verified corpus can be checked incrementally without requiring a single monolithic file. The current importer pipeline is resumable and idempotent through checksums: unchanged documents are skipped, changed documents are persisted with embedding status reset to `pending`, and `--no-embeddings` keeps import validation separate from embedding generation.

Use duplicate diagnostics before and after any approved corpus import:

```bash
php artisan knowledge:duplicates --source-type=bible_verse
php artisan knowledge:duplicates --source-type=bible_verse --format=json
```

Duplicates with the same `source_type + source_name + reference` are accidental duplicates and should be fixed at the source. The same plain reference across different source names can be legitimate when multiple translations or editions are imported, but downstream reference lookup must remain clear about which source is being returned.

Bible CLI examples:

```bash
php artisan knowledge:import bible
php artisan knowledge:import bible --book=John
php artisan knowledge:import bible --book=John --chapter=1
php artisan knowledge:import bible --translation=douay-rheims
php artisan knowledge:import bible --force
php artisan knowledge:import bible --skip-unchanged
```

The importer preserves only cross references supplied in the source file. It does not infer or generate cross references. Use only Bible sources you have the right to import, such as public-domain, licensed, or user-supplied texts. No copyrighted Bible text is bundled or hard-coded.

To add another Bible translation, place a compatible JSON file in a configured import directory and provide `translation`, `language`, license, and source edition metadata. No pipeline code changes are required.

### Catechism Importer

The Catechism importer also implements `KnowledgeImporterInterface` and uses the shared import pipeline. It supports user-supplied or licensed CCC paragraph JSON and the older Baltimore lesson format.

CCC paragraph format:

```json
{
  "catechism": "Catechism of the Catholic Church",
  "language": "en",
  "source_edition": "Second Edition",
  "publication_year": 1997,
  "paragraphs": [
    {
      "number": 456,
      "title": "Why did the Word become flesh?",
      "part": "Part I",
      "section": "Section One",
      "chapter": "Chapter Two",
      "article": "Article 3",
      "paragraph": "Paragraph 1",
      "category": "christology",
      "topics": ["incarnation", "salvation"],
      "content": "The Word became flesh for us... See CCC 457 and John 1:14.",
      "church_father_references": ["St. Athanasius, De Incarnatione"]
    }
  ]
}
```

Each CCC paragraph becomes one `catechism` document with a stable reference such as `CCC 456`.

CCC metadata includes:

- `document_type`
- `reference_number`
- `paragraph_number`
- `category`
- `topics`
- `hierarchy`
- `part`
- `section`
- `chapter`
- `article`
- `paragraph`
- `language`
- `source_edition`
- `publication_year`
- `tradition`
- `internal_references`
- `scripture_references`
- `church_father_references`
- `checksum`

The importer preserves official references supplied in the source and extracts explicit references already present in paragraph text. It does not infer AI-generated links.

Cross-reference metadata:

- `internal_references`: explicit `CCC 457` style Catechism links.
- `scripture_references`: explicit Scripture references such as `John 1:14` or `Philippians 2:6-11`.
- `church_father_references`: explicit patristic references supplied by the source.

Catechism CLI examples:

```bash
php artisan knowledge:import catechism
php artisan knowledge:import catechism --force
php artisan knowledge:import catechism --skip-unchanged
php artisan knowledge:status
```

To add another Catechism edition or translation, provide the same paragraph structure with edition, language, publication year, licensing, and source metadata. No retrieval, embedding, or pipeline changes are required.

### Church Fathers Importer

The Church Fathers importer implements `KnowledgeImporterInterface` and uses the same source registry, DTO normalization, manifest tracking, persistence service, and embedding dispatch as Bible and Catechism imports. It is source-file driven: users provide appropriately licensed JSON files, and the importer preserves only explicit references supplied by the text or source metadata.

Initial supported authors:

- St. Augustine
- St. Thomas Aquinas
- St. John Chrysostom
- St. Athanasius
- St. Gregory the Great

Church Fathers JSON format:

```json
{
  "author": "St. Augustine",
  "work": "Tractates on John",
  "volume": "NPNF1-07",
  "century": "4th-5th",
  "language": "en",
  "original_language": "Latin",
  "translation": "Public domain translation",
  "source_edition": "Nicene and Post-Nicene Fathers",
  "sections": [
    {
      "title": "Tractate 2",
      "reference": "Augustine, Tractates on John, Tractate 2",
      "section": "Tractate 2",
      "chapter": "John 1",
      "paragraph": "2",
      "topics": ["logos", "incarnation"],
      "content": "The Evangelist says John 1:1 and John 1:14. See also CCC 456.",
      "church_father_references": ["Athanasius, On the Incarnation, Chapter 8"]
    }
  ]
}
```

Each section becomes one `church_father` document. The importer requires stable source-supplied references such as:

- `Augustine, Tractates on John, Tractate 2`
- `Catena Aurea, John 1:14`
- `Athanasius, On the Incarnation, Chapter 8`

Metadata includes:

- `author`
- `author_key`
- `work`
- `volume`
- `section`
- `chapter`
- `paragraph`
- `language`
- `original_language`
- `translation`
- `century`
- `topics`
- `source_edition`
- `tradition`
- `import_version`
- `checksum`
- `scripture_references`
- `catechism_references`
- `church_father_references`
- `cross_references`

CLI examples:

```bash
php artisan knowledge:import church-fathers
php artisan knowledge:import church-fathers --author=augustine
php artisan knowledge:import church_fathers --force
php artisan knowledge:import church-fathers --skip-unchanged
php artisan knowledge:status
```

The dashed `church-fathers` command source is accepted as an alias for the registered `church_fathers` importer identifier.

To add more patristic material, create another compatible JSON file with author, work, section references, content, licensing, and source edition metadata. To support another author family explicitly, extend the importer-supported author list; the pipeline and retrieval layers do not need changes.

Licensing: do not import copyrighted editions unless you have rights to use them. Prefer public-domain editions or user-supplied licensed sources. No patristic source text is bundled or hard-coded.

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
