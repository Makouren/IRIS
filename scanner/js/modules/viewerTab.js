/**
 * Purpose: Scanner interface module for viewer tab; loaded by the Scanner application.
 */
import { $ } from '../utils/helpers.js';
export function initViewerTab(ctx) {
  ctx.api.renderViewerTab = scan => {
    if (!scan) return;
    const meta = $('viewerFileMeta');
    if (meta) meta.textContent = `${scan.name || 'Untitled'} • ${(scan.type || '').toUpperCase()} • ${((scan.size || 0) / 1024).toFixed(1)} KB`;
    const area = $('viewerContentArea');
    if (!area) return;
    area.innerHTML = '';
    const container = $('sheetSelectorContainer');
    if (container) container.style.display = 'none';

    if (scan.type === 'image' && scan.previewUrl) {
      const image = document.createElement('img');
      image.src = scan.previewUrl;
      image.style.cssText = 'max-width:100%;border-radius:var(--radius-md);border:1px solid var(--border-light)';
      area.appendChild(image);
      return;
    }
    if (scan.type === 'excel' && scan.sheetsData) {
      const select = $('sheetSelect');
      if (container) container.style.display = 'flex';
      if (select) {
        select.innerHTML = Object.keys(scan.sheetsData).map(name => `<option value="${name}">${name}</option>`).join('');
        const render = name => {
          const sheet = scan.sheetsData[name];
          if (!sheet) return;
          area.innerHTML = `<div class="table-container" style="max-height:500px"><table class="data-table"><thead><tr>${(sheet.headers || []).map(h => `<th>${h || ''}</th>`).join('')}</tr></thead><tbody>${(sheet.rows || []).slice(0, 50).map(row => `<tr>${(row || []).map(cell => `<td>${cell ?? ''}</td>`).join('')}</tr>`).join('')}</tbody></table></div>`;
        };
        select.onchange = event => render(event.target.value);
        render(select.value);
      }
      return;
    }
    if (scan.type === 'docx' && scan.docxBuffer && typeof window.DocxViewerComponent !== 'undefined') {
      const wrap = document.createElement('div');
      wrap.style.cssText = 'width:100%;height:100%;min-height:400px';
      area.appendChild(wrap);
      new window.DocxViewerComponent(wrap, { showToolbar: true }).loadDocument(scan.docxBuffer, scan.name || 'document.docx');
      return;
    }
    if (scan.formattedHtml) {
      const div = document.createElement('div');
      div.className = 'docx-reader-container';
      div.innerHTML = scan.formattedHtml;
      area.appendChild(div);
      return;
    }
    const pre = document.createElement('pre');
    pre.style.cssText = 'background:rgba(15,23,42,.7);padding:1.25rem;border-radius:var(--radius-md);font-family:var(--font-mono);font-size:.85rem;color:var(--text-light);overflow-x:auto;white-space:pre-wrap';
    pre.textContent = scan.rawText || 'No readable text extracted.';
    area.appendChild(pre);
  };
}
