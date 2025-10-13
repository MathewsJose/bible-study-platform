# 📖 Bible API — Laravel 11 · PHP 8.4 · MongoDB · Elasticsearch · DDD · Clean Architecture

A modern, scalable, and extensible **Bible microservice** built with cutting-edge backend architecture principles.  
This project demonstrates **Domain-Driven Design (DDD)** and **Clean Architecture** in PHP/Laravel, leveraging **MongoDB** for persistence and **Elasticsearch** for powerful text search capabilities — all containerized with **Docker**.

---

## 📜 Project Overview

This project is more than just a Bible API. Its core purpose is to provide **historical and theological insights** for every Book, Chapter, and Verse of the Bible.

It aims to serve as a foundation for theological study tools, digital commentaries, or educational apps by combining structured Biblical data with historical context and interpretive layers.

### Key Goals:

- 📚 **Historical Context** — Offer information about the period, culture, and background for each passage.
- ✝️ **Theological Insights** — Highlight doctrinal significance, theological themes, and interpretations.
- 🌍 **Search & Study** — Enable powerful verse-level searches using Elasticsearch, including theological keywords and historical terms.
- 🧠 **Structured + Extensible** — Build using Domain-Driven Design and Clean Architecture so new features (like commentaries, cross-references, maps, timelines) can be added without breaking the core.

Whether you're a theologian, developer, or researcher, this application provides a modern backend infrastructure for Bible study — with data stored in MongoDB and indexed in Elasticsearch for blazing-fast retrieval.

---

## ✨ Features

- ✅ **Laravel 11 + PHP 8.4** — Modern backend framework with latest PHP features  
- 🧠 **DDD + Clean Architecture** — Separation of concerns, testability, and scalability  
- 🧰 **MongoDB** — Primary data store for structured Bible content  
- 🔍 **Elasticsearch** — Full-text search (fast lookup of verses and theological references)  
- 🐳 **Dockerized Environment** — Ready-to-run development setup  
- 🧪 **Seed Data + Indexing** — Populate Bible data and sync to Elasticsearch  
- 🔌 **Extensible Architecture** — Add new use cases, domains, or search strategies with ease

---

## 🏗 Architecture Overview

The project follows a **layered hexagonal architecture**:

```
┌───────────────────────────┐
│   Interface Layer         │ ← Controllers, HTTP routes, CLI
├───────────────────────────┤
│   Application Layer       │ ← Use Cases (Business rules orchestration)
├───────────────────────────┤
│   Domain Layer            │ ← Entities, Value Objects, Repositories (interfaces)
├───────────────────────────┤
│   Infrastructure Layer    │ ← MongoDB, Elasticsearch, external services
└───────────────────────────┘
```

- **MongoDB** → Source of truth for all structured data  
- **Elasticsearch** → Indexed for blazing-fast verse & text search  
- **Use Cases** mediate between interface and domain layers — no controllers touch the database directly.

---

## 🚀 Quick Start

### 1️⃣ Clone the Repository

```bash
git clone https://github.com/your-username/bible-project.git
cd bible-project
```

### 2️⃣ Copy Environment File

```bash
cp .env.example .env
```

Update `.env` with MongoDB and Elasticsearch credentials (Docker defaults are provided).

### 3️⃣ Start Docker Environment

```bash
docker-compose up -d
```

This will start:
- App (Laravel)
- MongoDB
- Elasticsearch

### 4️⃣ Install Dependencies

```bash
docker exec -it app composer install
docker exec -it app php artisan key:generate
```

### 5️⃣ Seed the Database

```bash
docker exec -it app php artisan db:seed
```

(Optional) Sync seed data to Elasticsearch:

```bash
docker exec -it app php artisan search:reindex
```

---

## 📚 Example Endpoints

| Method | Endpoint                              | Description                              |
|--------|---------------------------------------|------------------------------------------|
| GET    | `/api/verse/{book}/{chapter}/{verse}` | Get a specific verse from MongoDB        |
| GET    | `/api/search?query=grace`             | Full-text search via Elasticsearch       |

**Example:**

```bash
curl http://localhost:8000/api/verse/John/3/16
```

---

## 🧠 Domain-Driven Design Structure

```
app/
├── Domain/
│   └── Bible/
│       ├── Entities/
│       ├── Repositories/
│       └── ValueObjects/
├── Application/
│   └── Bible/
│       └── UseCases/
├── Infrastructure/
│   ├── Persistence/Mongo/
│   └── Search/Elasticsearch/
└── Interfaces/
    └── Http/Controllers/
```

- **Entities**: Core domain models (e.g., Verse)
- **Repositories**: Contracts/interfaces for data access
- **UseCases**: Business logic orchestration
- **Infrastructure**: Implementation of Repositories, search indexing, etc.
- **Interfaces**: Controllers, routes, presentation layer

---

## 🧰 Technology Stack

| Layer             | Technology                |
|-------------------|---------------------------|
| Language          | PHP 8.4                   |
| Framework         | Laravel 11                |
| Database          | MongoDB                   |
| Search Engine     | Elasticsearch             |
| Containerization  | Docker + Docker Compose   |
| Architecture      | DDD + Clean Architecture  |

---

## 🧪 Running Tests

```bash
docker exec -it app php artisan test
```

You can also write domain-level unit tests and use case tests without touching the infrastructure.

---

## 🌱 Future Improvements

- ✅ Add authentication & user-specific study notes
- ✅ Implement advanced search strategies (e.g., fuzzy matching, verse context search)
- ✅ Caching layer for high-traffic endpoints
- ✅ Multilingual Bible versions support

---

## 🤝 Contributing

1. Fork the repository
2. Create a new branch (`feature/add-commentaries`)
3. Commit your changes
4. Push to the branch
5. Create a Pull Request 🎉

---

## 📝 License

This project is licensed under the MIT License.