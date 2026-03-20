# 📖 Bible API — Laravel 13 · PHP 8.5 · MongoDB · Elasticsearch · DDD · Clean Architecture

A modern, scalable, extensible **Bible microservice** using updated service stack and architecture principles. This repo illustrates Domain-Driven Design (DDD), Clean Architecture, and fast full-text verse search with MongoDB + Elasticsearch.

---

## ✅ Upgrade Summary

- Laravel: `12.x` → `13.1.1`
- PHP: `8.2`/`8.4` → `8.5`
- MongoDB package: `mongodb/laravel-mongodb` pinned to `^5.4` (currently stable server driver experience)
- Docker base image: `php:8.5-fpm`
- Deprecation fix: `PDO::MYSQL_ATTR_SSL_CA` → `Pdo\\Mysql::ATTR_SSL_CA`

---

## 📦 Requirements

- Docker (recommended)
- Docker Compose
- PHP 8.5 (local CLI if running outside container, plus `ext-mongodb`)
- Composer

---

## 🚀 Quick start (Docker)

1. `git clone https://github.com/your-username/bible-project.git`
2. `cd bible-project`
3. `cp .env.example .env`
4. Configure MongoDB/Elasticsearch values in `.env`
5. `docker-compose up -d`
6. `docker exec -it app composer install`
7. `docker exec -it app php artisan key:generate`
8. `docker exec -it app php artisan migrate --force`
9. `docker exec -it app php artisan db:seed`
10. `docker exec -it app php artisan search:reindex`

---

## 🌐 API Endpoints

- `GET /api/verse/{book}/{chapter}/{verse}` - fetch a verse
- `GET /api/search?query=word` - Elasticsearch-powered search

Example: `curl http://localhost:8000/api/verse/John/3/16`

---

## 🧩 Architecture

- app/Domain: Entities + repository interfaces
- app/Application: Use cases (business orchestration)
- app/Infrastructure: MongoDB + Elasticsearch implementation
- app/Interfaces: HTTP controllers

---

## 🔧 Testing

- `docker exec -it app php artisan test`
- `docker exec -it app phpunit`

---

## 🛠 Developer notes

- Ensure `ext-mongodb` is enabled in CLI (`php --ri mongodb`)
- Disable Xdebug unless debugging (performance)
- Run `composer update` after dependency changes

---

## 📌 Notes

- `laravel/tinker` on Laravel 13 currently requires Illuminate 13, `^3.0` or versions compatible with the new stack.
- Lock file prepared with `composer update --ignore-platform-req=ext-mongodb` if driver is only available in container.

---

## 🤝 Contributing

1. Fork -> branch
2. commit with clear message `chore: ...` or `fix: ...`
3. PR

---

## 📝 License
MIT
