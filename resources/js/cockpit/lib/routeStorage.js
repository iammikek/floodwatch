/**
 * Persist cockpit route From/To in localStorage (same-origin, operator convenience).
 */

export const ROUTE_STORAGE_KEY = 'flood-watch-cockpit-route';

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
