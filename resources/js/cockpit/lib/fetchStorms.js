/**
 * Load curated storm catalogue for place-mode replay.
 * @param {{ corridor?: string, volumeCompare?: boolean, fetchImpl?: typeof fetch }} [opts]
 * @returns {Promise<{ source: 'lake'|'empty'|'error', items: Array<object>, error?: string }>}
 */
export async function fetchStorms({
  corridor = 'a361-muchelney',
  volumeCompare = false,
  fetchImpl = fetch,
} = {}) {
  const params = new URLSearchParams();
  params.set('corridor', corridor);
  if (volumeCompare) params.set('volume_compare', 'true');
  const url = `/flood-watch/storms?${params.toString()}`;
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
