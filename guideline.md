# Bible Study Platform Guideline

This document provides comprehensive guidelines for development within the Bible Study Platform. It serves as a source of truth for architecture, coding standards, testing strategies, and general rules for both human developers and coding agents.

## 1. Project Overview & Structure

The platform is a monorepo consisting of a modern frontend and two specialized backend services:

-   **`frontend/`**: Nuxt 4 (Vue 3) SPA. High-performance, SEO-friendly scripture reader.
-   **`api/` (Core Service)**: Laravel 13 backend managing Bible versions (MongoDB) and core relational data (MariaDB).
-   **`knowledge_documents/` (Knowledge Service)**: Laravel 13 backend focused on theological document indexing and RAG support (PostgreSQL + pgvector).

---

## 2. Architecture & Design Patterns

### Clean Architecture Layers
All backend services must strictly follow these layers:

1.  **Domain**: Pure business logic. Contains **Entities**, **Value Objects**, **Enums**, and **Repository Interfaces**.
    -   *Rule*: No dependencies on external libraries or frameworks (except for PHP language features).
2.  **Application**: Orchestrates business logic. Contains **Use Cases**, **Services**, and **DTOs**.
    -   *Rule*: Handles the flow of data between Domain and Infrastructure.
3.  **Infrastructure**: Implementation details. Contains **Persistence Adapters** (Eloquent/Mongo models), **External Integrations** (OpenAI), and **Service Providers**.
    -   *Rule*: Implements interfaces defined in the Domain layer.
4.  **Interfaces / Presentation**: Delivery mechanisms. Contains **HTTP Controllers**, **API Requests/Responses**, and **Console Commands**.

### Domain-Driven Design (DDD)
-   **Entities**: Must be identifiable and encapsulate state transitions.
-   **Value Objects**: Immutable objects representing data without identity (e.g., `DocumentReference`).
-   **Aggregates**: Ensure consistency boundaries within the Domain.

---

## 3. Coding Standards

### PHP Standards
-   **PSR-12**: All PHP code must adhere to PSR-12 coding standards (enforced via Laravel Pint).
-   **Strict Typing**: Every PHP file MUST start with `declare(strict_types=1);`.
-   **Type Hinting**: All function arguments and return types MUST be explicitly typed. Avoid `mixed` unless strictly necessary.
-   **Immutability**: Use `readonly` properties for DTOs and Entities. In the Knowledge Service, Domain entities should be `final readonly`.
-   **Visibility**: Always define property and method visibility (`public`, `protected`, `private`).
-   **Naming**: 
    -   Classes: PascalCase.
    -   Methods/Properties: camelCase.
    -   Variables: camelCase.

### Frontend Standards
-   **Vue 3 Composition API**: Use `<script setup>` syntax.
-   **Nuxt 4 Patterns**: Leverage auto-imports for components, composables, and stores.
-   **Tailwind CSS 4**: Use utility-first styling. Avoid custom CSS unless necessary.
-   **State Management**: Use Pinia stores for global state.

---

## 4. Testing Strategy

### Backend Testing
-   **Frameworks**:
    -   `knowledge_documents`: **Pest PHP**.
    -   `api`: **PHPUnit**.
-   **Test Types**:
    -   **Feature Tests**: Required for every API endpoint. Should verify happy paths and edge cases (404, 422, etc.).
    -   **Unit Tests**: Required for complex logic in Domain or Application layers.
-   **Database**: Tests must run against a separate database (SQLite in-memory is the default for both services).
-   **Mocks**: Use Mockery or Pest's built-in mocking for external services (e.g., OpenAI API).

### Frontend Testing
-   **Runner**: Custom runner `node tests/run-tests.js`.
-   **Expectations**: All core logic and utility functions must have unit tests.

### General Testing Rules
-   **No Fake Passing**: Never use `assert(true)` to bypass testing requirements.
-   **Coverage**: Target high coverage for Domain and Application layers.
-   **Regression**: Every bug fix MUST include a reproduction test.

---

## 5. General Rules for Coding Agents

### Rule 1: No Leakage of Persistence Models
Never return Eloquent or MongoDB models from a Service or Use Case. Always map them to **DTOs** or **Domain Entities** before passing them to the Presentation layer.

### Rule 2: Strict Boundary Enforcement
The Domain layer must never know about the Infrastructure layer. If the Domain needs a database or an external service, it must define an **Interface** that the Infrastructure layer implements.

### Rule 3: Defensive Programming
-   Validate all incoming API data using Laravel `FormRequest` or `Validator`.
-   Handle potential exceptions (e.g., `ModelNotFoundException`) and return appropriate API responses.
-   Use Enums for fixed sets of values (e.g., `Tradition`, `SourceType`).

### Rule 4: Documentation
-   New public methods should have brief DocBlocks if the purpose is not immediately obvious from the name/types.
-   Update `README.md` if changing installation or environment requirements.

### Rule 5: Static Analysis
-   Knowledge Service: Code must pass **PHPStan Level 8**.
-   Core API: Ensure no obvious type errors or unreachable code.

---

## 6. Database & Persistence

-   **Core API (`api/`)**:
    -   **MongoDB**: Primary store for Bible content (Flexible schema).
    -   **MariaDB**: Relation data (Users, Auth).
-   **Knowledge Service (`knowledge_documents/`)**:
    -   **PostgreSQL 17 + pgvector**: Specialized for vector embeddings and full-text search.
    -   **Search Strategy**: Always implement both `fullTextSearch` (keyword) and `semanticSearch` (AI-based) for document retrieval.

---

## 7. Extension Guidelines

-   **Adding New Features**:
    1. Define Domain Entities/Interfaces.
    2. Implement Application Use Cases.
    3. Implement Infrastructure Persistence/Adapters.
    4. Expose via Interface (Controller).
    5. Write Feature Tests.
-   **Adding Knowledge Sources**: Update `SourceType` Enum and create an Importer in `Infrastructure\Knowledge\Importers`.
