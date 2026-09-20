# Database and Persistence

[dbManager.js](dbManager.js) is the browser-side persistence adapter. The server-side implementation is [../../server.js](../../server.js).

## v7 Host configuration

REST paths are grouped in `DEFAULT_API_ENDPOINTS` and copied into the manager configuration. A future host can provide endpoint overrides without changing CRUD behavior or the browser fallbacks. The adapter still defaults to the current Express routes and does not establish a new backend integration by itself.

## Persistence layers

1. MySQL through the Express REST API is the canonical store when available.
2. IndexedDB stores browser records for offline continuity.
3. localStorage provides a lightweight backup for records.

Record reads prefer the server. Failed server calls fall back to browser storage where supported.

## Stored entities

### `records`

Stores file identity, type, size, scan date, status, extracted data, graph drafts, metadata, and admin notes.

### `saved_graphs`

Stores cleaned chart snapshots linked to a record through `record_id`, including title, chart type, labels, values, and creation time.

## Graph operations

`DatabaseManager` exposes:

- `saveGraph()` for cleaned studio charts,
- `getGraphsByRecord()` for draft/saved chart rendering,
- `getAllSavedGraphs()` for the saved graph admin view,
- `exportGraph()` for normalized draft export,
- `exportGraphs()` for the live database export mode used by selected saved graphs,
- `printGraphSheet()` for free printable output,
- `printGraphSheets()` for one combined print window containing multiple saved graphs,
- `deleteGraph()` for saved graph removal.

The saved-graph text export does not use `exportGraphs()`. It is generated in the Saved Dashboard Graphs module from the already loaded labels, values, and metadata, then downloaded as a `.txt` file containing SQL-formatted statements. This keeps text export separate from live database writes.

## API endpoints

```text
GET    /api/records
POST   /api/records
PUT    /api/records/:id
DELETE /api/records/:id
GET    /api/graphs
GET    /api/graphs/:recordId
POST   /api/graphs
DELETE /api/graphs/:id
```

The server creates the required tables during startup. The default connection is configured directly in `server.js` for the current internal deployment.
