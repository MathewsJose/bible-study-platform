# Bible API

Laravel backend for a Bible study application. The service currently exposes Bible chapter lookup, historical context lookup, and Church teaching lookup endpoints backed by MongoDB-oriented repositories.

The codebase uses a layered structure:

- `app/Domain`: entities and repository interfaces
- `app/Application`: DTOs, use cases, and services
- `app/Infrastructure`: MongoDB models and repository implementations
- `app/Interfaces`: HTTP controllers and API response formatting

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

The bundled seed data includes a small public-domain Douay-Rheims sample under `version=drb`. It is intended for endpoint verification only. NRSV-CE text is not bundled in this repository and should be imported only from a licensed source.

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
- `version`: defaults to `nrsvce`

Example:

```bash
curl "http://localhost/bible?book=john&chapter=3&version=drb"
```

Example response:

```json
{
  "success": true,
  "data": {
    "book": "john",
    "chapter": 3,
    "version": "drb",
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
curl "http://localhost/history?book=john&chapter=3&verse=16&version=drb"
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
curl "http://localhost/teachings?book=john&chapter=3&verse=16&version=drb"
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

### Legacy Verse Lookup

```http
GET /api/verse/{book}/{chapter}/{verse}
```

This route is kept for existing clients. New client work should prefer the query-based endpoints above.

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

The current default version is `nrsvce` and the current default language is `en`. The repository/model boundaries are intended to support additional Bible versions, languages, and content sources without rewriting the controllers.

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
php artisan route:list --path=teachings
```

## License

MIT
