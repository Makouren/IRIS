import { $, escapeHtml } from '../utils/helpers.js';
import { createChart } from './chartEngine.js';

function getRecordName(graph, records) {
  const record = records.find(item => item.id === graph.record_id);
  return graph.source_file_name || record?.fileName || graph.record_id || 'Unknown file';
}

export function downloadText(payload) {
  const textContent = payload.text || payload.blob;
  const blob = new Blob([textContent], { type: 'text/plain' });
  const fileName = payload.fileName || 'iris_saved_graphs.txt';
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = fileName;
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  URL.revokeObjectURL(url);
}

export function showExportChoice(onChoice) {
  const modal = document.createElement('div');
  modal.style.cssText = 'position:fixed;inset:0;z-index:1000;display:flex;align-items:center;justify-content:center;background:rgba(15,23,42,.35);padding:1rem;';
  modal.innerHTML = `<div role="dialog" aria-modal="true" style="width:min(420px,100%);background:#fff;border:1px solid #D7E3DA;border-radius:10px;padding:1.25rem;box-shadow:0 20px 60px rgba(15,23,42,.2);"><h3 style="margin:0 0 .4rem;color:#146C36;">Export saved graph</h3><p style="margin:0 0 1rem;color:#64748B;font-size:.85rem;">Choose how to export the selected graph data.</p><div style="display:grid;gap:.55rem;"><button type="button" data-mode="database" class="btn-table-load-studio">Database Export <small>(write to live MySQL)</small></button><button type="button" data-mode="script" class="btn-table-load-studio">Export as Text File (.txt) <small>(download without database changes)</small></button><button type="button" data-mode="cancel" style="border:0;background:transparent;color:#64748B;cursor:pointer;padding:.5rem;">Cancel</button></div></div>`;
  document.body.appendChild(modal);
  modal.addEventListener('click', event => {
    const mode = event.target.closest('[data-mode]')?.dataset.mode;
    if (!mode) return;
    modal.remove();
    if (mode !== 'cancel') onChoice(mode);
  });
}

function buildTextExport(graphs) {
  const sqlString = value => `'${String(value ?? '').replace(/\\/g, '\\\\').replace(/'/g, "''")}'`;
  const sqlValue = value => {
    if (typeof value === 'number' && Number.isFinite(value)) return String(value);
    const text = String(value ?? '').trim();
    return /^[-+]?\d+(?:\.\d+)?$/.test(text) ? text : sqlString(value);
  };
  const tableName = title => {
    const sanitized = String(title || 'saved_graph')
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, '_')
      .replace(/^_+|_+$/g, '') || 'saved_graph';
    return `graph_${sanitized}`;
  };

  return graphs.map(graph => {
    const labels = Array.isArray(graph.labels) ? graph.labels : [];
    const values = Array.isArray(graph.values_data) ? graph.values_data : [];
    const name = tableName(graph.title);
    const rows = labels.map((label, index) => `  (${sqlValue(label)}, ${sqlValue(values[index])})`).join(',\n');
    return [
      `-- Title: ${graph.title || 'Saved Chart'}`,
      `-- Source: ${graph.source_file_name || graph.record_id || 'Unknown file'}`,
      `-- Chart Type: ${(graph.chart_type || 'bar').toUpperCase()}`,
      '',
      `CREATE TABLE IF NOT EXISTS \`${name}\` (`,
      '  `category` VARCHAR(255),',
      '  `value` DECIMAL(10,2)',
      ');',
      '',
      `INSERT INTO \`${name}\` (\`category\`, \`value\`) VALUES`,
      `${rows};`
    ].join('\n');
  }).join('\n\n');
}

