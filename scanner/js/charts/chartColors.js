/**
 * Purpose: Resolve stable chart and series colors from saved, shared, and fallback palettes.
 * Loaded by: Shared admin/Observatory footers and ES-module chart consumers.
 * Inputs/outputs: Accepts chart/field color metadata; exposes color helpers on globalThis.
 * Dependencies: None.
 * Load order: Load before classic or module consumers that read IRISChartColors.
 */
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

  function sharedColorIsNewer(fieldKey, fieldColorUpdatedAt, chartUpdatedAt) {
    const fieldUpdated = Date.parse(fieldColorUpdatedAt?.[fieldKey] || '');
    if (!Number.isFinite(fieldUpdated)) return false;
    const chartUpdated = Date.parse(chartUpdatedAt || '');
    return !Number.isFinite(chartUpdated) || fieldUpdated >= chartUpdated;
  }

  /**
   * Resolve colors by shared-field freshness, then chart/legacy values, then defaults.
   * @param {string[]} fields Display names used to look up shared field colors.
   * @param {Object} options Saved colors, update timestamps, and fallback settings.
   * @returns {string[]} One valid color per requested field.
   */
  function resolveFieldColors(fields, {
    chartColors = null,
    fieldColors = globalThis.IRISFieldColors || {},
    fieldColorUpdatedAt = globalThis.IRISFieldColorUpdatedAt || {},
    chartUpdatedAt = null,
    chartColorsOverrideShared = false,
    legacyColors = null,
    defaultColors = DEFAULT_CHART_COLORS
  } = {}) {
    return fields.map((field, index) => {
      const key = normalizeFieldKey(field);
      const fieldColor = fieldColors instanceof Map
        ? fieldColors.get(key)
        : Object.prototype.hasOwnProperty.call(fieldColors, key) ? fieldColors[key] : null;
      if (chartColorsOverrideShared && isValidChartColor(chartColors?.[index])) return chartColors[index].toUpperCase();
      if (isValidChartColor(fieldColor) && sharedColorIsNewer(key, fieldColorUpdatedAt, chartUpdatedAt)) return fieldColor.toUpperCase();
      if (isValidChartColor(chartColors?.[index])) return chartColors[index].toUpperCase();
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

  /** Attach resolved itemStyle colors to chart points without mutating the input data. */
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