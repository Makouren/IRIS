(function (root, factory) {
  if (typeof module === 'object' && module.exports) {
    module.exports = factory();
  } else {
    root.ChartData = factory();
  }
})(typeof self !== 'undefined' ? self : this, function () {
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

  return { prepareCircularData };
});
