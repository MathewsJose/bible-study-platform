# Bible Study Platform User Guide

This guide explains how to use the Bible Study Platform locally during private alpha testing. It is written for users and testers, not for contributors changing the code.

## What This App Does

The platform helps you read Scripture and ask Catholic Bible study questions grounded in available sources.

Main capabilities:

- Read Bible chapters in the frontend app.
- View study context where available.
- Ask Catholic Bible study questions in the Private Alpha Ask page.
- Review citations from Bible, Catechism, and Church Father sources.
- Submit simple helpful / not helpful feedback.

The AI answer feature is a study aid. Always verify important theological conclusions against the cited sources and official Church teaching.

## Local URLs

When the Docker services are running locally:

```text
Frontend app:       http://localhost:3000
Core API:           http://localhost
Knowledge Service:  http://localhost:8080
pgAdmin:            http://localhost:5050
Private Alpha Ask:  http://localhost:3000/ask
```

If your frontend runs on a different dev-server port, use the port printed by `npm run dev`.

## Starting The App

Start the Knowledge Service:

```bash
cd knowledge_documents
docker compose up -d
docker compose exec -T app php artisan migrate --force
docker compose exec -T app php artisan knowledge:status
docker compose exec -T app php artisan embeddings:health
```

Start the Core API:

```bash
cd ../api
docker compose up -d
docker compose exec -T app php artisan migrate --force
```

Start the frontend:

```bash
cd ../frontend
npm run dev
```

Open:

```text
http://localhost:3000
```

## Reading Scripture

Use the reading page to select:

- Book
- Chapter
- Verse
- Bible version, where available

The reader is intended for normal study flow: select a passage, read it, and then use the study panels or Private Alpha Ask page for deeper exploration.

## Private Alpha Ask Page

Open:

```text
http://localhost:3000/ask
```

You need a Core API Sanctum token for the alpha question flow. Paste it into the Alpha Token field.

Then enter a question such as:

```text
Why did Jesus become man?
```

The app sends the question to the Core API, which forwards it to the Knowledge Service. The answer should include supporting sources when retrieval finds useful material.

## What To Check In An Answer

For each alpha answer, check:

- Is the answer understandable?
- Is the answer grounded in the returned sources?
- Are the citations relevant to the question?
- Are important Catholic sources missing?
- Did the answer avoid making claims unsupported by citations?
- Did it clearly say when evidence was insufficient?

Good answers should cite sources such as:

- Bible verses, for example `John 1:14`
- Catechism paragraphs, for example `CCC 456`
- Church Father or Catena Aurea references, where relevant

## Opening Citations

On the Ask page, click a returned source to open the supporting document in the side panel.

You can also test source lookup in Postman or a browser:

```text
http://localhost/v1/knowledge/reference/CCC%20456
http://localhost/v1/knowledge/reference/John%201%3A14
```

## Sending Feedback

After reviewing an answer, choose:

- `Yes` if the answer was useful and sufficiently cited.
- `No` if the answer was wrong, weakly cited, missing important sources, or not useful.

For not helpful feedback, choose a reason:

- Incorrect answer
- Incorrect citation
- Missing information
- Other

Optional comments may be collected by the UI, but the Core API is configured to avoid storing comments by default during alpha testing. This reduces the chance of collecting personal information.

## Useful Manual Test Questions

Use these questions in the Ask page:

1. Why did Jesus become man?
2. What does John 1:14 mean?
3. What does John 1:1 teach about Jesus?
4. What does the Catholic Church teach about grace?
5. What is the Trinity?
6. What is the Eucharist?
7. What does Baptism do?
8. How are we saved?
9. What does Catholic teaching say about faith?
10. Why is Mary important in Catholic teaching?
11. What is the Church?
12. What does Jesus teach about prayer?
13. What is sin?
14. What does repentance mean?
15. What do the Church Fathers say about the Word?
16. How does Scripture describe eternal life?
17. What is the role of the Holy Spirit?
18. How should Christians understand suffering?
19. What does John 3:16 teach?
20. How does the Catechism explain the Incarnation?

For each question, inspect both the answer and the citations.

## Postman Testing

A Postman collection is available at:

```text
knowledge_documents/postman/private-alpha-evaluation.postman_collection.json
```

Import it into Postman and set these collection variables:

```text
core_api_url = http://localhost
knowledge_service_url = http://localhost:8080
auth_token = your Core API Sanctum token
request_id = manual-alpha-001
reference = CCC 456
```

The collection includes:

- AI answer requests
- Citation lookup requests
- Helpful and not helpful feedback requests
- Direct Knowledge Service semantic, lexical, and hybrid search checks

## Terminal Testing

Ask a question directly through the Knowledge Service:

```bash
cd knowledge_documents
docker compose exec -T app php artisan ai:answer "Why did Jesus become man?"
```

Run retrieval-only diagnostics:

```bash
docker compose exec -T app php artisan retrieval:pipeline "Why did Jesus become man?" --top-k=10
```

Check feedback totals from the Core API:

```bash
cd ../api
docker compose exec -T app php artisan ai:feedback:health --days=30 --format=json
```

## Health Checks Before Alpha Testing

Run these before inviting testers:

```bash
cd knowledge_documents
docker compose exec -T app php artisan knowledge:status
docker compose exec -T app php artisan embeddings:health
docker compose exec -T app php artisan retrieval:health --top-k=5
docker compose exec -T app php artisan ai:llm-health --format=json
docker compose exec -T app php artisan ai:security-health
docker compose exec -T app php artisan agent:health --days=7
```

Then check Core API feedback:

```bash
cd ../api
docker compose exec -T app php artisan ai:feedback:health --days=30 --format=json
```

## Troubleshooting

If the Ask page says the server is unavailable:

- Make sure the Core API Docker stack is running.
- Make sure the Knowledge Service Docker stack is running.
- Confirm the Core API can reach the Knowledge Service.
- Check that `KNOWLEDGE_SERVICE_URL` points to `http://host.docker.internal:8080` when the Core API runs in Docker.

If semantic search returns empty results:

- Run `embeddings:health`.
- Confirm documents have embeddings.
- Lower the minimum score threshold.
- Check whether filters such as `source_types` or `tradition` are too restrictive.

If feedback health says the table is missing:

```bash
cd api
docker compose exec -T app php artisan migrate --force
```

If frontend changes do not appear:

```bash
cd frontend
npm run dev
```

Then refresh the browser.

## What To Report After Testing

For each issue, record:

- Question asked
- Request ID shown in the Ask page
- Whether the answer was helpful
- What citation looked wrong or missing
- Expected source, if known
- Screenshot or copied response, if useful

Avoid collecting private personal information in feedback comments.

For deciding the next sprint, bring back:

- Top not helpful reasons
- Example request IDs for weak answers
- Common missing source patterns
- Any repeated citation problems
- Any safety or provider errors
- User comments summarized without personal data
