export const $ = (id) => document.getElementById(id);
export const all = (selector) => Array.from(document.querySelectorAll(selector));

export function escapeHtml(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;').replace(/</g, '&lt;')
    .replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}

export function parseEditableValue(value) {
  const text = String(value ?? '').trim();
  return text !== '' && Number.isFinite(Number(text)) ? Number(text) : text;
}

export function formatFileSize(size) { return `${(Number(size || 0) / 1024).toFixed(1)} KB`; }

