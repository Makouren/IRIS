/**
 * Purpose: Prepare and serialize chart rows and saved chart state.
 * Loaded by: scanner/index.php as a classic script; required by Node tests.
 * Inputs/outputs: Accepts row/chart data; exports ChartData for browser and CommonJS consumers.
 * Dependencies: None.
 * Load order: Load before Studio modules that consume ChartData.
 */
(function (root, factory) {
  if (typeof module === 'object' && module.exports) {
    module.exports = factory();
  } else {
    root.ChartData = factory();
  }
})(typeof self !== 'undefined' ? self : this, function () {

  /**
   * Group and aggregate rows by label for Bar/Line charts.
   * Duplicate labels are averaged (suitable for scores/rankings).
   */
  function groupAndAggregate(rows) {
    const grouped = new Map();
    const counts = new Map();
    rows.forEach(row => {
      const key = row.label;
      grouped.set(key, (grouped.get(key) || 0) + row.value);
      counts.set(key, (counts.get(key) || 0) + 1);
    });
    return Array.from(grouped, ([label, sum]) => ({
      label,
      value: Number((sum / counts.get(label)).toFixed(4)),
      rawValue: Number((sum / counts.get(label)).toFixed(4))
    }));
  }

  /**
   * Group for circular charts (sum duplicates).
   */
  function prepareCircularData(rows, groupDuplicates) {
    if (groupDuplicates) {
      const grouped = new Map();
      rows.forEach(row => grouped.set(row.label, (grouped.get(row.label) || 0) + row.value));
      rows = Array.from(grouped, ([label, value]) => ({ label, value, rawValue: value }));
    }
    return {
      rows,
      legendLabels: Array.from(new Set(rows.map(row => row.label)))
    };
  }

  /**
   * Group pie rows by a selected parent column while preserving their child values.
   * @param {Array<Object>} rows Prepared chart rows with source row values.
   * @param {number} groupField Zero-based source-column index.
   * @returns {{groups: Array<Object>, rows: Array<Object>}} Parent groups and flattened children.
   */
  function prepareNestedPieData(rows, groupField) {
    if (!Number.isInteger(groupField) || groupField < 0) return { groups: [], rows: [] };
    const groups = new Map();
    rows.forEach(row => {
      const group = String(row.row?.[groupField] ?? '').trim();
      if (!group) return;
      if (!groups.has(group)) groups.set(group, { label: group, value: 0, rawValue: 0, children: [] });
      const value = Number(row.rawValue ?? row.value ?? 0);
      const child = { label: row.label, value, rawValue: value };
      const parent = groups.get(group);
      parent.value += value;
      parent.rawValue += value;
      parent.children.push(child);
    });
    return { groups: Array.from(groups.values()), rows: Array.from(groups.values()).flatMap(group => group.children) };
  }

  /**
   * Recover editable labels, raw values, and series from rendered chart options.
   * @param {Object} options Chart options, usually from ECharts.
   * @param {Object} config Persisted chart settings used to retain original values.
   * @returns {{labels: string[], values: Array<unknown>, series: Array<Object>}} Editable chart state.
   */
  function serializeChartState(options, config = {}) {
    const sourceSeries = Array.isArray(options?.series) ? options.series : [];
    const series = sourceSeries.map((item, seriesIndex) => ({
      name: item.name || `Series ${seriesIndex + 1}`,
      data: (Array.isArray(item.data) ? item.data : []).map((point, index, points) => {
        const sourceIndex = config.reverseOrder ? points.length - index - 1 : index;
        const sourcePoint = points[sourceIndex];
        return sourcePoint && typeof sourcePoint === 'object' ? sourcePoint.rawValue ?? sourcePoint.value : sourcePoint;
      })
    }));
    const firstSeries = sourceSeries[0] || {};
    const points = Array.isArray(firstSeries.data) ? firstSeries.data : [];
    const categoryAxis = Array.isArray(options?.xAxis) ? options.xAxis[0]?.data : options?.xAxis?.data;
    const valueAxis = Array.isArray(options?.yAxis) ? options.yAxis[0]?.data : options?.yAxis?.data;
    const axisLabels = Array.isArray(categoryAxis) ? categoryAxis : (Array.isArray(valueAxis) ? valueAxis : []);
    const nestedPie = config.type === 'nestedPie';
    const hasCurrentPoints = points.length > 0;
    const useConfiguredLabels = config.labels?.length && (nestedPie || !hasCurrentPoints || config.labels.length === points.length);
    const labels = useConfiguredLabels ? config.labels.map(String) : points.map((point, index) => {
      if (point && typeof point === 'object' && point.name !== undefined) return String(point.name);
      return String(axisLabels[index] ?? `Item ${index + 1}`);
    });
    const useConfiguredValues = Array.isArray(config.rawValues) && (nestedPie || !hasCurrentPoints || config.rawValues.length === points.length);
    const values = useConfiguredValues
      ? config.rawValues.slice()
      : points.map(point => point && typeof point === 'object' ? point.rawValue ?? point.value : point);
    return { labels, values, series };
  }

  return { prepareCircularData, prepareNestedPieData, groupAndAggregate, serializeChartState };
});
