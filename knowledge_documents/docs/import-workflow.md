### Catholic Source Import Workflow

This document describes how to import Catholic knowledge documents into the service, including the Douay-Rheims Bible, Catechism, and Church Father writings.

#### 1. File Placement

The import pipeline scans directories configured in `config/knowledge.php` (default: `storage/app/imports`).

To import specific sources, place your files in the following subdirectories:

- **Douay-Rheims Bible**: `storage/app/imports/bible/douay-rheims/`
- **Other Bible Versions**: `storage/app/imports/bible/`
- **Catechism**: `storage/app/imports/catechism/`
- **Church Fathers**: `storage/app/imports/church-fathers/`

#### 2. Running the Import

Use the `knowledge:import` command to scan and process all files in the configured directories:

```bash
php artisan knowledge:import
```

For Bible-specific single file imports (using the generic Bible importer):

```bash
php artisan bible:import path/to/chapter.json
```

#### 3. Source JSON Format (Bible)

Bible source files should be JSON objects representing a single chapter:

```json
{
  "book": "John",
  "book_abbreviation": "Jn",
  "chapter": 3,
  "testament": "New Testament",
  "verses": [
    {
      "verse": 16,
      "text": "For God so loved the world, as to give his only begotten Son..."
    }
  ]
}
```

#### 4. Idempotency

The import process is fully idempotent. Records are uniquely identified by a combination of:
`source_type` + `source_name` + `reference`

- **New Records**: Created if the combination does not exist.
- **Existing Records**: Updated if the content or metadata has changed.
- **Unchanged Records**: Skipped if the content and metadata are identical.

Import manifests are tracked in the `import_manifests` table to prevent re-processing identical files (based on SHA-256 checksum).

#### 5. Source and License Metadata

You can pass source and license information via command options. These will be merged into the metadata of every imported document:

```bash
php artisan knowledge:import \
  --source-url="https://example.com/source" \
  --license="Public Domain" \
  --language="en"
```

Available options:
- `--source-url`
- `--license`
- `--license-url`
- `--rights-notes`
- `--language` (defaults to `en`)

#### 6. Copyright Warning

⚠️ **IMPORTANT**: Do not import copyrighted texts without explicit permission from the rights holder. This includes most modern Bible translations (e.g., NABRE, RSV-2CE) and the full modern Catechism of the Catholic Church. 

The Douay-Rheims Bible and many writings of the Church Fathers are in the public domain and are safe to import.
