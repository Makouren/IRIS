import { $, all, escapeHtml } from '../utils/helpers.js';
import { createChart } from './chartEngine.js?v=remove-rose-20261001';
import { downloadText, showExportChoice } from './savedGraphsTab.js';

export function initGraphsTab(ctx) {
  ctx.api.renderGraphsTab = async scan => {
    const container = $('graphDraftsContainer');
    container.querySelectorAll('.graph-card').forEach(card => { card._chartResizeObserver?.disconnect?.(); card._chart?.dispose?.(); card._chart?.destroy?.(); });
    container.innerHTML = '';
    Object.values(ctx.state.chartInstances).forEach(chart => { chart?.dispose?.(); chart?.destroy?.(); });
    ctx.state.chartInstances = {};
    const savedGraphs = scan.id ? await ctx.dbManager.getGraphsByRecord(scan.id) : [];
    const drafts = savedGraphs.length > 0 ? savedGraphs : (scan.graphDrafts || []);
    scan.graphDrafts = drafts;

    if (!drafts.length) {
      container.innerHTML = '<div style="color:var(--text-muted);padding:2rem;text-align:center">No numerical series detected to build chart drafts.</div>';
      return;
    }

    drafts.forEach((draft, index) => {
      const canvasId = `chart_canvas_${index}`;
      const card = document.createElement('div');
      card.className = 'graph-card';
      const selectedType = /^(?:polararea|polar-area|rose|nightingale)$/i.test(String(draft.chart_type || draft.primaryType || 'bar')) ? 'bar' : (draft.chart_type || draft.primaryType || 'bar');
      // <button class="export-draft-mysql" type="button" title="Export to MySQL" aria-label="Export to MySQL">/* */</button>
      card.innerHTML = `<div class="graph-card-header"><div><div class="graph-card-title">${escapeHtml(draft.title)}</div><div style="font-size:.78rem;color:var(--text-muted);margin-top:.25rem">Source: ${escapeHtml(draft.source)}</div></div><div><button class="export-draft-print" type="button"><i class="fa-solid fa-print" aria-hidden="true"></i> Print Sheet</button><select class="form-input chart-type-select" data-draft-idx="${index}" style="width:auto;padding:.25rem .5rem;font-size:.8rem"><option value="bar" ${selectedType === 'bar' ? 'selected' : ''}>Bar Chart</option><option value="line" ${selectedType === 'line' ? 'selected' : ''}>Line Chart</option><option value="pie" ${selectedType === 'pie' ? 'selected' : ''}>Pie Chart</option><option value="doughnut" ${selectedType === 'doughnut' ? 'selected' : ''}>Doughnut Chart</option><option value="rankedBar" ${selectedType === 'rankedBar' ? 'selected' : ''}>Ranked Bar</option><option value="nestedPie" ${selectedType === 'nestedPie' ? 'selected' : ''}>Nested Pie</option></select></div></div><div style="font-size:.82rem;color:var(--accent-cyan);margin-bottom:1rem"><i class="fa-solid fa-lightbulb" aria-hidden="true"></i> <strong>AI Recommendation:</strong> ${escapeHtml(draft.recommendation)}</div><div class="graph-canvas-container" style="height:320px;position:relative"><div id="${canvasId}" style="height:100%;width:100%"></div></div>`;
      container.appendChild(card);

      card.querySelector('.export-draft-print').onclick = () => ctx.dbManager.printGraphSheet(draft, { recordName: scan.name || 'IRIS report' });
      const render = () => {
        const host = $(canvasId);
        if (!host) return;
        card._chartResizeObserver?.disconnect?.();
        card._chart?.dispose?.();
        card._chart = createChart(host, draft.chart_type || draft.primaryType || selectedType, draft.chartData ? { ...draft, ...draft.chartData, chartData: draft.chartData } : draft);
        if (typeof ResizeObserver !== 'undefined') {
          card._chartResizeObserver = new ResizeObserver(() => card._chart?.resize?.());
          card._chartResizeObserver.observe(host);
        }
        ctx.state.chartInstances[canvasId] = card._chart;
      };
      card._renderChart = render;
      setTimeout(render, 50);
    });

    all('.chart-type-select').forEach(select => select.addEventListener('change', event => {
      const index = Number(event.target.dataset.draftIdx);
      drafts[index].primaryType = event.target.value;
      drafts[index].chart_type = event.target.value;
      const id = `chart_canvas_${index}`;
      const card = event.target.closest('.graph-card');
      ctx.state.chartInstances[id]?.dispose?.();
      card?._chartResizeObserver?.disconnect?.();
      card?._renderChart?.();
    }));
  };
  new MutationObserver(() => { if (ctx.state.activeScan) ctx.api.renderGraphsTab(ctx.state.activeScan); }).observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
}
