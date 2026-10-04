/**
 * Purpose: Scanner import module for import data mapper; supports the browser-side import workflow.
 */
export function normalizeHeader(value) {
  return String(value ?? '').trim().toLowerCase().replace(/[^a-z0-9]+/g, ' ').trim();
}

export function displayValues(values) {
  if (!values || typeof values !== 'object') return '';
  return Object.entries(values)
    .filter(([, value]) => value !== null && value !== undefined && value !== '')
    .map(([field, value]) => `${field.replaceAll('_', ' ')}: ${String(value)}`)
    .join('\n');
}

export function identityLabel(row, destination) {
  if (destination === 'summary_cards') return `${row.card_title || row.import_key} · ${row.period_key}`;
  const identity = row.identity || {};
  return [identity.organization, identity.ranking_type, identity.year]
    .filter(value => value !== null && value !== undefined && value !== '')
    .join(' · ');
}
