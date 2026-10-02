# IRIS

**IRIS (International Rapport Insight System)** is the CLSU International Affairs Office's institutional performance data and observatory application. It combines structured ranking and program data, reviewed file ingestion, saved analytics, and a user-facing Observatory.

IRIS is a plain-PHP application. Apache serves PHP pages and PDO-backed APIs; browser JavaScript handles interactive workflows, file parsing, and charts. There is no Laravel application, Composer runtime, Node.js web server, or Python service.

## V4.5.0 Highlight

V4.5.0 expands workbook-based Ranking History imports and administration, adds reusable workbook mapping for ranking and summary-card profiles, and lets office users select **Data and Report Visualization** as the general upload destination. Super Admins can search and manage rankings, set public chart defaults, and review destination-specific imports. The public Ranking History chart runs as a dedicated JavaScript module. See [README_V4.5.0.md](README_V4.5.0.md) for changes, migrations, and verification.

## What the System Does

- Presents institutional rankings, ranking breakdowns, college contributions, program results, and accreditation data.
- Lets administrators import structured CSV rows into the institutional database through a reviewable staging workflow.
- Provides a Scanner for spreadsheet ingestion, extracted-data review, editable tables, and chart drafting.
- Stores records, saved graph configurations, and manually managed performance snapshot cards in MySQL.
- Publishes selected graphs and snapshot cards to the Observatory without conflating graph publication with record approval.
- Provides authentication, user/admin roles, record management, graph exports, and print views.

## Roles and Access

- **User:** registers with a CLSU email address, signs in, and reads the Observatory.
- **Admin:** has all user access plus office uploads and the File Ingestion dashboard.
- **Super Admin:** manages accounts, templates, ranking bodies, review workflows, ranking history, summary cards, and saved graphs.

Registration creates a `user` role. An administrator must promote accounts explicitly. PHP session authentication protects the Observatory and APIs; mutations use role checks and CSRF tokens. Do not expose local database credentials in a deployed environment.

## Main Areas

| Area | Entry point | Purpose |
| --- | --- | --- |
| Application router | `index.php` | Redirects signed-in users to the Observatory or admins to File Ingestion; otherwise opens login. |
| Observatory | `user/dashboard.php` | Displays institutional analytics, published snapshot cards, and Scanner-published graphs. Admins also see editing and unpublish controls. |
| File Ingestion | `admin/dashboard.php` | Accepts Scanner spreadsheet uploads and shows parsed records for review. |
| Review Editor | `admin/review_editor.php` | Reviews records, edits extracted cells and chart mappings, saves records, and publishes the active Studio chart. |
| Template Imports | Review Editor import controls | Previews office spreadsheets, maps fields through Super Admin profiles, and requires an explicit diff review before writes. |
| Smart Upload | `admin/smart_upload.php` | Maps supported structured CSV headers into staging rows for the institutional tables. |
| Extraction Review | `admin/review_extraction.php` | Lets an admin inspect, edit, and select mapped rows before the Smart Upload database transaction. |
| Saved Graphs | `admin/saved_graphs.php` | Lists saved chart versions and supports per-graph or selected-graph publishing, export, printing, and deletion. |
| Scanner workspace | `scanner/index.php` | Provides browser-based ingestion, extraction overview, document/data viewing, draft charts, and links to the admin and Observatory. |

## Data Workflows

### Structured Institutional CSV

1. An admin uploads a CSV in Smart Upload.
2. Rule-based mapping checks the headers and stages recognized rows in the PHP session; it does not write them automatically.
3. The admin reviews and selects rows in the extraction review page.
4. Confirmation inserts the selected ranking, breakdown, college, program, and/or accreditation rows in a MySQL transaction and adds an `uploads_log` entry.

### Scanner Record and Chart

1. An admin uploads a CSV, XLS, or XLSX using the Scanner upload widget. The current upload validator allows these extensions and limits files to 100 MB.
2. Browser JavaScript parses the workbook and creates an editable pending record. Upload handoff between admin pages uses IndexedDB; MySQL persistence is performed through the PHP API.
3. The Review Editor can update record fields, extracted data, notes, and chart configuration. Save keeps the record in its selected review status and leaves the active graph unpublished.
4. Studio Publish sets `records.status` to `Approved` and saves the active chart with `saved_graphs.is_published = 1`. It does not publish every saved graph belonging to that record.
5. The archive can publish or unpublish selected records in bulk. File-level Unpublish returns the record to `Pending Review` and hides every saved graph linked by `record_id`; it does not delete either the record or its charts.

