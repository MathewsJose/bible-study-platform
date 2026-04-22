# Bible Study Platform

Bible Study Platform is a Nuxt-powered Bible reading application focused on clean reading, historical context, and church teaching side by side. The interface is built to feel calm and uninterrupted: changing books, chapters, or verses updates the page in place without breaking study flow.

## What The App Does

- Lets readers choose a Bible book and valid chapter from built-in canon metadata
- Displays the selected chapter in a central reading panel with serif scripture typography
- Loads historical context for the active verse in the left panel
- Loads teachings and interpretation notes for the active verse in the right panel
- Highlights the selected verse and updates the side panels dynamically
- Uses compact sticky sidebars with scrollable content for larger study notes
- Includes local sample study data for `John 1`
- Uses `RSV-CE` as the intended Bible version target
- Falls back to local sample content when no API base URL is configured
- Keeps existing content on screen while background refreshes complete

## SPA Behavior

This project is delivered through Nuxt with client-friendly reading behavior.

- There is no full-page reload when the user clicks a verse or changes a selection
- Data fetching is coordinated in app state, not inside the browser navigation flow
- Async requests are race-safe, so stale responses do not overwrite newer selections
- Loading indicators are only shown for first-load states; later updates happen in the background with lightweight status text
- Book and chapter changes automatically refresh the Bible text, historical context, and church teachings
- Verse selection updates contextual sidebars immediately

## Tech Stack

- Nuxt 4
- Vue 3
- Pinia
- Tailwind CSS v4
- Nuxt runtime config
- `ofetch`

## Project Structure

```text
src/
  app.vue         Nuxt app entry
  components/
    bible/        Reader UI and verse interactions
    common/       Shared empty/error/loading states
    layout/       App shell and top controls
    panels/       Historical and teaching side panels
  composables/    Shared reader orchestration
  layouts/        Nuxt layouts
  pages/          Nuxt pages
  services/       API access and local sample content
  stores/         Pinia stores for selection and fetched data
  utils/          Canon metadata, async helpers, bookmark persistence
nuxt.config.ts    Nuxt application configuration
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

3. Open the local Nuxt URL shown in the terminal.

## Environment Configuration

The frontend reads its API base URL from `NUXT_PUBLIC_API_BASE_URL`.

Example:

```bash
NUXT_PUBLIC_API_BASE_URL=http://localhost:3000
```

Backward-compatible fallback:

```bash
VITE_API_BASE_URL=http://localhost:3000
```

If no API base URL is set, the app uses local sample study data.

## Included Sample Data

The app currently ships with built-in sample content for:

- `John 1`
- Historical context entries tied to the chapter and selected verses
- Church teaching summaries tied to the chapter and selected verses

Notes:

- The app is configured around the `RSV-CE` translation target
- Because `RSV-CE` is copyrighted, the local fallback uses concise study-oriented summaries rather than a full embedded chapter text
- Connect a licensed Bible API or backend to serve full translation text in production

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

## Current UI Features

- Minimal top bar with compact `Book` and `Chapter` selectors
- Responsive 3-column study layout
- Continuous Bible reading flow instead of boxed verse cards
- Individually selectable verses with clear active-state highlighting
- Scrollable side panels for long historical and doctrinal content
- Clean white reading interface optimized for readability

## Notes For Future Development

- The routing layer is intentionally minimal and can later support deep-linked books, chapters, and verses
- Bookmark persistence is local-only today and can later be replaced with user accounts or server sync
- The app already separates fetching, presentation, and selection state, which should make future feature work easier
