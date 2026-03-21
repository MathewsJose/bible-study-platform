# Bible Study Platform

Bible Study Platform is a Vue 3 single-page application for reading Bible passages alongside historical context and church teachings. The app is designed to feel fast and uninterrupted: changing books, chapters, or verses updates content in place without page reloads or blocking navigation.

## What The App Does

- Lets readers choose a Bible book and valid chapter from built-in canon metadata
- Displays the selected chapter in the main reading panel
- Loads historical context for the active verse in the left panel
- Loads teachings and interpretation notes for the active verse in the right panel
- Saves verse bookmarks in browser `localStorage`
- Keeps existing content on screen while background refreshes complete

## SPA Behavior

This project is a client-side SPA built with Vue, Vite, Pinia, and Vue Router.

- There is no full-page reload when the user clicks a verse or changes a selection
- Data fetching is coordinated in app state, not inside the browser navigation flow
- Async requests are race-safe, so stale responses do not overwrite newer selections
- Loading indicators are only shown for first-load states; later updates happen in the background with lightweight status text

## Tech Stack

- Vue 3
- Vite
- Pinia
- Vue Router
- Axios

## Project Structure

```text
src/
  components/
    bible/        Reader UI and verse interactions
    common/       Shared empty/error/loading states
    layout/       App shell and top controls
    panels/       Historical and teaching side panels
  composables/    Shared reader orchestration
  services/       API requests
  stores/         Pinia stores for selection and fetched data
  utils/          Canon metadata, async helpers, bookmark persistence
tests/            Lightweight regression tests
```

## Requirements

- Node.js 22+ recommended
- npm 10+ recommended

## Getting Started

1. Install dependencies:

```bash
npm install
```

2. Start the development server:

```bash
npm run dev
```

3. Open the local Vite URL shown in the terminal.

## Environment Configuration

The frontend reads its API base URL from `VITE_API_BASE_URL`.

Example:

```bash
VITE_API_BASE_URL=http://localhost:3000
```

If `VITE_API_BASE_URL` is not set, the app will use a relative base URL and call the current origin.

## API Contract

The app expects these endpoints:

- `GET /bible?book=<book>&chapter=<chapter>`
- `GET /history?book=<book>&chapter=<chapter>&verse=<verse>`
- `GET /teachings?book=<book>&chapter=<chapter>&verse=<verse>`

Expected response shapes:

```json
{
  "verses": [
    { "verse": 1, "text": "In the beginning..." }
  ]
}
```

```json
{
  "items": [
    "Historical note or teaching item"
  ]
}
```

## Available Scripts

Run the development server:

```bash
npm run dev
```

Run tests:

```bash
npm run test
```

Build for production:

```bash
npm run build
```

Run the full validation flow:

```bash
npm run check
```

Preview the production build:

```bash
npm run preview
```

## Testing

The project includes lightweight regression coverage for:

- Bible book metadata and chapter validation
- Bookmark parsing and persistence safety
- Async resource handling and stale-request protection

## Notes For Future Development

- The routing layer is intentionally minimal right now and can be extended for deep-linked passages
- Bookmark persistence is local-only today and can later be replaced with user accounts or server sync
- The app already separates fetching, presentation, and selection state, which should make future feature work easier