### Office Upload Destinations

Office users choose **Data and Report Visualization**, **Summary Cards**, or **Ranking History** before uploading a spreadsheet. General visualization uploads require an active analytics template and enter the regular record-review and charting workflow. Summary Card and Ranking History uploads use their active import profiles and remain separated from the general Review Editor dataset.

Parser and viewer modules for PDF, DOCX, and image OCR are present in the codebase, but the current Scanner upload widget accepts spreadsheets only. Smart Upload currently performs rule-based mapping for CSV files.

### Saved Graph Publish and Unpublish

- Saved Graphs Publish sends the selected graph IDs to the authenticated graph API and changes only those graph rows.
- Observatory Unpublish changes the same graph's publication flag back to false. The chart remains saved and can be published again.
- Archive file-level Unpublish resets one record to `Pending Review` and unpublishes all of its linked saved graphs. Bulk Publish/Unpublish applies the same record-and-chart behavior to each selected record in a single database transaction.
- Public Scanner-Published Analytics includes only saved graph rows whose `is_published` value is true. Its endpoint sends no-cache headers so state changes appear after refresh.

### Performance Snapshot Cards

Snapshot cards are maintained separately from Scanner graphs. Their title, values, labels, year, description, display order, precision, and publication state are stored in `summary_cards`. Publishing or unpublishing a card does not change records or saved graphs.

The Unified Summary Cards profile maps office spreadsheet headers to Summary Card fields, including Global Label (`summary_cards.import_key`), reporting period, card content, optional categories, and display precision. Imports compare every incoming period against database history. Older imports are backfills; only the latest explicitly published snapshot is public/current. New periods remain unpublished until an administrator publishes them. Blank cells preserve values; `__CLEAR__` explicitly clears a field. The Summary Card history manager supports correction, publication, provenance inspection, and read-only public history.

Ranking History imports match the full ranking identity, display a side-by-side preview, and reject ambiguous matches. The Super Admin must acknowledge the diff before applying selected rows. Optimistic row versions prevent applying stale previews.

After successful database writes, open IRIS pages refresh automatically. Super Admin tabs synchronize promptly; other roles poll every five seconds. Pages with unsaved edits defer refresh and offer a manual refresh action.

## Important Publication Rules

Publication and record review are distinct:

- `records.status` describes record review (`Pending Review`, `Approved`, or `Needs Revision`).
- `saved_graphs.is_published` controls whether a graph appears in Scanner-Published Analytics.
- `summary_cards.is_published` controls whether a snapshot card appears in the Observatory.

Approving a record does not implicitly publish its saved graphs. The public graph query filters by the graph flag; publication is never inferred from record approval.

## Architecture and Data

### Runtime

- **Server:** Apache with PHP 8.1+ and PDO.
- **Database:** MySQL or MariaDB, database name `iris_db` by default.
- **Frontend:** browser JavaScript modules and CSS. Pages load Tailwind, Flowbite, Font Awesome, ECharts, and parser libraries from CDNs, so the browser needs access to those hosts.
- **Tests:** Node.js is optional and is used only for the dependency-free test suite.

### System Architecture

The application is a server-rendered PHP site with browser-side modules. PHP owns authentication, authorization, validation, and MySQL transactions; JavaScript owns interactive page behavior, spreadsheet parsing, editing, and chart rendering. The browser talks directly to the PHP JSON API rather than to a separate Node service.

```mermaid
flowchart LR
   Browser[Browser]
   Pages[PHP pages<br/>login, admin, review, Observatory]
   Modules[JavaScript modules<br/>ingestion, review, charts, exports]
   API[PHP JSON APIs<br/>session auth and admin checks]
   DB[(MySQL / MariaDB)]
   CDNs[Browser libraries<br/>Tailwind, Flowbite, ECharts, parsers]

   Browser --> Pages
   Pages --> Modules
   Modules --> API
   Pages --> API
   API --> DB
   Browser --> CDNs
```

| Component | Responsibility |
| --- | --- |
| `index.php`, `auth/` | Route users into the application and manage login, registration, and PHP sessions. |
| `admin/` | Provide ingestion, review, Smart Upload, extraction review, and saved-graph management pages. |
| `scanner/js/` | Run browser workflows and call the PHP API through `DatabaseManager`; parsing and charting happen client-side. |
| `api/iris.php` | Authenticate record and graph reads; require admin authorization for mutations; persist changes using PDO. |
| `api/dashboard_graphs.php`, `api/summary.php` | Supply Observatory graph/card data and authenticated summary data. |
| `config/db.php`, `database.sql` | Configure the PDO connection and create the institutional and Scanner schema. |
| `user/dashboard.php` | Render institutional analytics and only explicitly published Scanner graphs and summary cards. |

