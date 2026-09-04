/**
 * Same-origin Laravel proxies for road incidents + corridor route check.
 * (Not data-lake — National Highways / OSRM / EA via Laravel services.)
 */

import { CORRIDOR_CENTER } from './fetchLiveMapData.js';

export const DEFAULT_ROUTE = {
  from: 'Muchelney, Somerset',
  to: 'Bridgwater, Somerset',
};

/**
 * @param {unknown} row
 * @param {number} index
 */
export function normalizeIncident(row, index = 0) {
  if (!row || typeof row !== 'object') return null;
  const i = /** @type {Record<string, unknown>} */ (row);
  const lat = i.lat != null ? Number(i.lat) : null;
  const lng = i.lng != null ? Number(i.lng) : null;
  return {
    ...i,
    id: String(i.id ?? `inc-${index}`),
    type: 'incident',
    road: String(i.road ?? 'Road'),
    statusLabel: String(i.statusLabel ?? i.status ?? 'Update'),
    description: String(i.description ?? ''),
    lat: Number.isFinite(lat) ? lat : null,
    lng: Number.isFinite(lng) ? lng : null,
  };
}

/**
 * @param {{ lat?: number, lng?: number, region?: string, fetchImpl?: typeof fetch }} opts
 */
export async function fetchIncidents({
  lat = CORRIDOR_CENTER.center[0],
  lng = CORRIDOR_CENTER.center[1],
  region = CORRIDOR_CENTER.region,
  fetchImpl = fetch,
} = {}) {
  const url =
    `/flood-watch/incidents?lat=${encodeURIComponent(lat)}` +
    `&lng=${encodeURIComponent(lng)}` +
    `&region=${encodeURIComponent(region)}`;
  const res = await fetchImpl(url, {
    headers: { Accept: 'application/json' },
    credentials: 'same-origin',
  });
  if (!res.ok) {
    throw new Error(`Incidents request failed: ${res.status}`);
  }
  const body = await res.json();
  const items = Array.isArray(body?.items) ? body.items : Array.isArray(body) ? body : [];
  return items.map(normalizeIncident).filter(Boolean);
}

/**
 * @param {{ from?: string, to?: string, fetchImpl?: typeof fetch }} opts
 */
export async function fetchRouteCheck({
  from = DEFAULT_ROUTE.from,
  to = DEFAULT_ROUTE.to,
  fetchImpl = fetch,
} = {}) {
  const url =
    `/flood-watch/route-check?from=${encodeURIComponent(from)}` +
    `&to=${encodeURIComponent(to)}`;
  const res = await fetchImpl(url, {
    headers: { Accept: 'application/json' },
    credentials: 'same-origin',
  });
  if (!res.ok) {
    throw new Error(`Route check request failed: ${res.status}`);
  }
  const body = await res.json();
  if (!body || typeof body !== 'object') {
    throw new Error('Route check payload invalid');
  }
  return {
    verdict: String(body.verdict ?? 'error'),
    verdictLabel: String(body.verdict_label ?? body.verdictLabel ?? 'Error'),
    summary: String(body.summary ?? ''),
    floodsOnRoute: Array.isArray(body.floods_on_route) ? body.floods_on_route : [],
    incidentsOnRoute: Array.isArray(body.incidents_on_route) ? body.incidents_on_route : [],
    alternatives: Array.isArray(body.alternatives) ? body.alternatives : [],
    routeGeometry: Array.isArray(body.route_geometry) ? body.route_geometry : [],
    routeKey: body.route_key ? String(body.route_key) : null,
    from: String(body.from ?? from),
    to: String(body.to ?? to),
  };
}

/**
 * Live mode never invents road data — failed feeds become empty / null.
 * @returns {Promise<{ source: 'live'|'error', incidents: object[], route: object|null, error?: string }>}
 */
export async function fetchLiveRoadData({
  lat = CORRIDOR_CENTER.center[0],
  lng = CORRIDOR_CENTER.center[1],
  region = CORRIDOR_CENTER.region,
  from = DEFAULT_ROUTE.from,
  to = DEFAULT_ROUTE.to,
  fetchImpl = fetch,
} = {}) {
  const [incidentsResult, routeResult] = await Promise.allSettled([
    fetchIncidents({ lat, lng, region, fetchImpl }),
    fetchRouteCheck({ from, to, fetchImpl }),
  ]);

  const incidentsOk = incidentsResult.status === 'fulfilled';
  const routeOk = routeResult.status === 'fulfilled';
  const errors = [];
  if (!incidentsOk) {
    errors.push(
      incidentsResult.reason instanceof Error
        ? incidentsResult.reason.message
        : String(incidentsResult.reason),
    );
  }
  if (!routeOk) {
    errors.push(
      routeResult.reason instanceof Error
        ? routeResult.reason.message
        : String(routeResult.reason),
    );
  }

  if (!incidentsOk && !routeOk) {
    return {
      source: 'error',
      incidents: [],
      route: null,
      error: errors.join('; '),
    };
  }

  return {
    source: 'live',
    incidents: incidentsOk ? incidentsResult.value : [],
    route: routeOk ? routeResult.value : null,
    ...(errors.length ? { error: errors.join('; ') } : {}),
  };
}
