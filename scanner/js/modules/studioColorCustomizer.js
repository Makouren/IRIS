import '../../vendor/vanilla-colorful/hex-color-picker.js';
import '../chartColors.js?v=data-preserving-colors-20260930';

const { DEFAULT_CHART_COLORS, buildColoredSeriesData, isValidChartColor, normalizeFieldKey, resolveFieldColors } = globalThis.IRISChartColors;

export function initStudioColorCustomizer(ctx) {
  let persistedFieldColors = {};
  let persistedFieldColorUpdatedAt = {};
  const colorSaveStates = new Map();
  ctx.api.fieldColorsReady = ctx.dbManager.getFieldColorRows()
    .then(rows => {
      persistedFieldColors = Object.fromEntries(rows.map(row => [row.field_key, row.color]));
      persistedFieldColorUpdatedAt = Object.fromEntries(rows.filter(row => row.updated_at).map(row => [row.field_key, row.updated_at]));
      globalThis.IRISFieldColors = Object.assign(Object.create(null), persistedFieldColors);
      globalThis.IRISFieldColorUpdatedAt = Object.assign(Object.create(null), persistedFieldColorUpdatedAt);
      return persistedFieldColors;
    })
    .catch(error => {
      console.warn('Field colors are temporarily unavailable:', error);
      globalThis.IRISFieldColors = globalThis.IRISFieldColors || {};
      return globalThis.IRISFieldColors;
    });
  const root = document.getElementById('studioColorCustomizer');
  if (!root) return;

  const swatches = document.getElementById('studioColorSwatches');
  const sectionToggle = document.getElementById('studioColorSectionToggle');
  const colorContent = document.getElementById('studioColorContent');
  const colorCount = document.getElementById('studioColorCount');
  const pickerPanel = document.getElementById('studioColorPickerPanel');
  const picker = document.getElementById('studioColorPicker');
  const hexInput = document.getElementById('studioColorHex');
  const rgbInputs = ['R', 'G', 'B'].map(channel => document.getElementById(`studioColor${channel}`));
  const presets = document.getElementById('studioColorPresets');
  const applyAllToggle = document.getElementById('studioColorApplyAll');
  const managerModal = document.getElementById('studioFieldColorsModal');
  const managerList = document.getElementById('studioFieldColorsList');
  const saveFieldButton = document.getElementById('studioColorSaveField');
  let currentColors = [];
  let currentFields = [];
  let currentFieldKeys = [];
  let currentType = 'bar';
  let selectedIndex = 0;
  let colorSectionExpanded = false;
  let editingManagedField = null;
  const restorePersistedFieldColors = () => {
    globalThis.IRISFieldColors = Object.assign(Object.create(null), persistedFieldColors);
    globalThis.IRISFieldColorUpdatedAt = Object.assign(Object.create(null), persistedFieldColorUpdatedAt);
  };
  const updateSectionState = () => {
    const multiple = currentColors.length > 1;
    colorCount.textContent = `(${currentColors.length})`;
    sectionToggle.hidden = !multiple;
    sectionToggle.setAttribute('aria-expanded', String(multiple ? colorSectionExpanded : true));
    colorContent.hidden = multiple && !colorSectionExpanded;
  };
  sectionToggle.addEventListener('click', () => {
    colorSectionExpanded = !colorSectionExpanded;
    updateSectionState();
  });

  const isPie = () => ['pie', 'doughnut', 'nestedPie'].includes(currentType);
  const legacyColors = () => isPie()
    ? ['#009639', '#1E6031', '#E0A70D', '#3B82F6', '#8B5CF6', '#F59E0B', '#10B981', '#EF4444', '#38BDF8', '#F97316']
    : ['#009639'];

  const syncInputs = color => {
    if (!isValidChartColor(color)) return;
    const normalized = color.toUpperCase();
    hexInput.value = normalized;
    hexInput.setAttribute('aria-invalid', 'false');
    const values = normalized.slice(1).match(/.{2}/g).map(value => parseInt(value, 16));
    rgbInputs.forEach((input, index) => { input.value = String(values[index]); });
    picker.color = normalized;
  };

  const applyColors = () => {
    if (currentType === 'nestedPie') {
      ctx.api.updateStudioChart();
      window.IRIS_STUDIO_DIRTY = true;
      return;
    }
    const perCategory = isPie() || currentType === 'bar' || currentType === 'rankedBar';
    const chart = ctx.state.studioChartInstance;
    const currentSeries = chart?.getOption?.()?.series || [];
    const series = currentSeries.map((current, seriesIndex) => {
      if (perCategory) {
        const data = Array.isArray(current.data) ? current.data : [];
        const fields = data.map((point, index) => point?.name || currentFields[index] || `Field ${index + 1}`);
        return { ...current, data: buildColoredSeriesData(data, fields, currentColors) };
      }
      const color = currentColors[seriesIndex] || currentColors[0];
      return {
        ...current,
        itemStyle: { ...(current.itemStyle || {}), color },
        lineStyle: currentType === 'line' ? { ...(current.lineStyle || {}), color } : current.lineStyle
      };
    });
    chart?.setOption({ color: [...currentColors], series }, { notMerge: false });
    window.IRIS_STUDIO_DIRTY = true;
  };

  const renderSwatches = () => {
    swatches.replaceChildren();
    currentColors.forEach((color, index) => {
      const row = document.createElement('div');
      row.className = 'studio-color-field-row';
      const button = document.createElement('button');
      const label = currentFields[index] || `Field ${index + 1}`;
      button.type = 'button';
      button.className = 'studio-color-swatch';
      button.style.setProperty('--studio-swatch-color', color);
      button.setAttribute('aria-label', `Edit color for ${label}`);
      button.setAttribute('aria-pressed', String(index === selectedIndex));
      button.title = `${label}: ${color}`;
      button.innerHTML = '<span aria-hidden="true"></span>';
      const name = document.createElement('span');
      name.className = 'studio-color-field-label';
      name.textContent = label;
      const fieldKey = normalizeFieldKey(currentFieldKeys[index] || label);
      const savedState = colorSaveStates.get(fieldKey);
      const isSaved = persistedFieldColors[fieldKey] === color.toUpperCase();
      const state = savedState?.color === color.toUpperCase()
        ? savedState.state
        : isSaved ? 'saved' : 'unsaved';
      const status = document.createElement('span');
      status.className = 'studio-color-field-status';
      status.dataset.state = state;
      status.setAttribute('role', 'status');
      status.setAttribute('aria-live', 'polite');
      status.textContent = ({ unsaved: 'Unsaved', saving: 'Saving…', saved: 'Saved', failed: 'Failed — retry' })[state] || 'Unsaved';
      if (savedState?.error && state === 'failed') status.title = savedState.error;
      const save = document.createElement('button');
      save.type = 'button';
      save.className = 'studio-color-field-save';
      save.textContent = 'Save';
      save.setAttribute('aria-label', `Save color for ${label}`);
      save.disabled = state === 'saving' || state === 'saved';
      save.addEventListener('click', () => saveFieldColor(index));
      button.addEventListener('click', () => {
        if (selectedIndex === index && !editingManagedField && !pickerPanel.hidden) {
          pickerPanel.hidden = true;
          selectedIndex = -1;
          renderSwatches();
          return;
        }
        selectedIndex = index;
        editingManagedField = null;
        saveFieldButton.hidden = true;
        pickerPanel.hidden = false;
        syncInputs(currentColors[index]);
        renderSwatches();
      });
      row.append(button, name, status, save);
      swatches.appendChild(row);
    });
  };

  const applyColor = (index, color) => {
    if (!isValidChartColor(color)) return;
    const normalized = color.toUpperCase();
    if (editingManagedField) {
      editingManagedField.color = normalized;
      const currentIndex = currentFieldKeys.findIndex(field => normalizeFieldKey(field) === editingManagedField.field_key);
      if (currentIndex >= 0) {
        currentColors[currentIndex] = normalized;
        renderSwatches();
        applyColors();
      }
      saveFieldButton.hidden = false;
      syncInputs(normalized);
      return;
    }
    if (index < 0 || index >= currentColors.length) return;
    currentColors[index] = normalized;
    ctx.state.studioChartColors = [...currentColors];
    const fieldKey = normalizeFieldKey(currentFieldKeys[index] || currentFields[index]);
    colorSaveStates.set(fieldKey, { state: 'unsaved', color: normalized });
    ctx.state.studioChartOverrides = [...currentColors];
    syncInputs(currentColors[index]);
    renderSwatches();
    applyColors();
  };

  async function saveFieldColor(index) {
    if (index < 0 || index >= currentColors.length) return;
    const fieldKey = normalizeFieldKey(currentFieldKeys[index] || currentFields[index]);
    const label = currentFieldKeys[index] || currentFields[index];
    const color = currentColors[index].toUpperCase();
    colorSaveStates.set(fieldKey, { state: 'saving', color });
    renderSwatches();
    try {
      const saved = await ctx.dbManager.saveFieldColor(fieldKey, label, color);
      persistedFieldColors[fieldKey] = color;
      if (saved.updated_at) persistedFieldColorUpdatedAt[fieldKey] = saved.updated_at;
      restorePersistedFieldColors();
      colorSaveStates.set(fieldKey, { state: 'saved', color });
      const allCurrentColorsSaved = currentColors.every((currentColor, currentIndex) => {
        const currentKey = normalizeFieldKey(currentFieldKeys[currentIndex] || currentFields[currentIndex]);
        return persistedFieldColors[currentKey] === currentColor.toUpperCase();
      });
      if (applyAllToggle.checked && allCurrentColorsSaved) ctx.state.studioChartOverrides = null;
      try {
        ctx.api.updateStudioChart();
      } catch (error) {
        alert(`Color saved, but the chart preview could not refresh: ${error.message || error}`);
      }
    } catch (error) {
      colorSaveStates.set(fieldKey, { state: 'failed', color, error: error.message || String(error) });
      alert(`Unable to save color for "${label}": ${error.message || error}. The color is unchanged; use Save to retry.`);
    } finally {
      renderSwatches();
    }
  }

  const renderPresets = () => {
    presets.replaceChildren();
    DEFAULT_CHART_COLORS.forEach(color => {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'studio-color-preset';
      button.style.setProperty('--studio-swatch-color', color);
      button.setAttribute('aria-label', `Use preset ${color}`);
      button.title = color;
      button.addEventListener('click', () => applyColor(selectedIndex, color));
      presets.appendChild(button);
    });
  };

  ctx.api.renderStudioColorCustomizer = ({ chartType, labels, colorKeys = labels, colors, overrides }) => {
    currentType = chartType;
    currentFields = labels.map((label, index) => String(label || `Field ${index + 1}`));
    currentFieldKeys = colorKeys.map((key, index) => String(key || currentFields[index]));
    currentColors = resolveFieldColors(currentFieldKeys, {
      chartColors: overrides,
      fieldColors: globalThis.IRISFieldColors || {},
      fieldColorUpdatedAt: globalThis.IRISFieldColorUpdatedAt || {},
      chartUpdatedAt: ctx.state.studioActiveGraphUpdatedAt,
      chartColorsOverrideShared: Array.isArray(overrides),
      legacyColors: legacyColors(),
      defaultColors: DEFAULT_CHART_COLORS
    });
    ctx.state.studioChartColors = [...currentColors];
    selectedIndex = Math.min(selectedIndex, currentColors.length - 1);
    root.hidden = currentColors.length === 0;
    updateSectionState();
    if (!currentColors.length) return;
    renderSwatches();
    renderPresets();
    if (!pickerPanel.hidden) syncInputs(currentColors[selectedIndex]);
  };

  ctx.api.resetStudioColorChanges = () => {
    restorePersistedFieldColors();
    colorSaveStates.clear();
    editingManagedField = null;
    saveFieldButton.hidden = true;
    ctx.state.studioChartOverrides = null;
  };

  const renderManagerList = async () => {
    managerList.replaceChildren();
    const rows = await ctx.dbManager.getFieldColorRows();
    if (!rows.length) {
      const empty = document.createElement('p');
      empty.className = 'text-sm text-gray-500 dark:text-gray-300';
      empty.textContent = 'No shared field colors have been saved.';
      managerList.appendChild(empty);
      return;
    }
    rows.forEach(field => {
      const row = document.createElement('div');
      row.className = 'studio-managed-color-row';
      const swatch = document.createElement('span');
      swatch.className = 'studio-managed-color-swatch';
      swatch.style.setProperty('--studio-swatch-color', field.color);
      const details = document.createElement('span');
      details.className = 'studio-managed-color-name';
      details.textContent = field.label;
      details.title = field.field_key;
      const edit = document.createElement('button');
      edit.type = 'button';
      edit.className = 'archive-load-button';
      edit.textContent = 'Edit';
      edit.addEventListener('click', () => {
        if (editingManagedField?.field_key === field.field_key && !pickerPanel.hidden) {
          pickerPanel.hidden = true;
          editingManagedField = null;
          selectedIndex = -1;
          saveFieldButton.hidden = true;
          renderSwatches();
          managerModal.classList.remove('active');
          managerModal.setAttribute('aria-hidden', 'true');
          return;
        }
        editingManagedField = { field_key: field.field_key, label: field.label, color: field.color };
        selectedIndex = Math.max(0, currentFields.findIndex(name => normalizeFieldKey(name) === field.field_key));
        root.hidden = false;
        pickerPanel.hidden = false;
        managerModal.classList.remove('active');
        managerModal.setAttribute('aria-hidden', 'true');
        saveFieldButton.hidden = true;
        syncInputs(field.color);
      });
      const remove = document.createElement('button');
      remove.type = 'button';
      remove.className = 'export-cancel-button';
      remove.textContent = 'Delete / Reset';
      remove.addEventListener('click', async () => {
        if (!confirm(`Remove the shared color for "${field.label}"?`)) return;
        remove.disabled = true;
        try {
          await ctx.dbManager.deleteFieldColor(field.field_key);
          delete persistedFieldColors[field.field_key];
          delete persistedFieldColorUpdatedAt[field.field_key];
          restorePersistedFieldColors();
          await renderManagerList();
          ctx.api.updateStudioChart();
        } catch (error) {
          alert(`Unable to remove field color: ${error.message || error}`);
          remove.disabled = false;
        }
      });
      row.append(swatch, details, edit, remove);
      managerList.appendChild(row);
    });
  };

  document.getElementById('studioManageFieldColors').addEventListener('click', async () => {
    try {
      await renderManagerList();
      managerModal.classList.add('active');
      managerModal.setAttribute('aria-hidden', 'false');
    } catch (error) {
      alert(`Unable to load field colors: ${error.message}`);
    }
  });
  document.getElementById('studioCloseFieldColors').addEventListener('click', () => {
    managerModal.classList.remove('active');
    managerModal.setAttribute('aria-hidden', 'true');
  });
  managerModal.addEventListener('click', event => {
    if (event.target === managerModal) {
      managerModal.classList.remove('active');
      managerModal.setAttribute('aria-hidden', 'true');
    }
  });
  saveFieldButton.addEventListener('click', async () => {
    if (!editingManagedField || !isValidChartColor(editingManagedField.color)) return;
    if (saveFieldButton.disabled) return;
    saveFieldButton.disabled = true;
    try {
      const saved = await ctx.dbManager.saveFieldColor(editingManagedField.field_key, editingManagedField.label, editingManagedField.color);
      persistedFieldColors[editingManagedField.field_key] = editingManagedField.color.toUpperCase();
      if (saved.updated_at) persistedFieldColorUpdatedAt[editingManagedField.field_key] = saved.updated_at;
      restorePersistedFieldColors();
      editingManagedField = null;
      saveFieldButton.hidden = true;
      ctx.state.studioChartOverrides = null;
      ctx.api.updateStudioChart();
      await renderManagerList();
    } catch (error) {
      alert(`Unable to save field color: ${error.message}`);
    } finally {
      saveFieldButton.disabled = false;
    }
  });

  applyAllToggle.addEventListener('change', () => {
    editingManagedField = null;
    saveFieldButton.hidden = true;
    if (applyAllToggle.checked) {
      const allColorsSaved = currentColors.every((color, index) => {
        const fieldKey = normalizeFieldKey(currentFieldKeys[index] || currentFields[index]);
        return persistedFieldColors[fieldKey] === color.toUpperCase();
      });
      if (allColorsSaved) ctx.state.studioChartOverrides = null;
    }
  });

  picker.addEventListener('color-changed', event => applyColor(selectedIndex, event.detail.value));
  hexInput.addEventListener('input', () => {
    const color = hexInput.value.trim();
    const valid = isValidChartColor(color);
    hexInput.setAttribute('aria-invalid', String(!valid));
    if (valid) applyColor(selectedIndex, color);
  });
  hexInput.addEventListener('blur', () => {
    if (!isValidChartColor(hexInput.value.trim())) syncInputs(currentColors[selectedIndex]);
  });
  rgbInputs.forEach(input => input.addEventListener('input', () => {
    const values = rgbInputs.map(field => Number(field.value));
    if (values.some(value => !Number.isInteger(value) || value < 0 || value > 255)) return;
    applyColor(selectedIndex, `#${values.map(value => value.toString(16).padStart(2, '0')).join('')}`);
  }));
  document.getElementById('studioColorReset').addEventListener('click', async () => {
    if (!confirm('Reset these fields to default colors and remove their saved shared colors? This affects all charts using them.')) return;
    const failed = [];
    for (let index = 0; index < currentFields.length; index += 1) {
      const fieldKey = normalizeFieldKey(currentFieldKeys[index] || currentFields[index]);
      if (!Object.prototype.hasOwnProperty.call(persistedFieldColors, fieldKey)) continue;
      try {
        await ctx.dbManager.deleteFieldColor(fieldKey);
        delete persistedFieldColors[fieldKey];
        delete persistedFieldColorUpdatedAt[fieldKey];
        colorSaveStates.delete(fieldKey);
      } catch (error) {
        failed.push(`${currentFields[index]}: ${error.message || error}`);
        colorSaveStates.set(fieldKey, { state: 'failed', color: currentColors[index], error: error.message || String(error) });
      }
    }
    restorePersistedFieldColors();
    if (failed.length) alert(`Some saved colors could not be reset:\n${failed.join('\n')}`);
    ctx.state.studioChartOverrides = applyAllToggle.checked
      ? null
      : currentFields.map((_, index) => legacyColors()[index] || DEFAULT_CHART_COLORS[index % DEFAULT_CHART_COLORS.length]);
    currentColors = resolveFieldColors(currentFieldKeys, {
      chartColors: ctx.state.studioChartOverrides,
      fieldColors: globalThis.IRISFieldColors || {},
      fieldColorUpdatedAt: globalThis.IRISFieldColorUpdatedAt || {},
      chartUpdatedAt: ctx.state.studioActiveGraphUpdatedAt,
      legacyColors: legacyColors(),
      defaultColors: DEFAULT_CHART_COLORS
    });
    ctx.state.studioChartColors = [...currentColors];
    if (currentColors.length) syncInputs(currentColors[selectedIndex]);
    renderSwatches();
    applyColors();
  });
}