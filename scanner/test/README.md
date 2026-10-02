# Scanner Tests

V5.3.0 adds coverage for destination-aware imports and mapping alongside the existing Unified Summary Cards tests. Run the PHP mapping test as well as the JavaScript suite; also manually verify office upload destinations, File Archives filters and row actions, role-gated navigation, ranking defaults and viewer filters, graph scope editing and grouping, and import recovery against the local XAMPP database. See [README_V5.3.0.md](../../README_V5.3.0.md) for release verification details.

```powershell
php scanner/test/templateImportMapping.test.php
```

Tests use Node's built-in `node:test` runner. No npm install or package manifest is required. From the repository root, run the full suite with:

```powershell
node --test scanner/test/*.test.js
```

To run the chart and graph/export coverage by itself:

```powershell
node --test scanner/test/chartMapping.test.js scanner/test/dashboardData.test.js
```

## Coverage

- Chart field inference, numeric parsing, and existing rank detection/inversion
- ECharts graph generation, Ranked Bar mapping, category/time columns, identifier exclusion, invalid cells, and multiple metrics
- Table filtering and document pagination
- Graph serialization, ECharts-series normalization, printable sheets, and SQL-formatted text exports
- Publish/unpublish API wiring, explicit graph publication state, Studio active-chart publication, and public dashboard cache behavior
- Upload input IDs and other structural frontend contracts

These tests cover utilities and source-level contracts. They do not replace browser checks for responsive layout, theme updates, dropdown behavior, authentication, or end-to-end Apache/MySQL behavior. Publish persistence and upload handoff should be smoke-tested against a running authenticated XAMPP/MySQL instance when those flows change.

## Layout contract

The scanner layout contract is defined by the current Studio grid at `minmax(0, 480px) minmax(0, 1fr)` with responsive fallback behavior at `@media (max-width: 1200px)` and overflow protection for tables and modal cards.
