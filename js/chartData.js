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
      value: Number((sum / counts.get(label)).toFixed(4))
    }));
  }

  /**
   * Group for circular charts (sum duplicates).
   */
  function prepareCircularData(rows, groupDuplicates) {
    if (groupDuplicates) {
      const grouped = new Map();
      rows.forEach(row => grouped.set(row.label, (grouped.get(row.label) || 0) + row.value));
      rows = Array.from(grouped, ([label, value]) => ({ label, value }));
    }
    return {
      rows,
      legendLabels: Array.from(new Set(rows.map(row => row.label)))
    };
  }

  return { prepareCircularData, groupAndAggregate };
});
