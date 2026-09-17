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
- `graphExport.js` normalizes draft/saved graph payloads and builds printable sheets.

The live studio chart is rendered by [../modules/chartEngine.js](../modules/chartEngine.js). Draft cards are rendered by [../modules/graphsTab.js](../modules/graphsTab.js).

## Chart types

- Bar charts compare distinct categories.
- Line charts represent temporal or sequential trends.
- Pie/doughnut/polar charts represent proportional data.

The chart layer also supports filtering, sorting, row limits, duplicate grouping, axis mapping, labels, warnings, and empty states.
