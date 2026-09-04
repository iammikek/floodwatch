/**
 * Same-origin lake feeds via Laravel proxies (tokens stay on the server).
 */

export const CORRIDOR_CENTER = {
  label: 'A361 Muchelney corridor',
  // [lat, lng] — Somerset Levels / Muchelney
  center: [51.12, -2.82],
  zoom: 11,
  region: 'SOM',
  radiusKm: 20,
};

/**
 * @param {[number, number]} center [lat, lng]
 * @param {number} radiusKm
 * @returns {string} minLng,minLat,maxLng,maxLat
 */
export function bboxFromCenter(center, radiusKm = 20) {
  const [lat, lng] = center;
  const latDelta = radiusKm / 111.0;
  const lngDelta = radiusKm / (111.0 * Math.max(Math.cos((lat * Math.PI) / 180), 0.001));
  const minLat = lat - latDelta;
  const maxLat = lat + latDelta;
  const minLng = lng - lngDelta;
  const maxLng = lng + lngDelta;
  return `${minLng},${minLat},${maxLng},${maxLat}`;
}

/**
 * @param {unknown} row
 * @param {number} index
 */
export function normalizeGauge(row, index = 0) {
  if (!row || typeof row !== 'object') return null;
  const g = /** @type {Record<string, unknown>} */ (row);
  const lat = Number(g.lat);
  const lng = Number(g.lng);
  if (!Number.isFinite(lat) || !Number.isFinite(lng)) return null;
  const station = String(g.station ?? g.station_label ?? 'Gauge');
  const id = String(g.id ?? g.notation ?? g.measure_id ?? `gauge-${station}-${index}`);
  return {
    ...g,
    id,
    type: 'gauge',
    station,
    river: String(g.river ?? ''),
    town: String(g.town ?? ''),
    value: Number(g.value),
    unit: String(g.unit ?? g.unitName ?? 'm'),
    unitName: String(g.unitName ?? g.unit ?? 'm'),
    dateTime: g.dateTime ? String(g.dateTime) : null,
    lat,
    lng,
    levelStatus: String(g.levelStatus ?? 'unknown'),
    stationType: String(g.stationType ?? 'river_gauge'),
  };
}

/**
 * Map EA / lake severity text to numeric level (1 severe … 4 clear).
 * @param {unknown} severity
 * @param {unknown} severityLevel
 */
export function severityToLevel(severity, severityLevel) {
  const n = Number(severityLevel);
  if (Number.isFinite(n) && n >= 1 && n <= 4) return n;
  const label = String(severity ?? '').toLowerCase();
  if (label.includes('severe')) return 1;
  if (label.includes('warning') && !label.includes('no longer')) return 2;
  if (label.includes('alert')) return 3;
  if (label.includes('no longer')) return 4;
  return 4;
}

/**
 * Rough centroid from GeoJSON geometry (Point / Polygon / MultiPolygon).
 * @param {unknown} geometry
 * @returns {[number, number]|null} [lat, lng]
 */
export function centroidFromGeometry(geometry) {
  if (!geometry || typeof geometry !== 'object') return null;
  const g = /** @type {{ type?: string, coordinates?: unknown }} */ (geometry);
  const type = g.type;
  const coords = g.coordinates;
  if (type === 'Point' && Array.isArray(coords) && coords.length >= 2) {
    const lng = Number(coords[0]);
    const lat = Number(coords[1]);
    return Number.isFinite(lat) && Number.isFinite(lng) ? [lat, lng] : null;
  }
  /** @type {number[]} */
  const flat = [];
  const walk = (node) => {
    if (!Array.isArray(node)) return;
    if (typeof node[0] === 'number' && typeof node[1] === 'number') {
      flat.push(Number(node[0]), Number(node[1]));
      return;
    }
    for (const child of node) walk(child);
  };
  walk(coords);
  if (flat.length < 2) return null;
  let sumLng = 0;
  let sumLat = 0;
  let n = 0;
  for (let i = 0; i < flat.length; i += 2) {
    sumLng += flat[i];
    sumLat += flat[i + 1];
    n += 1;
  }
  if (!n) return null;
  return [sumLat / n, sumLng / n];
}

/**
 * Normalize lake Warning (`title`, `geometry`) or fixture rows (`lat`/`lng`).
 * Rows without coordinates are kept for corridor counts but skipped on the map.
 * @param {unknown} row
 * @param {number} index
 */
