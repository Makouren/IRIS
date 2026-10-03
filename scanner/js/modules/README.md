# Scanner Frontend Modules

> Current application release: [V6.7.0](../../../README_V6.7.0.md).

V5.7.0 adds explicit saved-graph editing, independent chart-color persistence, Summary Card category/default management, and Super Admin record merge/File History workflows. `changeRefresh.js` provides role-aware refresh signaling across open IRIS pages.

[`../app.js`](../app.js) initializes the browser application and creates a shared context with scanner, database manager, state, and module APIs. Modules exchange callbacks through `ctx.api`; DOM queries stay with the module that owns the UI.

## State and rendering

[`state.js`](state.js) stores the active scan/record, queues, filters, and chart instances. [`chartEngine.js`](chartEngine.js) handles Studio row filtering, sorting, grouping, limits, and ECharts options. [`studioWorkbench.js`](studioWorkbench.js) saves the active chart and its configuration to Saved Graphs; editing a saved graph restores its field mappings, filters, sorting, limits, grouping, and chart-specific settings. Studio Publish approves the active record and publishes only its active chart.

`chartEngine.js` builds the ECharts option used by Studio, Saved Graphs, and the public Observatory. Draft and saved graph cards mount options through `createChart()`, which also adapts legacy saved label/value payloads. Studio can reopen saved graph configuration for editing; shared field colors are saved independently from a chart. Supported chart types are Line, Stacked Area, Bar, Pie, Doughnut, and Nested Pie.

## Module responsibilities

| Module | Responsibility |
| --- | --- |
| `navigation.js` | Scanner and admin view switching |
| `navigationTabs.js` | Scanner and admin tab switching |
| `fileIngestion.js` | File selection, drag/drop, samples, progress, IndexedDB upload handoff, and scan orchestration |
| `queue.js` | Ingestion queue and active scan selection |
| `overviewTab.js` | Extracted fields and scan overview |
| `viewerTab.js` | Basic scan viewer |
| `graphsTab.js` | Draft chart cards and print actions |
| `savedGraphsTab.js` | Saved graph cards, filtering, selection, explicit per-graph publish, exports, print, and deletion |
| `adminPortal.js` | Record archive, search, statistics, status actions, and record merge/File History workflows |
| `studioWorkbench.js` | Studio record setup, field mapping, Save Graph, and publish of the active chart |
| `documentViewer.js` | Document viewing, paging, zoom, and copy behavior |
| `tableGrid.js` | Editable Studio table and row/column operations |
| `chartEngine.js` | ECharts rendering and Studio data transformations |
| `studioActions.js` | Studio add-field/add-row actions |

## Saved Graphs and Exports

Saved graph rows are stored through `dbManager.js` and the authenticated PHP graph API. The `is_published` flag is independent of record approval; Saved Graphs publishes selected IDs, while Studio Publish approves the record and publishes its active chart. Observatory Unpublish clears the same graph flag without deleting chart data. SQL-formatted `.txt` exports are generated from saved labels, values, and metadata; print sheets render chart previews and data tables separately.

## Development Checks

Run the dependency-free tests from the repository root with `node --test scanner/test/*.test.js`. Browser-test responsive layout, theme updates, dropdown behavior, and public/admin navigation when changing these views.
