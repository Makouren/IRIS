# Database and Persistence

V5.3.0 adds destination-aware office uploads and a Super Admin File Archives workspace. Ranking and summary-card source files are kept out of the general Review Editor record list; their data is managed through dedicated import APIs. Summary Card rows, categories, precision, and historical snapshots persist through the PHP/MySQL pipeline. Saved graphs can be grouped by scope, and ranking bodies have an editable display order. The authenticated `api/change_signal.php` endpoint lets active pages detect successful writes; Super Admin pages synchronize promptly and other roles poll every five seconds. See [`README_V5.3.0.md`](../../../README_V5.3.0.md) for release migrations and verification.

[`dbManager.js`](dbManager.js) is the Scanner's browser-side persistence adapter. It communicates with the PHP/PDO API in `api/iris.php`; there is no Express server.

## Storage

- MySQL is the canonical store when the authenticated PHP API is available.
- IndexedDB and localStorage provide browser-side fallback for records; IndexedDB also carries pending upload files between admin pages.
- Saved graph snapshots are linked to records through `record_id`.
- `records.status` tracks record review/approval. `saved_graphs.is_published` independently controls public visibility; approving a record does not publish every graph attached to it.

The PHP runtime connects to the normalized `iris_db_3nf` schema through `config/db.php`; it does not create or alter tables. `database.sql` and historical migrations targeting `iris_db` describe a retired schema and must not be used to provision V5.3.0.

## Saved Graph Data

Saved graphs store chart type, labels, real values, chart configuration, rank and axis metadata, colors, publication state, and creation time. Supported types are Bar, Line, Pie, Doughnut, Ranked Bar, and Nested Pie. Publication is explicit and is not inferred from record status.

## PHP API Routes

All routes use `api/iris.php` with query parameters:

```text
GET/POST       /api/iris.php?resource=records
GET/PUT/DELETE /api/iris.php?resource=records&id={recordId}
GET/POST       /api/iris.php?resource=graphs
GET/DELETE     /api/iris.php?resource=graphs&id={graphId}
GET            /api/iris.php?resource=graphs&record_id={recordId}
POST           /api/iris.php?resource=graphs&id={graphId}&action=publish
POST           /api/iris.php?resource=graphs&id={graphId}&action=unpublish
POST           /api/iris.php?resource=graphs&action=export
```

Graph publish/unpublish requests send JSON `{ "published": true|false }`. `api/iris.php` requires a signed-in admin for mutations and returns the persisted graph ID and publication state. The public Observatory reads only rows with `saved_graphs.is_published = 1` from `api/dashboard_graphs.php`; that endpoint disables caching so state changes are visible on refresh.

## Graph Operations

`DatabaseManager` provides record CRUD, graph save/read/delete/publish, database-copy export, and printable sheet helpers. Studio Publish approves the record and saves only the active chart as published. Saved Graphs Publish changes only selected graph rows; Observatory Unpublish clears the same `is_published` field without deleting chart data. SQL-formatted text export is built in `../modules/savedGraphsTab.js` from loaded graph data and is separate from database export. Print Sheet and Print All use `../graphExport.js`.
