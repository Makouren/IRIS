import { $, escapeHtml, parseEditableValue } from '../utils/helpers.js';
import { renderStudioChart } from './chartEngine.js?v=rank-axis-render-sync-20261004';
import { initStudioColorCustomizer } from './studioColorCustomizer.js?v=echarts-six-chart-types-1';

export function initStudioWorkbench(ctx) {
  initStudioColorCustomizer(ctx);
  ctx.api.renderStudioWorkbench = record => {
    if (!record) return;
    ['studioBtnSave', 'studioBtnApprove'].forEach(id => { const button = $(id); if (button) button.disabled = false; });
    if (ctx.state.studioChartColorRecordId !== record.id) {
      ctx.api.resetStudioColorChanges?.();
      ctx.state.studioChartColorRecordId = record.id;
      ctx.state.studioChartColors = null;
      ctx.state.studioChartOverrides = null;
    }
    ctx.api.ensureTableDataStructure(record);
    if ($('studioActiveFileName')) $('studioActiveFileName').textContent = `${record.fileName} (${(record.fileType || '').toUpperCase()})`;
    if ($('studioDocTypeInput')) $('studioDocTypeInput').value = record.docType || 'General Institutional Data';
    if ($('studioStatusSelect')) $('studioStatusSelect').value = record.status || 'Pending Review';
    if ($('studioNotesInput')) $('studioNotesInput').value = record.adminNotes || '';
    $('studioChartTitleInput')?.removeAttribute('data-customized');
    ctx.api.renderDocumentWindow(record);
    const sheet = ctx.api.getStudioActiveSheet(record);
    ['studioBtnAddField', 'studioBtnAddRow'].forEach(id => { const button = $(id); if (button) button.disabled = !sheet; });
    if (sheet) {
      ctx.api.updateFieldSelectOptions(sheet.data);
      ctx.api.renderStudioTableGrid(record);
      ctx.api.renderStudioChart(record);
    } else {
      const table = $('studioTableContainer');
      if (table) table.textContent = 'No parsed spreadsheet data is available for this record. Re-upload the file to extract its contents.';
      ['studioCategoryCol', 'studioValueCol'].forEach(id => {
        const select = $(id);
        if (select) { select.replaceChildren(); select.disabled = true; }
      });
      ctx.api.renderStudioChart(record);
    }
  };
  ctx.api.updateFieldSelectOptions = sheet => {
    if (!sheet?.headers) return;
    const inferred = window.ChartMapping.inferColumns(sheet.headers, sheet.rows || []);
    const chartType = $('studioChartTypeSelect')?.value || 'bar';
    const circular = ['pie', 'doughnut', 'nestedPie'].includes(chartType);
    const groupWrap = $('studioGroupFieldWrapper');
    const groupSelect = $('studioGroupField');
    const seriesWrap = $('studioSeriesFieldWrapper');
    const seriesSelect = $('studioSeriesField');
    const reverseWrap = $('studioReverseOrderWrapper');
    if (seriesWrap) seriesWrap.style.display = chartType === 'stackedArea' ? 'flex' : 'none';
    if (reverseWrap) reverseWrap.style.display = ['line', 'stackedArea', 'bar'].includes(chartType) ? 'flex' : 'none';
    if (groupWrap) groupWrap.style.display = chartType === 'nestedPie' ? 'flex' : 'none';
    const category = $('studioCategoryCol'); const value = $('studioValueCol'); const previousCategory = category?.value; const previousValue = value?.value;
    if (category) { category.disabled = false; category.innerHTML = sheet.headers.map((header, index) => `<option value="${index}">${escapeHtml(header || `Column ${index + 1}`)}</option>`).join(''); category.value = previousCategory !== '' && sheet.headers[Number(previousCategory)] ? previousCategory : String(inferred.labelColumn); }
    if (value) { value.disabled = false; value.innerHTML = sheet.headers.map((header, index) => `<option value="${index}">${escapeHtml(header || `Column ${index + 1}`)}${inferred.columnTypes?.[index] === 'numeric' ? ' <i class="fa-solid fa-check" aria-hidden="true"></i>' : inferred.columnTypes?.[index] === 'text' ? ' (text)' : ''}</option>`).join(''); value.value = previousValue !== '' && sheet.headers[Number(previousValue)] ? previousValue : String(inferred.valueColumn); }
    if (seriesSelect) {
      const previousSeries = ctx.state.studioChartConfig?.seriesField ?? seriesSelect.value;
      const excluded = new Set([Number(category?.value), Number(value?.value)]);
      const candidates = sheet.headers.map((header, index) => ({ header, index })).filter(item => !excluded.has(item.index));
      seriesSelect.innerHTML = `<option value="">No grouping (single area)</option>${candidates.map(item => `<option value="${item.index}">${escapeHtml(item.header || `Column ${item.index + 1}`)}</option>`).join('')}`;
      seriesSelect.value = candidates.some(item => String(item.index) === String(previousSeries)) ? String(previousSeries) : '';
    }
    if (groupSelect) {
      const previousGroup = ctx.state.studioChartConfig?.groupField ?? groupSelect.value;
      groupSelect.innerHTML = `<option value="">Choose group field</option>${sheet.headers.map((header, index) => `<option value="${index}">${escapeHtml(header || `Column ${index + 1}`)}</option>`).join('')}`;
      if (previousGroup !== '' && sheet.headers[Number(previousGroup)] !== undefined) groupSelect.value = String(previousGroup);
      else groupSelect.value = '';
    }
    if ($('studioCategoryLabel')) $('studioCategoryLabel').textContent = circular ? 'Labels:' : 'Category (X-axis):';
    if ($('studioValueLabel')) $('studioValueLabel').textContent = circular ? 'Value (single):' : 'Value (Y-axis):';
    const filter = $('studioFilterField'); const previousFilter = filter?.value;
    if (filter) { filter.innerHTML = '<option value="all">All selected data</option><option value="context">Context / label only</option><option value="value">Metric / value only</option>'; sheet.headers.forEach((header, index) => { const option = document.createElement('option'); option.value = `column:${index}`; option.textContent = `${header || `Column ${index + 1}`} only`; filter.appendChild(option); }); filter.value = [...filter.options].some(option => option.value === previousFilter) ? previousFilter : 'all'; }
  };
  ctx.api.renderStudioChart = record => renderStudioChart(record, {
    ctx,
    state: ctx.state,
    getStudioActiveSheet: ctx.api.getStudioActiveSheet,
    elements: {
      canvas: $('studioChartCanvas'),
      emptyState: $('studioChartEmptyState'),
      emptyMsg: $('studioChartEmptyMsg'),
      warning: $('studioFieldWarning'),
      typeSelect: $('studioChartTypeSelect'),
      titleInput: $('studioChartTitleInput'),
      subtitle: $('studioChartSubtitleDisplay'),
      categorySelect: $('studioCategoryCol'),
      valueSelect: $('studioValueCol'),
      groupFieldSelect: $('studioGroupField'),
      seriesFieldSelect: $('studioSeriesField'),
      valuePrecision: $('studioValuePrecisionSelect'),
      yearSelect: $('studioYearSelect'),
      reverseOrder: $('studioReverseOrder'),
      filterField: $('studioFilterField'),
      filterOperator: $('studioFilterOperator'),
      filterValue: $('studioFilterValue'),
      filterUpperValue: $('studioFilterUpperValue'),
      sortOrder: $('studioSortOrder'),
      rowLimit: $('studioRowLimit'),
      groupDuplicates: $('studioGroupDuplicates')
    }
  });
  ctx.api.updateStudioChart = () => ctx.state.studioActiveRecord && ctx.api.renderStudioChart(ctx.state.studioActiveRecord);
  ctx.api.saveStudioData = async approve => {
    const record = ctx.state.studioActiveRecord; if (!record) return;
    const info = ctx.api.getStudioActiveSheet(record); const sheet = info?.data;
    document.querySelectorAll('.studio-cell-input').forEach(input => { const row = Number(input.dataset.row); const column = Number(input.dataset.col); if (sheet?.rows?.[row]) sheet.rows[row][column] = parseEditableValue(input.value); });
    let savedChart = null; const chart = ctx.state.studioChartInstance;
    if (chart) {
      const options = chart.getOption();
      const config = ctx.state.studioChartConfig || {};
      const current = window.ChartData.serializeChartState(options, config);
      const selectedType = $('studioChartTypeSelect')?.value || 'bar';
      const sheet = ctx.api.getStudioActiveSheet(record)?.data;
      const selectedColumn = id => {
        const raw = $(id)?.value;
        const index = raw === '' || raw === undefined || raw === null ? -1 : Number(raw);
        return Number.isInteger(index) && index >= 0 && index < (sheet?.headers?.length || 0) ? index : null;
      };
      const categoryField = selectedColumn('studioCategoryCol');
      const valueField = selectedColumn('studioValueCol');
      const groupField = selectedColumn('studioGroupField');
      const seriesField = selectedColumn('studioSeriesField');
      const upperFilterValue = $('studioFilterUpperValue')?.value;
      const rowLimit = Number($('studioRowLimit')?.value ?? 30);
      const irisConfig = {
        ...config,
        type: selectedType,
        categoryField,
        valueField,
        groupField,
        seriesField,
        precision: Number($('studioValuePrecisionSelect')?.value ?? 2),
        rawValues: current.values,
        series: current.series,
        rankSemantic: config.rankSemantic === true,
        reverseOrder: Boolean($('studioReverseOrder')?.checked),
        selectedYear: $('studioYearSelect')?.value || config.selectedYear || 'all',
        filterField: $('studioFilterField')?.value || 'all',
        filterOperator: $('studioFilterOperator')?.value || 'all',
        filterValue: $('studioFilterValue')?.value || '',
        filterUpperValue: upperFilterValue !== '' && Number.isFinite(Number(upperFilterValue)) ? Number(upperFilterValue) : null,
        sortOrder: $('studioSortOrder')?.value || 'source',
        rowLimit: Number.isFinite(rowLimit) ? Math.max(1, Math.min(100, rowLimit)) : 30,
        groupDuplicates: $('studioGroupDuplicates')?.checked !== false,
        applyColorsToAllCharts: $('studioColorApplyAll')?.checked !== false
      };
      savedChart = {
        record_id: record.id,
        title: $('studioChartTitleInput')?.value || 'Observatory Draft',
        chart_type: selectedType,
        colors: $('studioColorApplyAll')?.checked ? null : ctx.state.studioChartOverrides,
        orientation: config.orientation || 'vertical',
        rankSemantic: config.rankSemantic === true,
        valueAxisMin: config.valueAxisMin,
        valueAxisMax: config.valueAxisMax,
        labels: current.labels,
        values_data: current.values,
        chart_data: { ...options, irisConfig }
      };
    }
    if (!savedChart && !approve) throw new Error('Render a chart before saving it to Saved Graphs.');
    let graphIdToUpdate = ctx.state.studioActiveGraphId;
    let graphIsPublished = Boolean(ctx.state.studioActiveGraphPublished);
    if (savedChart && !graphIdToUpdate) {
      const existingGraphs = await ctx.dbManager.getGraphsByRecord(record.id);
      const matchingGraph = existingGraphs.find(graph => String(graph.title || 'Saved Chart') === String(savedChart.title || 'Saved Chart'));
      if (matchingGraph) {
        graphIdToUpdate = matchingGraph.id;
        graphIsPublished = Boolean(matchingGraph.is_published);
      }
    }
    if (savedChart && graphIdToUpdate) {
      try { graphIsPublished = Boolean((await ctx.dbManager.getGraphById(graphIdToUpdate)).is_published); } catch (error) {}
    }
    if (savedChart && graphIdToUpdate && graphIsPublished && !window.confirm('This graph is currently published. Saving will update the live dashboard immediately. Continue?')) return;

    const updated = await ctx.dbManager.updateRecord(record.id, { docType: $('studioDocTypeInput')?.value.trim() || record.docType, status: approve ? 'Approved' : ($('studioStatusSelect')?.value || record.status), adminNotes: $('studioNotesInput')?.value.trim() || record.adminNotes, extractedData: record.extractedData, graphDrafts: [] });
    if (savedChart) {
      savedChart.is_published = approve === true;
      if (graphIdToUpdate) {
        if (approve !== true) delete savedChart.is_published;
        const updatedGraph = await ctx.dbManager.updateGraph(graphIdToUpdate, savedChart);
        ctx.state.studioActiveGraphId = String(updatedGraph.id);
        ctx.state.studioActiveGraphPublished = Boolean(updatedGraph.is_published);
        ctx.state.studioActiveGraphUpdatedAt = updatedGraph.updated_at || updatedGraph.updatedAt || new Date().toISOString();
      } else {
        const createdGraph = await ctx.dbManager.saveGraph(savedChart);
        ctx.state.studioActiveGraphId = String(createdGraph.id);
        ctx.state.studioActiveGraphPublished = Boolean(createdGraph.is_published);
        ctx.state.studioActiveGraphUpdatedAt = createdGraph.updated_at || createdGraph.updatedAt || new Date().toISOString();
      }
    }
    ctx.state.studioActiveRecord = { ...record, ...updated };
    if (ctx.state.activeScan?.id === record.id) { ctx.state.activeScan = { ...ctx.state.activeScan, ...updated }; await ctx.api.renderOverviewTab(ctx.state.activeScan); ctx.api.renderViewerTab(ctx.state.activeScan); await ctx.api.renderGraphsTab(ctx.state.activeScan); }
    window.IRIS_STUDIO_DIRTY = false;
    await ctx.api.renderAdminPortal();
    let savedGraphsRefreshError = '';
    if (savedChart && ctx.api.renderSavedGraphsTab) {
      try { await ctx.api.renderSavedGraphsTab(); }
      catch (error) { savedGraphsRefreshError = ` Saved Graphs could not refresh: ${error.message || error}`; }
    }
    alert(savedChart
      ? `Graph "${savedChart.title}" and its dataset were saved.${approve ? ' The graph is published.' : ''}${savedGraphsRefreshError}`
      : `Dataset '${record.fileName}' successfully saved to database!${approve ? ' (Published)' : ''}`);
  };
  ['studioChartTypeSelect', 'studioCategoryCol', 'studioValueCol', 'studioValuePrecisionSelect', 'studioGroupField', 'studioSeriesField', 'studioYearSelect', 'studioReverseOrder', 'studioFilterField', 'studioFilterOperator', 'studioFilterValue', 'studioFilterUpperValue', 'studioSortOrder', 'studioRowLimit', 'studioGroupDuplicates'].forEach(id => $(id)?.addEventListener('change', () => { if (['studioChartTypeSelect', 'studioCategoryCol', 'studioValueCol'].includes(id)) { const sheet = ctx.api.getStudioActiveSheet(ctx.state.studioActiveRecord)?.data; if (sheet) ctx.api.updateFieldSelectOptions(sheet); } window.IRIS_STUDIO_DIRTY = true; ctx.api.updateStudioChart(); }));
  $('studioFilterValue')?.addEventListener('input', () => { window.IRIS_STUDIO_DIRTY = true; ctx.api.updateStudioChart(); }); $('studioRowLimit')?.addEventListener('input', () => { window.IRIS_STUDIO_DIRTY = true; ctx.api.updateStudioChart(); }); $('studioChartTitleInput')?.addEventListener('input', event => { window.IRIS_STUDIO_DIRTY = true; event.target.setAttribute('data-customized', 'true'); });
  ['studioDocTypeInput', 'studioStatusSelect', 'studioNotesInput'].forEach(id => $(id)?.addEventListener('change', () => { window.IRIS_STUDIO_DIRTY = true; }));
  document.addEventListener('input', event => { if (event.target?.classList?.contains('studio-cell-input') || event.target?.classList?.contains('header-rename-input')) window.IRIS_STUDIO_DIRTY = true; });
  const updateFilterInputs = () => { const operator = $('studioFilterOperator'); const upper = $('studioFilterUpperValue'); if (upper) upper.style.display = operator?.value === 'between' ? 'inline-block' : 'none'; };
  $('studioFilterOperator')?.addEventListener('change', updateFilterInputs); updateFilterInputs();
}
