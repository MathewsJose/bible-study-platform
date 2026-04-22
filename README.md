# Bible Study Platform Monorepo

This repository is now organized as a small monorepo:

```text
frontend/  Nuxt app
api/       Laravel API
```

## Projects

### `frontend/`

Nuxt-based Bible study UI.

Common commands:

```bash
npm --prefix frontend install
npm --prefix frontend run dev
npm --prefix frontend run build
npm --prefix frontend run test
```

### `api/`

Laravel backend for Bible content, history, and teaching endpoints.

Common commands:

```bash
cd api
composer install
php artisan serve
php artisan test
```

## Root Commands

The root package provides a few convenience scripts:

```bash
npm run frontend:dev
npm run frontend:build
npm run frontend:test
```
