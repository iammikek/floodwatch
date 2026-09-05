/**
 * Flood-bound presentation for live vs place-history replay.
 *
 * Live: EA planning FZ2/FZ3 for the map viewport.
 * Replay: clip planning zones to each storm's curated impact_bbox
 * (approximate event footprint — not modelled inundation).
 */

/**
 * @param {string|null|undefined} severity
 * @returns {'live'|'low'|'medium'|'high'}
 */
export function normalizeEventSeverity(severity) {
  const s = String(severity || '').toLowerCase();
  if (s === 'low' || s === 'medium' || s === 'high') return s;
  return 'live';
}

/**
 * @param {unknown} raw
 * @returns {[number, number, number, number]|null}
 */
export function normalizeImpactBbox(raw) {
  if (!Array.isArray(raw) || raw.length !== 4) return null;
  const nums = raw.map(Number);
  if (nums.some((n) => !Number.isFinite(n))) return null;
  const [w, s, e, n] = nums;
  if (e <= w || n <= s) return null;
  return [w, s, e, n];
}

/**
 * @param {unknown} coords
 * @param {number[]} xs
 * @param {number[]} ys
 */
function collectCoords(coords, xs, ys) {
  if (!Array.isArray(coords)) return;
  if (typeof coords[0] === 'number' && typeof coords[1] === 'number') {
    xs.push(Number(coords[0]));
    ys.push(Number(coords[1]));
    return;
  }
  for (const child of coords) collectCoords(child, xs, ys);
}

/**
 * @param {unknown} geom
 * @returns {[number, number, number, number]|null}
 */
export function geometryBbox(geom) {
  if (!geom || typeof geom !== 'object') return null;
  const xs = [];
  const ys = [];
  collectCoords(/** @type {{ coordinates?: unknown }} */ (geom).coordinates, xs, ys);
  if (!xs.length) return null;
  return [Math.min(...xs), Math.min(...ys), Math.max(...xs), Math.max(...ys)];
}

/**
 * @param {[number, number, number, number]} a
 * @param {[number, number, number, number]} b
 */
export function bboxIntersects(a, b) {
  return !(a[2] < b[0] || a[0] > b[2] || a[3] < b[1] || a[1] > b[3]);
}

/**
 * @param {{
 *   type?: string,
 *   features?: object[],
 * }|null|undefined} geo
 * @param {{
 *   severity?: string|null,
 *   bounds_mode?: string|null,
 *   impact_bbox?: unknown,
 * }|null|undefined} event
 * @returns {{ type: 'FeatureCollection', features: object[] }}
 */
export function emphasizeFloodZones(geo, event = null) {
  const features = Array.isArray(geo?.features) ? geo.features : [];
  const mode = String(event?.bounds_mode || '').toLowerCase();
  if (mode === 'none') {
    return { type: 'FeatureCollection', features: [] };
  }

  const level = normalizeEventSeverity(event?.severity);
  const impact = normalizeImpactBbox(event?.impact_bbox);

  const filtered = features.filter((f) => {
    const zone = String(f?.properties?.flood_zone || '');
    if (level === 'low' && zone !== 'FZ3') return false;
    if (level === 'medium' && zone !== 'FZ3' && zone !== 'FZ2') return false;
    if (impact) {
      const gb = geometryBbox(f?.geometry);
      if (!gb || !bboxIntersects(gb, impact)) return false;
    }
    return true;
  });

  return {
    type: 'FeatureCollection',
    features: filtered.map((f) => ({
      ...f,
      properties: {
        ...(f.properties || {}),
        _emphasis: level,
      },
    })),
  };
}

/**
 * Leaflet path style for a flood-zone feature under the current emphasis.
 * @param {object} feature
 */
export function floodZoneStyleForFeature(feature) {
  const zone = String(feature?.properties?.flood_zone || '');
  const level = normalizeEventSeverity(feature?.properties?._emphasis);
  const isFz3 = zone === 'FZ3';

  if (level === 'low') {
    return {
      color: '#a16207',
      fillColor: '#fde68a',
      fillOpacity: isFz3 ? 0.12 : 0,
      weight: 1,
      opacity: 0.35,
    };
  }
  if (level === 'medium') {
    return {
      color: isFz3 ? '#b45309' : '#d97706',
      fillColor: isFz3 ? '#f59e0b' : '#fbbf24',
      fillOpacity: isFz3 ? 0.22 : 0.1,
      weight: 1,
      opacity: isFz3 ? 0.65 : 0.4,
    };
  }
  if (level === 'high') {
    return {
      color: isFz3 ? '#9a3412' : '#b45309',
      fillColor: isFz3 ? '#ea580c' : '#f59e0b',
      fillOpacity: isFz3 ? 0.38 : 0.22,
      weight: isFz3 ? 1.5 : 1,
      opacity: isFz3 ? 0.9 : 0.7,
    };
  }
  if (isFz3) {
    return { color: '#b45309', fillColor: '#f59e0b', fillOpacity: 0.28, weight: 1, opacity: 0.75 };
  }
  return { color: '#d97706', fillColor: '#fbbf24', fillOpacity: 0.16, weight: 1, opacity: 0.55 };
}
