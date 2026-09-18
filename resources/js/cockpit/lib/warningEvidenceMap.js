/**
 * Map AfA435 historic warning evidence onto the History LeanMap.
 *
 * Flood-area polygons are not shipped in the cockpit yet, so corridor codes
 * use hand-placed approximate points (WGS84). The A361 East Lyng–Burrowbridge
 * code (112FWFEAS10A) also drives a road-strip severity accent.
 */

/** @type {Record<string, { lat: number, lng: number, roadAccent?: boolean }>} */
export const CORRIDOR_FLOOD_AREA_POINTS = {
  // A361 East Lyng to Burrowbridge — Severe in Feb 2014
  '112FWFEAS10A': { lat: 51.076, lng: -2.91, roadAccent: true },
  // Thorney / Kingsbury Episcopi (2014 code + current)
  '112FWF3C0B': { lat: 51.023, lng: -2.795 },
  '112FWFPAR20A': { lat: 51.023, lng: -2.795 },
  '112FWFPAR10A': { lat: 51.018, lng: -2.81 },
  // Curry Moor / Hay Moor
  '112FWFCUR10A': { lat: 51.045, lng: -2.92 },
  // Muchelney low-lying properties
  '112FWFMTM10A': { lat: 51.02, lng: -2.815 },
  // Moors alerts
  '112WAFYPM': { lat: 51.025, lng: -2.82 },
  '112WAFTPM': { lat: 51.05, lng: -2.88 },
  '112WAFYPL': { lat: 51.04, lng: -2.85 },
  '112WAFYPB': { lat: 51.1, lng: -2.95 },
  '112WAFYPY': { lat: 51.01, lng: -2.78 },
};

/**
 * Approximate A361 centreline (matches lake `api/config/road_lines.py`).
 * Used when volume road samples are not yet available.
 * @type {Array<[number, number]>} [lat, lng]
 */
export const A361_SEVERITY_FALLBACK_LINE = [
  [51.07, -2.93],
  [51.073, -2.92],
  [51.076, -2.91],
  [51.08, -2.91],
  [51.082, -2.895],
  [51.08, -2.88],
  [51.078, -2.87],
  [51.08, -2.86],
  [51.082, -2.85],
];

/**
 * @param {unknown} evidence
 * @returns {Array<Record<string, unknown>>}
 */
export function warningEvidenceItems(evidence) {
  if (!evidence || typeof evidence !== 'object') return [];
  const items = /** @type {{ items?: unknown }} */ (evidence).items;
  return Array.isArray(items) ? items.filter((it) => it && typeof it === 'object') : [];
}

/**
 * One marker per flood area: worst severity, then latest issue date.
 * @param {unknown} evidence
 * @returns {Array<{
 *   id: string,
 *   floodAreaID: string,
 *   title: string,
 *   severity: string,
 *   severityLevel: number,
 *   issued_at: string|null,
 *   lat: number,
 *   lng: number,
 *   roadAccent: boolean,
 *   type: 'historic-warning',
 * }>}
 */
export function mapWarningEvidenceForMap(evidence) {
  const byArea = new Map();
  for (const raw of warningEvidenceItems(evidence)) {
    const it = /** @type {Record<string, unknown>} */ (raw);
    const floodAreaID = String(it.floodAreaID ?? it.flood_area_id ?? '').trim();
    if (!floodAreaID) continue;
    const point = CORRIDOR_FLOOD_AREA_POINTS[floodAreaID];
    if (!point) continue;

    const severityLevel = Number(it.severityLevel ?? it.severity_level ?? 4);
    const level = Number.isFinite(severityLevel) ? severityLevel : 4;
    const issued = it.issued_at ? String(it.issued_at) : null;
    const prev = byArea.get(floodAreaID);
    if (prev) {
      if (level > prev.severityLevel) continue;
      if (level === prev.severityLevel && issued && prev.issued_at && issued < prev.issued_at) {
        continue;
      }
    }

    byArea.set(floodAreaID, {
      id: `afa435-${floodAreaID}`,
      floodAreaID,
      title: String(it.title ?? floodAreaID),
      severity: String(it.severity ?? 'Flood Alert'),
      severityLevel: level,
      issued_at: issued,
      lat: point.lat,
      lng: point.lng,
      roadAccent: Boolean(point.roadAccent),
      type: 'historic-warning',
    });
  }

  return [...byArea.values()].sort(
    (a, b) => a.severityLevel - b.severityLevel || a.floodAreaID.localeCompare(b.floodAreaID),
  );
}

/**
 * @param {unknown} evidence
 * @returns {{ total: number, severe: number, warning: number, alert: number, peakLevel: number|null, roadAccent: boolean }}
 */
export function summarizeWarningEvidenceMap(evidence) {
  const markers = mapWarningEvidenceForMap(evidence);
  let severe = 0;
  let warning = 0;
  let alert = 0;
  let peakLevel = null;
  let roadAccent = false;
  for (const m of markers) {
    if (m.severityLevel === 1) severe += 1;
    else if (m.severityLevel === 2) warning += 1;
    else alert += 1;
    if (peakLevel == null || m.severityLevel < peakLevel) peakLevel = m.severityLevel;
    if (m.roadAccent && m.severityLevel <= 2) roadAccent = true;
  }
  // Also accent when any severe exists even if not on the A361 code
  if (severe > 0) {
    const hasA361 = markers.some((m) => m.roadAccent);
    if (hasA361) roadAccent = true;
  }
  return {
    total: markers.length,
    severe,
    warning,
    alert,
    peakLevel,
    roadAccent,
  };
}

/**
 * @param {number} level
 * @returns {'historic-severe'|'historic-warning'|'historic-alert'}
 */
export function historicWarningMarkerKind(level) {
  if (level === 1) return 'historic-severe';
  if (level === 2) return 'historic-warning';
  return 'historic-alert';
}

/**
 * @param {unknown} evidence
 * @returns {boolean}
 */
export function shouldAccentA361ForEvidence(evidence) {
  return summarizeWarningEvidenceMap(evidence).roadAccent;
}
