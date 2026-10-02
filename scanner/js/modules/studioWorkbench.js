import { $, escapeHtml, parseEditableValue } from '../utils/helpers.js';
import { renderStudioChart } from './chartEngine.js?v=remove-rose-20261001';
import { initStudioColorCustomizer } from './studioColorCustomizer.js?v=remove-rose-20261001';

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
    const ranked = chartType === 'rankedBar';
    const groupWrap = $('studioGroupFieldWrapper');
    const groupSelect = $('studioGroupField');
    const yearWrap = $('studioRankedYearWrapper');
    const reverseWrap = $('studioRankedReverseOrderWrapper');
    if (yearWrap) yearWrap.style.display = ranked ? 'flex' : 'none';
    if (reverseWrap) reverseWrap.style.display = ranked ? 'flex' : 'none';
    if (groupWrap) groupWrap.style.display = chartType === 'nestedPie' ? 'flex' : 'none';
    const category = $('studioCategoryCol'); const value = $('studioValueCol'); const previousCategory = category?.value; const previousValue = value?.value;
    if (category) { category.disabled = false; category.innerHTML = sheet.headers.map((header, index) => `<option value="${index}">${escapeHtml(header || `Column ${index + 1}`)}</option>`).join(''); category.value = previousCategory !== '' && sheet.headers[Number(previousCategory)] ? previousCategory : String(inferred.labelColumn); }
    if (value) { value.disabled = false; value.innerHTML = sheet.headers.map((header, index) => `<option value="${index}">${escapeHtml(header || `Column ${index + 1}`)}${inferred.columnTypes?.[index] === 'numeric' ? ' <i class="fa-solid fa-check" aria-hidden="true"></i>' : inferred.columnTypes?.[index] === 'text' ? ' (text)' : ''}</option>`).join(''); value.value = previousValue !== '' && sheet.headers[Number(previousValue)] ? previousValue : String(inferred.valueColumn); }
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
      valuePrecision: $('studioValuePrecisionSelect'),
      rankedYearSelect: $('studioRankedYearSelect'),
      rankedReverseOrder: $('studioRankedReverseOrder'),
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
      const yearColumn = selectedType === 'rankedBar' ? window.ChartMapping.detectYearColumn(sheet?.headers || [], sheet?.rows || []) : null;
      const categoryField = Number($('studioCategoryCol')?.value ?? -1);
      const valueField = Number($('studioValueCol')?.value ?? -1);
      const groupField = Number($('studioGroupField')?.value ?? -1);
      const upperFilterValue = $('studioFilterUpperValue')?.value;
      const rowLimit = Number($('studioRowLimit')?.value ?? 30);
      const irisConfig = {
        ...config,
        type: selectedType,
        categoryField: Number.isInteger(categoryField) && categoryField >= 0 ? categoryField : null,
        valueField: Number.isInteger(valueField) && valueField >= 0 ? valueField : null,
        groupField: Number.isInteger(groupField) && groupField >= 0 ? groupField : null,
        precision: Number($('studioValuePrecisionSelect')?.value ?? 2),
        rawValues: current.values,
        series: current.series,
        rankSemantic: config.rankSemantic === true,
        reverseOrder: Boolean(config.reverseOrder),
        selectedYear: $('studioRankedYearSelect')?.value || config.selectedYear || config.rankedYear || null,
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
        valueAxisReversed: Boolean(config.reverseOrder),
        rankSemantic: config.rankSemantic === true,
        rankValueMin: config.rankValueMin,
        rankValueMax: config.rankValueMax,
        valueAxisMin: config.valueAxisMin,
        valueAxisMax: config.valueAxisMax,
        labels: current.labels,
        values_data: current.values,
        chart_data: { ...options, irisConfig, rankedBar: { selectedYear: irisConfig.selectedYear, yearColumn, reverseOrder: irisConfig.reverseOrder, categoryField: irisConfig.categoryField, valueField: irisConfig.valueField, yearField: yearColumn, chartType: selectedType } }
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
      const savedMapping = savedChart.chart_data?.rankedBar || {};
      const categorySelection = $('studioCategoryCol')?.value;
      const valueSelection = $('studioValueCol')?.value;
      const hasColumnIndex = value => value !== undefined && value !== null && value !== '' && Number.isInteger(Number(value)) && Number(value) >= 0;
      savedChart.chart_data = {
        ...(savedChart.chart_data || {}),
        irisConfig: { ...(savedChart.chart_data?.irisConfig || {}), categoryField: hasColumnIndex(categorySelection) ? Number(categorySelection) : null, valueField: hasColumnIndex(valueSelection) ? Number(valueSelection) : null, groupField: hasColumnIndex($('studioGroupField')?.value) ? Number($('studioGroupField').value) : null },
        rankedBar: {
          selectedYear: ctx.state.studioChartConfig?.selectedYear ?? ctx.state.studioChartConfig?.rankedYear ?? null,
          yearColumn: ctx.state.studioChartConfig?.yearColumn ?? null,
          reverseOrder: Boolean(ctx.state.studioChartConfig?.reverseOrder),
          chartType: savedChart.chart_type || savedMapping.chartType || 'bar',
          categoryField: hasColumnIndex(categorySelection) ? Number(categorySelection) : (savedMapping.categoryField ?? null),
          valueField: hasColumnIndex(valueSelection) ? Number(valueSelection) : (savedMapping.valueField ?? null),
          yearField: ctx.state.studioChartConfig?.yearField ?? null
        }
      };
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
  ['studioChartTypeSelect', 'studioCategoryCol', 'studioValueCol', 'studioValuePrecisionSelect', 'studioGroupField', 'studioRankedYearSelect', 'studioRankedReverseOrder', 'studioFilterField', 'studioFilterOperator', 'studioFilterValue', 'studioFilterUpperValue', 'studioSortOrder', 'studioRowLimit', 'studioGroupDuplicates'].forEach(id => $(id)?.addEventListener('change', () => { if (id === 'studioChartTypeSelect') { const sheet = ctx.api.getStudioActiveSheet(ctx.state.studioActiveRecord)?.data; if (sheet) ctx.api.updateFieldSelectOptions(sheet); } window.IRIS_STUDIO_DIRTY = true; ctx.api.updateStudioChart(); }));
  $('studioFilterValue')?.addEventListener('input', () => { window.IRIS_STUDIO_DIRTY = true; ctx.api.updateStudioChart(); }); $('studioRowLimit')?.addEventListener('input', () => { window.IRIS_STUDIO_DIRTY = true; ctx.api.updateStudioChart(); }); $('studioChartTitleInput')?.addEventListener('input', event => { window.IRIS_STUDIO_DIRTY = true; event.target.setAttribute('data-customized', 'true'); });
  ['studioDocTypeInput', 'studioStatusSelect', 'studioNotesInput'].forEach(id => $(id)?.addEventListener('change', () => { window.IRIS_STUDIO_DIRTY = true; }));
  document.addEventListener('input', event => { if (event.target?.classList?.contains('studio-cell-input') || event.target?.classList?.contains('header-rename-input')) window.IRIS_STUDIO_DIRTY = true; });
  const updateFilterInputs = () => { const operator = $('studioFilterOperator'); const upper = $('studioFilterUpperValue'); if (upper) upper.style.display = operator?.value === 'between' ? 'inline-block' : 'none'; };
  $('studioFilterOperator')?.addEventListener('change', updateFilterInputs); updateFilterInputs();
}
