# Scanner Tests

V3.4.5 import changes were not accompanied by automated tests. Manually verify template mapping, one selected live period per Global Label, history archival, stale-preview rejection, and import recovery against the local XAMPP database.

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
