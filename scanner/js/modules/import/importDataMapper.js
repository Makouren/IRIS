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
  return [`Body ${identity.ranking_body_id}`, identity.scope_id ? `Scope ${identity.scope_id}` : 'Unassigned', identity.ranking_type, identity.level || 'Unassigned', identity.year, identity.edition, identity.category]
    .filter(value => value !== null && value !== undefined && value !== '')
    .join(' · ');
}
