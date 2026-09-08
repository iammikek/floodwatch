/**
 * Load bathtub volume estimate for a storm (History analytic).
 * @param {{ stormId: string, place?: string, fetchImpl?: typeof fetch }} opts
 * @returns {Promise<{ source: 'lake'|'unavailable'|'error', doc: object|null, error?: string }>}
 */
export async function fetchStormVolume({
  stormId,
  place = 'a361-muchelney',
  fetchImpl = fetch,
} = {}) {
  if (!stormId) {
    return { source: 'unavailable', doc: null };
  }
  const params = new URLSearchParams();
  if (place) params.set('place', place);
  const qs = params.toString();
  const url = `/flood-watch/storms/${encodeURIComponent(stormId)}/volume${qs ? `?${qs}` : ''}`;
  try {
    const res = await fetchImpl(url, {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
    });
    if (!res.ok) {
      throw new Error(`Storm volume request failed: ${res.status}`);
    }
    const doc = await res.json();
    if (!doc?.available) {
      return { source: 'unavailable', doc };
    }
    return { source: 'lake', doc };
  } catch (err) {
    return {
      source: 'error',
      doc: null,
      error: err instanceof Error ? err.message : String(err),
    };
  }
}
