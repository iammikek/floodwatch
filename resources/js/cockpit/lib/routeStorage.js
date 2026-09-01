/**
 * Persist cockpit route From/To in localStorage (same-origin, operator convenience).
 */

export const ROUTE_STORAGE_KEY = 'flood-watch-cockpit-route';
export const ROUTE_HISTORY_KEY = 'flood-watch-cockpit-route-history';
export const MAX_RECENT_ROUTES = 5;

/**
 * @returns {Array<{ from: string, to: string }>}
 */
export function loadRecentRoutes() {
  if (typeof localStorage === 'undefined') return [];
  try {
    const raw = localStorage.getItem(ROUTE_HISTORY_KEY);
    if (!raw) return [];
    const parsed = JSON.parse(raw);
    if (!Array.isArray(parsed)) return [];
    return parsed
      .map((row) => ({
        from: typeof row?.from === 'string' ? row.from.trim() : '',
        to: typeof row?.to === 'string' ? row.to.trim() : '',
      }))
      .filter((row) => row.from && row.to)
      .slice(0, MAX_RECENT_ROUTES);
  } catch {
    return [];
  }
}

/**
 * @param {Array<{ from: string, to: string }>} routes
 */
function saveRecentRoutes(routes) {
  if (typeof localStorage === 'undefined') return;
  try {
    localStorage.setItem(ROUTE_HISTORY_KEY, JSON.stringify(routes.slice(0, MAX_RECENT_ROUTES)));
  } catch {
    // ignore quota / private mode
  }
}

/**
 * @param {{ from: string, to: string }} route
 * @returns {Array<{ from: string, to: string }>}
 */
export function rememberRecentRoute(route) {
  const from = route.from?.trim?.() ?? '';
  const to = route.to?.trim?.() ?? '';
  if (!from || !to) return loadRecentRoutes();

  const next = [{ from, to }, ...loadRecentRoutes().filter((r) => r.from !== from || r.to !== to)].slice(
    0,
    MAX_RECENT_ROUTES,
  );
  saveRecentRoutes(next);
  return next;
}

/**
 * @returns {{ from: string, to: string } | null}
 */
export function loadStoredRoute() {
  if (typeof localStorage === 'undefined') return null;
  try {
    const raw = localStorage.getItem(ROUTE_STORAGE_KEY);
    if (!raw) return null;
    const parsed = JSON.parse(raw);
    const from = typeof parsed?.from === 'string' ? parsed.from.trim() : '';
    const to = typeof parsed?.to === 'string' ? parsed.to.trim() : '';
    if (!from || !to) return null;
    return { from, to };
  } catch {
    return null;
  }
}

/**
 * @param {{ from: string, to: string }} route
 */
export function saveStoredRoute(route) {
  if (typeof localStorage === 'undefined') return;
  const from = route.from?.trim?.() ?? '';
  const to = route.to?.trim?.() ?? '';
  if (!from || !to) return;
  try {
    localStorage.setItem(ROUTE_STORAGE_KEY, JSON.stringify({ from, to }));
  } catch {
    // ignore quota / private mode
  }
}

/**
 * @param {import('./fetchLiveRoadData.js').DEFAULT_ROUTE} defaults
 * @returns {{ from: string, to: string }}
 */
export function initialRoute(defaults) {
  return loadStoredRoute() ?? { from: defaults.from, to: defaults.to };
}