export function normalizeWarning(row, index = 0) {
  if (!row || typeof row !== 'object') return null;
  const w = /** @type {Record<string, unknown>} */ (row);
  let lat = Number(w.lat);
  let lng = Number(w.lng);
  if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
    const centroid = centroidFromGeometry(w.geometry);
    if (centroid) {
      [lat, lng] = centroid;
    } else {
      lat = NaN;
      lng = NaN;
    }
  }
  const severity = String(w.severity ?? 'Flood Alert');
  const id = String(w.id ?? w.floodAreaID ?? `warn-${index}`);
  return {
    ...w,
    id,
    type: 'warning',
    severity,
    severityLevel: severityToLevel(severity, w.severityLevel ?? w.severity_level),
    description: String(w.description ?? w.title ?? 'Flood warning'),
    message: String(w.message ?? ''),
    floodAreaID: w.floodAreaID ? String(w.floodAreaID) : w.area_id ? String(w.area_id) : null,
    lat: Number.isFinite(lat) ? lat : null,
    lng: Number.isFinite(lng) ? lng : null,
  };
}

/**
 * @param {{ lat: number, lng: number, radiusKm?: number, fetchImpl?: typeof fetch }} opts
 */
export async function fetchRiverLevels({
  lat,
  lng,
  radiusKm = CORRIDOR_CENTER.radiusKm,
  fetchImpl = fetch,
} = {}) {
  const url =
    `/flood-watch/river-levels?lat=${encodeURIComponent(lat)}` +
    `&lng=${encodeURIComponent(lng)}` +
    `&radius=${encodeURIComponent(radiusKm)}`;
  const res = await fetchImpl(url, {
    headers: { Accept: 'application/json' },
    credentials: 'same-origin',
  });
  if (!res.ok) {
    throw new Error(`River levels request failed: ${res.status}`);
  }
  const body = await res.json();
  const rows = Array.isArray(body) ? body : [];
  return rows.map(normalizeGauge).filter(Boolean);
}

/**
 * @param {{ center?: [number, number], radiusKm?: number, region?: string, fetchImpl?: typeof fetch }} opts
 */
export async function fetchWarnings({
  center = CORRIDOR_CENTER.center,
  radiusKm = CORRIDOR_CENTER.radiusKm,
  region = CORRIDOR_CENTER.region,
  fetchImpl = fetch,
} = {}) {
  const bbox = bboxFromCenter(center, radiusKm);
  const url =
    `/api/lake/warnings?bbox=${encodeURIComponent(bbox)}` +
    `&region=${encodeURIComponent(region)}`;
  const res = await fetchImpl(url, {
    headers: { Accept: 'application/json' },
    credentials: 'same-origin',
  });
  if (!res.ok) {
    throw new Error(`Warnings request failed: ${res.status}`);
  }
  const body = await res.json();
  const items = Array.isArray(body?.items) ? body.items : Array.isArray(body) ? body : [];
  return items.map(normalizeWarning).filter(Boolean);
}

/**
 * Load map overlays from the lake (via Laravel).
 * Live mode never invents overlays — failed feeds become empty lists.
 * @returns {Promise<{ source: 'lake'|'error', gauges: object[], floods: object[], error?: string }>}
 */
export async function fetchLiveMapData({
  center = CORRIDOR_CENTER.center,
  radiusKm = CORRIDOR_CENTER.radiusKm,
  region = CORRIDOR_CENTER.region,
  fetchImpl = fetch,
} = {}) {
  const [lat, lng] = center;
  const [gaugesResult, floodsResult] = await Promise.allSettled([
    fetchRiverLevels({ lat, lng, radiusKm, fetchImpl }),
    fetchWarnings({ center, radiusKm, region, fetchImpl }),
  ]);

  const gaugesOk = gaugesResult.status === 'fulfilled';
  const floodsOk = floodsResult.status === 'fulfilled';
  const errors = [];
  if (!gaugesOk) {
    errors.push(
      gaugesResult.reason instanceof Error
        ? gaugesResult.reason.message
        : String(gaugesResult.reason),
    );
  }
  if (!floodsOk) {
    errors.push(
      floodsResult.reason instanceof Error
        ? floodsResult.reason.message
        : String(floodsResult.reason),
    );
  }

  if (!gaugesOk && !floodsOk) {
    return {
      source: 'error',
      gauges: [],
      floods: [],
      error: errors.join('; '),
    };
  }

  return {
    source: 'lake',
    gauges: gaugesOk ? gaugesResult.value : [],
    floods: floodsOk ? floodsResult.value : [],
    ...(errors.length ? { error: errors.join('; ') } : {}),
  };
}
