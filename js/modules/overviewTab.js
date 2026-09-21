import { $, formatFileSize } from '../utils/helpers.js';

function extractKeyFields(scan) {
  const fields = [];
  if (scan.type === 'excel' && scan.sheetsData) Object.entries(scan.sheetsData).forEach(([name, sheet]) => { fields.push({ label: `Sheet: ${name}`, value: `${sheet.rows?.length || 0} Rows` }); (sheet.headers || []).slice(0, 3).forEach(header => header && fields.push({ label: 'Column Header', value: header })); });
  else if (scan.rawText) { const pattern = /([A-Za-z\s\(\)\-\/]{3,30})\s*[:\-\=]\s*([0-9\.,]+%?)/g; let match; while ((match = pattern.exec(scan.rawText)) && fields.length < 6) fields.push({ label: match[1].trim(), value: match[2].trim() }); }
  return fields.length ? fields : [{ label: 'Format Type', value: scan.type.toUpperCase() }, { label: 'File Size', value: formatFileSize(scan.size) }];
}
function generateTakeaways(scan) { const result = [`Source Format: <strong>${scan.type.toUpperCase()}</strong> (${formatFileSize(scan.size)}).`]; if (scan.type === 'excel') result.push(`Multi-sheet spreadsheet containing <strong>${Object.keys(scan.sheetsData || {}).length} worksheet(s)</strong>.`); if (scan.type === 'pdf') result.push('Layout-aware PDF parsing completed with structured stat-card extraction.'); if (scan.type === 'docx') result.push('Word document paragraphs and embedded media unpacked.'); if (scan.type === 'image') result.push('Optical Character Recognition (OCR) extracted text and metric indicators.'); result.push('Draft status assigned as <strong>Pending Admin Review</strong> before publication to ECharts dashboard.'); return result; }

export function initOverviewTab(ctx) {
  ctx.api.renderOverviewTab = async scan => {
    const saved = scan.id ? await ctx.dbManager.getGraphsByRecord(scan.id) : [];
    scan.graphDrafts = saved.length ? saved : (scan.graphDrafts || []);
    $('summaryDocTitle').textContent = scan.name; $('docFormatBadge').textContent = scan.type.toUpperCase(); $('docFormatBadge').className = `format-chip ${scan.type}`;
    const raw = scan.rawText || ''; $('executiveSummaryText').textContent = `Document parsed successfully. Identified ${raw.split(/\s+/).filter(Boolean).length} words, ${raw.length} characters, with ${(scan.graphDrafts || []).length} draft visualization suggestions.`;
    const fieldsGrid = $('extractedFieldsGrid'); fieldsGrid.innerHTML = ''; extractKeyFields(scan).forEach(field => { const card = document.createElement('div'); card.style.cssText = 'background: var(--bg-card); border: 1px solid var(--border-light); border-radius: var(--radius-sm); padding: 0.75rem 1rem;'; card.innerHTML = `<div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700; margin-bottom: 0.25rem;">${field.label}</div><div style="font-size: 1.1rem; font-weight: 800; color: var(--accent-cyan); font-family: var(--font-mono);">${field.value}</div>`; fieldsGrid.appendChild(card); });
    $('takeawayList').innerHTML = generateTakeaways(scan).map(item => `<li class="takeaway-item"><span>🔹</span><div>${item}</div></li>`).join(''); $('draftsCountBadge').textContent = (scan.graphDrafts || []).length;
  };
  $('btnOpenInEditor')?.addEventListener('click', async () => {
    if (!ctx.state.activeScan) return;
    await ctx.api.openReviewStudio?.(ctx.state.activeScan.id);
  });
}
