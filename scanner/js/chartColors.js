(function (root, factory) {
  const api = factory();
  if (typeof module !== 'undefined' && module.exports) module.exports = api;
  root.IRISChartColors = api;
})(globalThis, function () {
  const DEFAULT_CHART_COLORS = [
    '#1E6031', '#E0A70D', '#0F766E', '#335C81',
    '#9A4D26', '#6B5B73', '#7A1F2B', '#6B7280'
  ];

  function isValidChartColor(color) {
    return typeof color === 'string' && /^#[0-9A-Fa-f]{6}$/.test(color);
  }

  function normalizeFieldKey(value) {
    return String(value ?? '').trim().toLowerCase().replace(/\s+/g, ' ');
  }

  function resolveFieldColors(fields, {
    chartColors = null,
    fieldColors = root.IRISFieldColors || {},
    legacyColors = null,
    defaultColors = DEFAULT_CHART_COLORS
  } = {}) {
    return fields.map((field, index) => {
      if (isValidChartColor(chartColors?.[index])) return chartColors[index].toUpperCase();
      const key = normalizeFieldKey(field);
      const fieldColor = fieldColors instanceof Map
        ? fieldColors.get(key)
        : Object.prototype.hasOwnProperty.call(fieldColors, key) ? fieldColors[key] : null;
      if (isValidChartColor(fieldColor)) return fieldColor.toUpperCase();
      if (isValidChartColor(legacyColors?.[index])) return legacyColors[index].toUpperCase();
      return defaultColors[index % defaultColors.length];
    });
  }

  function getChartColors(colors, count) {
    return resolveFieldColors(Array.from({ length: Math.max(0, count) }, (_, index) => `Item ${index + 1}`), {
      chartColors: colors,
      fieldColors: {},
      defaultColors: DEFAULT_CHART_COLORS
    });
  }

  function buildColoredSeriesData(data, names, colors) {
    const source = Array.isArray(data) ? data : [];
    const fields = Array.isArray(names) ? names : [];
    const resolved = resolveFieldColors(fields.length ? fields : source.map((item, index) => item?.name || `Item ${index + 1}`), {
      chartColors: colors,
      fieldColors: globalThis.IRISFieldColors || {},
      defaultColors: DEFAULT_CHART_COLORS
    });
    return source.map((point, index) => {
      const entry = point && typeof point === 'object' ? { ...point } : { value: point };
      const name = entry.name ?? fields[index] ?? `Item ${index + 1}`;
      const color = resolved[index] || DEFAULT_CHART_COLORS[index % DEFAULT_CHART_COLORS.length];
      return {
        ...entry,
        name,
        value: Object.prototype.hasOwnProperty.call(entry, 'value') ? entry.value : point,
        itemStyle: { ...(entry.itemStyle || {}), color }
      };
    });
  }

  return { DEFAULT_CHART_COLORS, buildColoredSeriesData, getChartColors, isValidChartColor, normalizeFieldKey, resolveFieldColors };
});