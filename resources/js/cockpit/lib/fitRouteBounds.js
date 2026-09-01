/**
 * Compute Leaflet-style bounds from route geometry [[lng, lat], ...].
 *
 * @param {Array<[number, number]>} routeGeometry
 * @returns {[[number, number], [number, number]] | null} [[south, west], [north, east]]
 */
export function boundsFromRouteGeometry(routeGeometry) {
  if (!Array.isArray(routeGeometry) || routeGeometry.length < 2) return null;

  let minLat = Infinity;
  let maxLat = -Infinity;
  let minLng = Infinity;
  let maxLng = -Infinity;

  for (const point of routeGeometry) {
    if (!Array.isArray(point) || point.length < 2) continue;
    const lng = Number(point[0]);
    const lat = Number(point[1]);
    if (!Number.isFinite(lat) || !Number.isFinite(lng)) continue;
    minLat = Math.min(minLat, lat);
    maxLat = Math.max(maxLat, lat);
    minLng = Math.min(minLng, lng);
    maxLng = Math.max(maxLng, lng);
  }

  if (!Number.isFinite(minLat)) return null;
  return [
    [minLat, minLng],
    [maxLat, maxLng],
  ];
}
