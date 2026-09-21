import { $, parseEditableValue } from '../utils/helpers.js';
import { renderStudioChart } from './chartEngine.js';

export function initStudioWorkbench(ctx) {
  ctx.api.renderStudioWorkbench = record => {
    if (!record) return;
    ctx.api.ensureTableDataStructure(record);
    $('studioActiveFileName').textContent = `${record.fileName} (${(record.fileType || '').toUpperCase()})`;
    $('studioDocTypeInput').value = record.docType || 'General Institutional Data';
    $('studioStatusSelect').value = record.status || 'Pending Review';
    $('studioNotesInput').value = record.adminNotes || '';
    $('studioChartTitleInput').removeAttribute('data-customized');
    ctx.api.renderDocumentWindow(record);
    const sheet = ctx.api.getStudioActiveSheet(record);
    if (sheet) { ctx.api.updateFieldSelectOptions(sheet.data); ctx.api.renderStudioTableGrid(record); ctx.api.renderStudioChart(record); }
  };
  ctx.api.updateFieldSelectOptions = sheet => {
    if (!sheet?.headers) return;
    const inferred = window.ChartMapping.inferColumns(sheet.headers, sheet.rows || []);
    const circular = ['pie', 'doughnut', 'polarArea'].includes($('studioChartTypeSelect')?.value || 'bar');
    const category = $('studioCategoryCol'); const value = $('studioValueCol'); const previousCategory = category?.value; const previousValue = value?.value;
    if (category) { category.innerHTML = sheet.headers.map((header, index) => `<option value="${index}">${header || `Column ${index + 1}`}</option>`).join(''); category.value = previousCategory !== '' && sheet.headers[Number(previousCategory)] ? previousCategory : String(inferred.labelColumn); }
    if (value) { value.innerHTML = sheet.headers.map((header, index) => `<option value="${index}">${header || `Column ${index + 1}`}${inferred.columnTypes?.[index] === 'numeric' ? ' ✓' : inferred.columnTypes?.[index] === 'text' ? ' (text)' : ''}</option>`).join(''); value.value = previousValue !== '' && sheet.headers[Number(previousValue)] ? previousValue : String(inferred.valueColumn); }
    $('studioCategoryLabel').textContent = circular ? 'Labels:' : 'Category (X-axis):'; $('studioValueLabel').textContent = circular ? 'Value (single):' : 'Value (Y-axis):';
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
      filterField: $('studioFilterField'),
      filterOperator: $('studioFilterOperator'),
      filterValue: $('studioFilterValue'),
      filterUpperValue: $('studioFilterUpperValue'),
      sortOrder: $('studioSortOrder'),
      rowLimit: $('studioRowLimit'),
      groupDuplicates: $('studioGroupDuplicates'),
      reverseSortOrder: $('studioReverseSortOrder'),
      reverseValueAxis: $('studioReverseValueAxis')
    }
  });
  ctx.api.updateStudioChart = () => ctx.state.studioActiveRecord && ctx.api.renderStudioChart(ctx.state.studioActiveRecord);
  ctx.api.saveStudioData = async approve => {
    const record = ctx.state.studioActiveRecord; if (!record) return;
    const info = ctx.api.getStudioActiveSheet(record); const sheet = info?.data;
    document.querySelectorAll('.studio-cell-input').forEach(input => { const row = Number(input.dataset.row); const column = Number(input.dataset.col); if (sheet?.rows?.[row]) sheet.rows[row][column] = parseEditableValue(input.value); });
    let savedChart = null; const chart = ctx.state.studioChartInstance;
    if (chart) { const options = chart.getOption(); const current = window.ChartData.serializeChartState(options, ctx.state.studioChartConfig || {}); savedChart = { record_id: record.id, title: $('studioChartTitleInput')?.value || 'Observatory Draft', chart_type: $('studioChartTypeSelect')?.value || 'bar', orientation: ctx.state.studioChartConfig?.orientation || 'vertical', rankSemantic: ctx.state.studioChartConfig?.rankSemantic === true, rankValueMin: ctx.state.studioChartConfig?.rankValueMin, rankValueMax: ctx.state.studioChartConfig?.rankValueMax, valueAxisReversed: ctx.state.studioChartConfig?.valueAxisReversed === true, valueAxisMin: ctx.state.studioChartConfig?.valueAxisMin, valueAxisMax: ctx.state.studioChartConfig?.valueAxisMax, labels: current.labels, values_data: current.values }; }
    const updated = await ctx.dbManager.updateRecord(record.id, { docType: $('studioDocTypeInput')?.value.trim() || record.docType, status: approve ? 'Approved' : ($('studioStatusSelect')?.value || record.status), adminNotes: $('studioNotesInput')?.value.trim() || record.adminNotes, extractedData: record.extractedData, graphDrafts: [] });
    if (savedChart) await ctx.dbManager.saveGraph(savedChart);
    ctx.state.studioActiveRecord = { ...record, ...updated };
    if (ctx.state.activeScan?.id === record.id) { ctx.state.activeScan = { ...ctx.state.activeScan, ...updated }; await ctx.api.renderOverviewTab(ctx.state.activeScan); ctx.api.renderViewerTab(ctx.state.activeScan); await ctx.api.renderGraphsTab(ctx.state.activeScan); }
    await ctx.api.renderAdminPortal(); alert(`Dataset '${record.fileName}' successfully saved to database!${approve ? ' (Approved for Observatory)' : ''}`);
  };
  ['studioChartTypeSelect', 'studioCategoryCol', 'studioValueCol', 'studioFilterField', 'studioFilterOperator', 'studioFilterValue', 'studioFilterUpperValue', 'studioSortOrder', 'studioRowLimit', 'studioGroupDuplicates'].forEach(id => $(id)?.addEventListener('change', () => { if (id === 'studioChartTypeSelect') { const sheet = ctx.api.getStudioActiveSheet(ctx.state.studioActiveRecord)?.data; if (sheet) ctx.api.updateFieldSelectOptions(sheet); } ctx.api.updateStudioChart(); }));
  $('studioFilterValue')?.addEventListener('input', ctx.api.updateStudioChart); $('studioRowLimit')?.addEventListener('input', ctx.api.updateStudioChart); $('studioChartTitleInput')?.addEventListener('input', event => event.target.setAttribute('data-customized', 'true'));
  $('studioReverseSortOrder')?.addEventListener('click', event => { const active = event.currentTarget.getAttribute('aria-pressed') === 'true'; event.currentTarget.setAttribute('aria-pressed', String(!active)); ctx.api.updateStudioChart(); }); $('studioReverseValueAxis')?.addEventListener('click', event => { const active = event.currentTarget.getAttribute('aria-pressed') === 'true'; event.currentTarget.setAttribute('aria-pressed', String(!active)); ctx.api.updateStudioChart(); });
  const updateFilterInputs = () => { const operator = $('studioFilterOperator'); const upper = $('studioFilterUpperValue'); if (upper) upper.style.display = operator?.value === 'between' ? 'inline-block' : 'none'; };
  $('studioFilterOperator')?.addEventListener('change', updateFilterInputs); updateFilterInputs();
}
