import { $, formatFileSize } from '../utils/helpers.js';

export function initQueue(ctx) {
  const list = $('queueList'); const count = $('queueCount'); const workspace = $('workspaceGrid');
  ctx.api.renderQueue = () => {
    if (count) count.textContent = ctx.state.queue.length; if (!list) return; list.innerHTML = '';
    const icons = { excel: '📊', pdf: '📄', docx: '📝', image: '🖼️', unknown: '📁' };
    ctx.state.queue.forEach(item => {
      const element = document.createElement('div'); element.className = `queue-item ${ctx.state.activeScan?.id === item.id ? 'active' : ''}`;
      element.innerHTML = `<div class="queue-icon">${icons[item.type] || '📁'}</div><div class="queue-info"><div class="queue-name" title="${item.name}">${item.name}</div><div class="queue-meta"><span>${formatFileSize(item.size)}</span><span class="queue-badge low">Draft</span></div></div>`;
      element.addEventListener('click', () => ctx.api.setActiveScan(item)); list.appendChild(element);
    });
  };
  $('btnClearQueue')?.addEventListener('click', () => { ctx.state.queue = []; ctx.state.activeScan = null; if (workspace) workspace.style.display = 'none'; ctx.api.renderQueue(); });
}