export function initSavedGraphsTab(ctx) {
  const state = ctx.state;

  const refreshSelectionUi = visibleGraphs => {
    const selectedVisible = visibleGraphs.filter(graph => state.savedGraphIds.has(graph.id)).length;
    const selectAll = $('savedGraphsSelectAll');
    const printAllButton = $('savedGraphsPrintAll');
    const exportButton = $('savedGraphsExportSelected');
    const count = $('savedGraphsSelectionCount');
    if (selectAll) {
      selectAll.checked = visibleGraphs.length > 0 && selectedVisible === visibleGraphs.length;
      selectAll.indeterminate = selectedVisible > 0 && selectedVisible < visibleGraphs.length;
    }
    if (exportButton) exportButton.disabled = state.savedGraphIds.size === 0;
    if (printAllButton) printAllButton.disabled = state.savedGraphIds.size === 0;
    if (count) count.textContent = `${state.savedGraphIds.size} selected`;
  };

  const exportGraphs = async (ids, mode, graph, selectedGraphs = []) => {
    console.log('Export handler started:', { ids, mode, graph, selectedGraphs });
    try {
      if (mode === 'script') {
        const graphs = selectedGraphs.length ? selectedGraphs : (graph ? [graph] : []);
        if (!graphs.length) throw new Error('No saved graph data is selected for export.');
        const title = graph?.title || (graphs.length > 1 ? 'iris_saved_graphs' : graphs[0].title) || 'iris_saved_graphs';
        const fileName = `${title.replace(/[^a-z0-9]+/gi, '_').replace(/^_+|_+$/g, '').toLowerCase() || 'iris_saved_graphs'}.txt`;
        downloadText({ fileName, text: buildTextExport(graphs) });
        alert(`${graphs.length} graph${graphs.length === 1 ? '' : 's'} exported as a text file.`);
        return;
      }
      const payload = await ctx.dbManager.exportGraphs(ids, mode);
      alert(`${payload.count} graph${payload.count === 1 ? '' : 's'} exported as ${mode === 'script' ? 'a text file' : 'a database export'}.`);
    } catch (error) {
      console.error('Graph export failed:', error);
      alert(`Graph export failed: ${error.message || error}`);
    }
  };

  const renderCard = (graph, version, records) => {
    const canvasId = `saved_graph_canvas_${graph.id}`;
    const card = document.createElement('div');
    card.className = 'graph-card';
    const sourceName = getRecordName(graph, records);
    card.innerHTML = `<div class="graph-card-header"><div style="display:flex;gap:.6rem;align-items:flex-start;"><input class="saved-graph-checkbox" type="checkbox" data-graph-id="${escapeHtml(graph.id)}" ${state.savedGraphIds.has(graph.id) ? 'checked' : ''} aria-label="Select ${escapeHtml(graph.title || 'saved graph')}" /><div><div class="graph-card-title">${escapeHtml(graph.title || 'Saved Dashboard Chart')}</div><div style="font-size:.78rem;color:var(--text-muted);margin-top:.25rem;">Version ${version}</div><div style="font-size:.75rem;color:var(--text-muted);">Source: ${escapeHtml(sourceName)}</div></div></div><div style="display:flex;gap:.4rem;flex-wrap:wrap;justify-content:flex-end;"><span class="badge badge-low">SAVED</span><button class="export-saved-mysql" type="button">💾 Export</button><button class="export-saved-print" type="button">🖨️ Print Sheet</button><button class="btn-table-delete delete-saved-graph" type="button" data-graph-id="${escapeHtml(graph.id)}">🗑️</button></div></div><div style="font-size:.82rem;color:var(--accent-cyan);margin-bottom:1rem;">📊 Chart type: <strong>${escapeHtml((graph.chart_type || 'bar').toUpperCase())}</strong></div><div class="graph-canvas-container" style="height:260px;position:relative;"><canvas id="${canvasId}"></canvas></div>`;

    card.querySelector('.saved-graph-checkbox').onchange = event => {
      if (event.target.checked) state.savedGraphIds.add(graph.id); else state.savedGraphIds.delete(graph.id);
      refreshSelectionUi(ctx.api.savedGraphsVisible || []);
    };
    card.querySelector('.export-saved-mysql').onclick = () => showExportChoice(mode => exportGraphs([graph.id], mode, graph, [graph]));
    card.querySelector('.export-saved-print').onclick = () => ctx.dbManager.printGraphSheet(graph, { recordName: sourceName });
    card.querySelector('.delete-saved-graph').onclick = async () => {
      if (await ctx.dbManager.deleteGraph(graph.id)) {
        state.savedGraphIds.delete(graph.id);
        await ctx.api.renderSavedGraphsTab();
      }
    };
    return { card, canvasId };
  };

  ctx.api.renderSavedGraphsTab = async () => {
    const container = $('savedDashboardGraphsContainer');
    if (!container) return;
    const graphs = await ctx.dbManager.getAllSavedGraphs();
    const records = await ctx.dbManager.getAllRecords();
    ctx.api.savedGraphsRecords = records;
    const select = $('savedGraphsRecordSelect');
    const ids = [...new Set(records.map(record => record.id).filter(Boolean))];

    if (select) {
      const previousSelection = select.value;
      select.innerHTML = ids.length ? ids.map(id => {
        const record = records.find(item => item.id === id);
        return `<option value="${escapeHtml(id)}">${escapeHtml(record?.fileName || id)} (${escapeHtml((record?.fileType || 'FILE').toUpperCase())})</option>`;
      }).join('') : '<option value="">No files available</option>';
      if (state.savedGraphsViewAll) {
        select.disabled = true;
      } else {
        select.disabled = false;
        select.value = ids.includes(previousSelection) ? previousSelection : (ids[0] || '');
      }
    }

    const selectedId = select?.value || ids[0] || '';
    let visibleGraphs = state.savedGraphsViewAll ? graphs : graphs.filter(graph => graph.record_id === selectedId);
    ctx.api.savedGraphsVisible = visibleGraphs;
    container.innerHTML = '';

    if (!visibleGraphs.length) {
      container.innerHTML = '<div style="color:var(--text-muted);padding:2rem;text-align:center">No saved dashboard graphs found for the selected file yet. Save a chart in the Studio to populate this view.</div>';
      refreshSelectionUi(visibleGraphs);
      return;
    }

    if (state.savedGraphsViewAll) {
      const groups = new Map();
      visibleGraphs.forEach(graph => { const key = graph.record_id || 'unknown'; if (!groups.has(key)) groups.set(key, []); groups.get(key).push(graph); });
      groups.forEach(group => {
        const heading = document.createElement('h4');
        const first = group[0];
        heading.style.cssText = 'margin:1.25rem 0 .65rem;color:#146C36;border-bottom:1px solid #D7E3DA;padding-bottom:.45rem;';
        heading.textContent = `📁 ${getRecordName(first, records)} (${(first.source_file_type || records.find(record => record.id === first.record_id)?.fileType || 'FILE').toUpperCase()})`;
        container.appendChild(heading);
        group.forEach((graph, index) => { const rendered = renderCard(graph, group.length - index, records); container.appendChild(rendered.card); setTimeout(() => { const canvas = $(rendered.canvasId); if (canvas) createChart(canvas, graph.chart_type || 'bar', { labels: graph.labels || [], datasets: [{ label: graph.title || 'Saved Series', data: graph.values_data || [] }] }); }, 20); });
      });
    } else {
      visibleGraphs.forEach((graph, index) => { const rendered = renderCard(graph, visibleGraphs.length - index, records); container.appendChild(rendered.card); setTimeout(() => { const canvas = $(rendered.canvasId); if (canvas) createChart(canvas, graph.chart_type || 'bar', { labels: graph.labels || [], datasets: [{ label: graph.title || 'Saved Series', data: graph.values_data || [] }] }); }, 20); });
    }

    refreshSelectionUi(visibleGraphs);
  };

  $('savedGraphsViewAllBtn')?.addEventListener('click', async event => {
    state.savedGraphsViewAll = !state.savedGraphsViewAll;
    event.currentTarget.textContent = state.savedGraphsViewAll ? 'View Per File' : 'View All';
    await ctx.api.renderSavedGraphsTab();
  });
  $('savedGraphsRecordSelect')?.addEventListener('change', () => ctx.api.renderSavedGraphsTab());
  $('savedGraphsSelectAll')?.addEventListener('change', event => {
    (ctx.api.savedGraphsVisible || []).forEach(graph => event.target.checked ? state.savedGraphIds.add(graph.id) : state.savedGraphIds.delete(graph.id));
    ctx.api.renderSavedGraphsTab();
  });
  $('savedGraphsExportSelected')?.addEventListener('click', () => {
    const ids = [...state.savedGraphIds];
    const selectedGraphs = (ctx.api.savedGraphsVisible || []).filter(graph => state.savedGraphIds.has(graph.id));
    if (ids.length) showExportChoice(mode => exportGraphs(ids, mode, null, selectedGraphs));
  });
  $('savedGraphsPrintAll')?.addEventListener('click', () => {
    const selectedGraphs = (ctx.api.savedGraphsVisible || []).filter(graph => state.savedGraphIds.has(graph.id));
    if (selectedGraphs.length) {
      try {
        ctx.dbManager.printGraphSheets(selectedGraphs, {
          recordNameForGraph: graph => getRecordName(graph, ctx.api.savedGraphsRecords || [])
        });
      } catch (error) {
        console.error('Print All failed:', error);
        alert(`Print All failed: ${error.message || error}`);
      }
    }
  });
}
