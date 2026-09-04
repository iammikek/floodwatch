/**
 * Load EA Flood Map for Planning flood-zone polygons for the map viewport.
 */

import { bboxFromCenter, CORRIDOR_CENTER } from './fetchLiveMapData.js';

/**
 * @param {unknown} body
 * @returns {{ type: 'FeatureCollection', features: object[] }}
 */
export function normalizeFloodZoneCollection(body) {
  if (!body || typeof body !== 'object') {
    return { type: 'FeatureCollection', features: [] };
  }
  const raw = /** @type {Record<string, unknown>} */ (body);
  const candidate =
    raw.data && typeof raw.data === 'object'
      ? /** @type {Record<string, unknown>} */ (raw.data)
      : raw;
  const features = Array.isArray(candidate.features) ? candidate.features : [];
  return {
    type: 'FeatureCollection',
    features: features.filter((f) => f && typeof f === 'object' && f.geometry),
  };
}

/**
 * @param {{
 *   center?: [number, number],
 *   radiusKm?: number,
 *   bbox?: string,
 *   outcode?: string,
 *   fetchImpl?: typeof fetch,
 * }} opts
 */
export async function fetchFloodZones({
  center = CORRIDOR_CENTER.center,
  radiusKm = 12,
  bbox,
  outcode = 'TA10',
  fetchImpl = fetch,
} = {}) {
  const box = bbox || bboxFromCenter(center, radiusKm);
  const params = new URLSearchParams({
    bbox: box,
    outcode,
    format: 'simplified',
  });
  const url = `/flood-watch/polygons?${params.toString()}`;
  const res = await fetchImpl(url, {
    headers: { Accept: 'application/json' },
    credentials: 'same-origin',
  });
  if (!res.ok) {
    throw new Error(`Flood zones request failed: ${res.status}`);
  }
  const body = await res.json();
  return normalizeFloodZoneCollection(body);
}
