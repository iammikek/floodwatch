/**
 * Load curated storm catalogue for place-mode replay.
 * @param {{ corridor?: string, fetchImpl?: typeof fetch }} [opts]
 * @returns {Promise<{ source: 'lake'|'empty'|'error', items: Array<object>, error?: string }>}
 */
export async function fetchStorms({
  corridor = 'a361-muchelney',
  fetchImpl = fetch,
} = {}) {
  const url = `/flood-watch/storms?corridor=${encodeURIComponent(corridor)}`;
  try {
    const res = await fetchImpl(url, {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
    });
    if (!res.ok) {
      throw new Error(`Storm catalogue request failed: ${res.status}`);
    }
    const body = await res.json();
    const items = Array.isArray(body?.storms) ? body.storms : [];
    return { source: items.length ? 'lake' : 'empty', items };
  } catch (err) {
    return {
      source: 'error',
      items: [],
      error: err instanceof Error ? err.message : String(err),
    };
  }
}
