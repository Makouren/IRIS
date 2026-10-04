/**
 * Purpose: Scanner import module for import api client; supports the browser-side import workflow.
 */
export async function getJson(url) {
  const response = await fetch(url, { headers: { Accept: 'application/json' }, cache: 'no-store' });
  return readJson(response);
}

export async function postJson(url, token, payload) {
  const response = await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': token, Accept: 'application/json' },
    body: JSON.stringify(payload)
  });
  return readJson(response);
}

async function readJson(response) {
  const result = await response.json().catch(() => ({}));
  if (!response.ok) throw Object.assign(new Error(result.error || `Request failed (${response.status}).`), result);
  return result;
}