For Scanner publication, a record is linked to its charts by `saved_graphs.record_id`. Record-level unpublishing changes `records.status` and the linked charts' `is_published` flags together. Summary cards are independent rows in `summary_cards` and are not associated with a Scanner file.

### Main Data Tables

| Table | Contents |
| --- | --- |
| `users` | Login identity, password hash, and `user`/`admin` role. |
| `ranking_bodies` | Ranking publisher catalog (QS, THE, WURI, and others). |
| `rankings` | Yearly global/national ranking values, categories, and notes. Original rank text is retained alongside derived numeric values. |
| `ranking_breakdowns` | Per-body, per-year items such as THE Impact SDGs or WURI categories. |
| `colleges` | College contribution metrics by year. |
| `programs` | College-linked program rank, score, movement, and year. |
| `accreditations` | Program-level assessment criteria, text scores, and optional numeric scores. |
| `uploads_log` | Admin CSV import filename, type, inserted row count, and uploader. |
| `records` | Scanner file metadata, extracted data, review status, notes, and draft metadata. |
| `saved_graphs` | Saved ECharts configuration/data linked to a record; `is_published` is the public visibility flag. |
| `summary_cards` | Independently managed Observatory snapshot cards and publication settings. |
| `template_import_profiles` | Super Admin mappings, required fields, and defaults for office spreadsheet imports. |
| `summary_card_snapshots` | Historical values for imported summary-card periods. |
| `import_batches`, `import_batch_rows` | Import audit records used to revert an applied batch. |
| `app_change_state` | Shared write version polled by active pages for refresh synchronization. |

`database.sql` creates the core institutional schema and Scanner `records`/`saved_graphs` tables. On database connection, `config/db.php` ensures the Scanner tables and compatibility columns exist and creates `summary_cards` when absent. The PHP database account therefore needs the required table/column creation privileges during setup or migration.

## API Map

`api/iris.php` is the session-authenticated JSON API. Mutating requests additionally require an admin role.

| Route | Purpose |
| --- | --- |
| `GET /api/iris.php?resource=records` | List Scanner records. |
| `POST /api/iris.php?resource=records` | Create a Scanner record. |
| `GET /api/iris.php?resource=records&id={recordId}` | Read one record. |
| `PUT /api/iris.php?resource=records&id={recordId}` | Update record fields and review status. |
| `DELETE /api/iris.php?resource=records&id={recordId}` | Delete a record and its linked saved graphs. |
| `POST /api/iris.php?resource=records&action=bulk-approve` | Approve selected records; does not publish their graphs. |
| `POST /api/iris.php?resource=records&id={recordId}&action=unpublish` | Return one record to Pending Review and unpublish all linked saved graphs. |
| `POST /api/iris.php?resource=records&action=bulk-publish` | Publish selected records and their linked saved graphs transactionally. |
| `POST /api/iris.php?resource=records&action=bulk-unpublish` | Return selected records to Pending Review and unpublish their linked graphs transactionally. |
| `GET /api/iris.php?resource=graphs` | List saved graphs. |
| `POST /api/iris.php?resource=graphs` | Save a graph and its chart data. |
| `POST /api/iris.php?resource=graphs&id={graphId}&action=publish` | Set one graph's `is_published` flag to true. |
| `POST /api/iris.php?resource=graphs&id={graphId}&action=unpublish` | Set one graph's `is_published` flag to false. |
| `GET /api/iris.php?resource=graphs&record_id={recordId}` | List graphs for one source record. |
| `POST /api/iris.php?resource=graphs&action=export` | Export selected saved graph data. |
| `GET /api/iris.php?resource=summary_cards` | List snapshot cards for the admin interface. |
| `POST/PUT/DELETE /api/iris.php?resource=summary_cards[&id={cardId}]` | Create, update, or delete snapshot cards. |
| `GET /api/dashboard_graphs.php` | Read-only Observatory feed containing explicitly published graphs and cards; response is non-cacheable. |
| `GET /api/summary.php` | Return an authenticated narrative summary based on stored institutional data. |
| `GET /api/change_signal.php` | Return the authenticated app data version used by active-page refresh polling. |
| `GET/POST /api/templates.php` | Read templates and manage Super Admin import profiles. |
| `POST /api/imports/summary_card_import.php` | Preview or apply template-driven summary-card imports. |
| `POST /api/imports/ranking_history_import.php` | Preview or apply template-driven ranking history imports. |
| `POST /api/imports/import_recovery.php` | Revert an audited import batch when its rows are unchanged since application. |
| `GET /api/iris.php?resource=summary_card_history&id={cardId}` | Read published period history; Super Admin can request `view=admin` for all periods. |
| `POST /api/iris.php?resource=summary_card_history&id={cardId}` | Correct a period or explicitly publish/unpublish it with optimistic locking. |

