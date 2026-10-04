# Graph Generation and Chart Data

> Current application release: [V6.7.0](../../../docs/releases/README_V6.7.0.md).

V5.7.0 adds saved-graph editing and standalone chart-color persistence through the shared chart/data modules. It does not introduce a remote AI service or change the chart-recommendation boundary described here.

This folder contains browser-side chart recommendations. It does not call an external AI service. The `AI` label describes the recommendation logic, not a remote model dependency.

## Draft generation

[`graphEngine.js`](graphEngine.js) creates draft chart objects from numeric spreadsheet columns and simple numeric key/value patterns extracted from document text. A draft includes its title, source, recommendation, labels, values, and chart metadata. Persistence and approval are handled by the Scanner's PHP-backed database manager and Studio workflow.

## Shared chart utilities

- `../charts/chartMapping.js` infers category/value columns and parses numeric and rank values used by Studio charts.
- `../charts/chartData.js` serializes chart state and supplies grouping helpers.
- `../charts/graphExport.js` normalizes export payloads and builds Print Sheet/Print All documents.
- `../modules/chartEngine.js` owns Studio option routing; `../modules/graphsTab.js` owns draft-card rendering.

## Chart types

GraphEngine and Studio support Line, Stacked Area, Bar, Pie, Doughnut, and Nested Pie charts. Studio provides year filtering, reverse order for axis charts, a series grouping field for Stacked Area, and a selected group field for Nested Pie. All chart views are built by the shared Apache ECharts option builder. Excel drafts use parser-provided rows, retain separate numerical fields as separate chart suggestions, infer chronological sequences from values as well as headers, and omit invalid cells rather than converting them to zero.

Saved-graph SQL-formatted text exports use graph data, not rendered canvas pixels. Print exports render a chart preview separately from the data table. Publishing is separate from chart generation: the database graph row carries the explicit publication flag.
