# Graph Generation and Chart Data

The graph layer produces suggestions from scanned content and provides shared chart-data operations for the studio.

## Graph engine

[graphEngine.js](graphEngine.js) generates draft chart objects from:

- spreadsheet sheets with a label column and numeric columns,
- numeric key/value patterns extracted from document text,
- fallback document metrics when no numeric series are detected.

Each draft contains a title, source, recommended chart type, recommendation text, labels, and a dataset.

## Chart utilities

- `chartMapping.js` infers label and numeric columns and parses formatted numeric values.
- `chartData.js` groups duplicate labels and prepares circular chart data.
- `graphExport.js` normalizes draft/saved graph payloads and builds printable sheets, including the combined Print All document.

The live studio chart is rendered by [../modules/chartEngine.js](../modules/chartEngine.js). Draft cards are rendered by [../modules/graphsTab.js](../modules/graphsTab.js).

## Integration boundary

`graphEngine.js` produces draft data and does not persist records or call the Python scanner service. A host application can consume the draft object, apply its own approval policy, and pass normalized chart data to the studio renderer. The v7 pass keeps this boundary explicit; backend/dashboard wiring remains deferred.

## Chart types

- Bar charts compare distinct categories.
- Line charts represent temporal or sequential trends.
- Pie/doughnut/polar charts represent proportional data.

The chart layer also supports filtering, sorting, row limits, duplicate grouping, axis mapping, labels, warnings, and empty states.

Saved graph export content is data-only. The `.txt` export uses saved labels, values, title, source, and chart type to generate SQL-formatted `CREATE TABLE` and `INSERT INTO` blocks; it does not read chart canvases or serialize rendered graphs. The printable helpers are separate and retain chart previews and tables for Print Sheet and Print All.
