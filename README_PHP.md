# PHP Deployment Notes

This repository is the framework-free PHP application. Pages and APIs are served by Apache/PHP and use PDO to access the shared MySQL database. Tailwind CSS, Flowbite, Font Awesome, Apache ECharts, and browser parsing libraries are loaded by the frontend pages.

## Local XAMPP

Follow the full setup in [`README.md`](README.md). In brief: create `iris_db`, import `database.sql`, adjust `config/db.php` for the local connection, start Apache/MySQL, then open `http://localhost/iris/`.

The server-side runtime does not require Laravel, Composer, Node.js, or Python. Node.js is optional and is used only to run the tests with `node --test scanner/test/*.test.js`.

## Browser Processing

The Scanner uses browser-side JavaScript for spreadsheet ingestion and charting. Parser modules also support document text/viewing workflows for PDF and DOCX and image OCR support where the corresponding browser libraries are available. Smart Upload and chart recommendations do not call an external AI API.

## PHP API

- `api/iris.php` handles authenticated record and saved-graph operations.
- `api/dashboard_graphs.php` supplies only explicitly published (`saved_graphs.is_published = 1`) graphs to the public Observatory and sends no-cache headers.
- `config/db.php` centralizes PDO setup and scanner table creation/compatibility migration.
- `api/templates.php` and `api/imports/` provide Super Admin template-driven import profiles, previews, guarded applies, audit batches, and recovery.
- `api/change_signal.php` exposes the authenticated shared write version used by active-page refresh polling.

## Template-Driven Imports

Office uploads are parsed server-side as CSV, TSV, or XLSX by the existing native reader. V3.4.7 adds the reusable Unified Summary Cards profile, which can be selected independently of a specific uploaded template; Super Admins choose the active profile and can map Global Label, period, card content, categories, and display precision. A Super Admin reviews the generated diff, acknowledges it, and approves. Summary Card imports require a stable `import_key` Global Label and merge against complete database history. Backfills never replace newer periods. Blank cells preserve values; `__CLEAR__` clears explicitly. New periods are unpublished and only explicit period publication changes the public current period. Ranking rows use their complete identity and block ambiguous matches. Row versions reject stale previews, and import audit rows support recovery. No Composer dependency is required by the current reader.

Apply `20261002_template_driven_imports.sql`, `20261002_allow_builtin_snapshot_imports.sql`, `20261002_summary_card_history_v2.sql`, `20261002_unified_summary_card_profiles.sql`, `20261002_summary_card_category_precision_mapping.sql`, and `20261002_create_app_change_state.sql` after the earlier schema migrations. Unified Summary Card imports need a stable Global Label column or a configured profile default. Equal period end dates sort by granularity (day, month, quarter, year). `config/db.php` also ensures the app change-state table exists on connection.

The shared refresh client watches successful data changes on open pages. Super Admin tabs synchronize promptly; other roles poll every five seconds. A page with unsaved form or Studio edits defers the reload and displays a refresh prompt.

## Publish and Unpublish

Record approval and graph publication are independent. The Studio Publish action updates `records.status` to `Approved` and saves its active chart with `saved_graphs.is_published = 1`. The Saved Graphs page publishes selected graph IDs without approving their source records. Observatory Unpublish sets that graph's `is_published` field to `0`; it does not delete the graph or its chart data.

Graph publish/unpublish requests use `POST /api/iris.php?resource=graphs&id={graphId}&action=publish` or `action=unpublish` with JSON `{ "published": true|false }`. `api/iris.php` requires authentication and checks for the `admin` role before mutation. The public dashboard endpoint is read-only and exposes only rows with `is_published = 1`.

The records archive also supports file-level publication. `POST /api/iris.php?resource=records&id={recordId}&action=unpublish` returns the record to `Pending Review` and sets every linked saved graph (`saved_graphs.record_id = records.id`) to unpublished in one transaction. Bulk archive actions use `POST /api/iris.php?resource=records&action=bulk-publish` or `action=bulk-unpublish` with JSON `{ "ids": ["record-id"] }`; each action updates selected records and their linked graphs atomically. These actions do not delete chart data. Summary cards are managed independently in `summary_cards` and are not linked to Scanner records.