Graph publish/unpublish requests send JSON such as `{ "published": true }` or `{ "published": false }`. The API responds with the graph ID and the resulting publication state. Browser persistence is managed by `scanner/js/database/dbManager.js`; MySQL is canonical when the PHP API is available.

## Local Setup (XAMPP)

1. Place or clone the repository under `C:/xampp/htdocs/iris` (or another Apache document-root subdirectory).
2. Start Apache and MySQL from the XAMPP Control Panel.
3. Import [`database.sql`](database.sql) once into MySQL using phpMyAdmin or the MySQL client. The script creates/selects `iris_db` and seeds the ranking body catalog.
4. Apply the required migrations in [`migrations/`](migrations/), including template imports, app change state, Summary Card history, and the Unified Summary Cards profile migrations. Imports require a stable Global Label column or a configured profile default.
5. Set `IRIS_DB_HOST`, `IRIS_DB_PORT`, `IRIS_DB_NAME`, `IRIS_DB_USER`, and `IRIS_DB_PASS` in [`config/db.php`](config/db.php) for the environment. The current defaults are intended for local XAMPP development, not production.
6. Open `http://localhost/iris/`, register a CLSU account, and sign in. Registration requires an email ending in `@clsu2.edu.ph` and a password of at least eight characters.
7. Registration assigns the `user` role. To grant administrator access, run the following as a database administrator, substituting the account name:

   ```sql
   UPDATE users SET role = 'admin' WHERE username = 'YOUR_USERNAME';
   ```

8. Sign out and back in so the new role is present in the PHP session. The application root redirects signed-in users according to their role.

For production, configure a least-privilege MySQL account, a non-default password, HTTPS, and a schema migration process appropriate to the deployment. Because the database bootstrap may create or add Scanner schema objects, ensure its database account has the necessary setup privileges.

## Repository Map

```text
auth/                    Registration, login, logout, and PHP sessions
admin/                   File intake, record review, Smart Upload, saved graphs
api/                     Authenticated IRIS API and Observatory read endpoints
config/db.php            PDO connection and Scanner schema compatibility setup
includes/                Authentication, rank helpers, extractors, data inserts
scanner/index.php        Authenticated Scanner application shell
scanner/js/              Browser app, charting, persistence, modules, parsers
scanner/css/             Scanner styles
scanner/test/            Dependency-free Node tests
user/dashboard.php       Signed-in Observatory interface
database.sql             Core schema and seed ranking-body data
```

## Tests

Node.js is not required to run the PHP application. To run the browser-logic and source-contract tests from the repository root:

```powershell
node --test scanner/test/*.test.js
```

The suite covers graph/chart mapping, table filtering, document pagination, graph exports, publication wiring, and UI contracts. It does not replace an authenticated browser smoke test against Apache/MySQL for changes to login, upload, persistence, or publish/unpublish behavior. See [`scanner/test/README.md`](scanner/test/README.md) for test coverage details.

## Related Documentation

- [`README_PHP.md`](README_PHP.md): PHP deployment notes.
- [`README_V4.5.0.md`](README_V4.5.0.md): V4.5.0 release changes and migration steps.
- [`README_V3.4.7.md`](README_V3.4.7.md): Historical V3.4.7 release details.
- [`README_V3.4.5.md`](README_V3.4.5.md): Historical V3.4.5 release notes.
- [`scanner/js/ai/README.md`](scanner/js/ai/README.md): chart suggestions and shared chart utilities.
- [`scanner/js/database/README.md`](scanner/js/database/README.md): persistence and API routes.
- [`scanner/js/modules/README.md`](scanner/js/modules/README.md): browser module responsibilities.
- [`scanner/js/parsers/README.md`](scanner/js/parsers/README.md): parser/viewer modules and data contract.
- [`scanner/test/README.md`](scanner/test/README.md): automated test commands and coverage.