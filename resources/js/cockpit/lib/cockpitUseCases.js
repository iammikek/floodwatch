/**
 * Cockpit use cases — primary product modes (not map layer presets).
 * Each use case owns which panels and map layers mount.
 */

export const USE_CASE_LIVE = 'live_place';
export const USE_CASE_HISTORY = 'historical_place';

/** @typedef {'live_place'|'historical_place'} CockpitUseCaseId */

/**
 * @type {Record<CockpitUseCaseId, {
 *   id: CockpitUseCaseId,
 *   label: string,
 *   description: string,
 *   panels: Record<string, boolean>,
 *   layers: {
 *     route: boolean,
 *     warnings: boolean,
 *     incidents: boolean,
 *     gauges: boolean,
 *     floodZones: boolean,
 *   },
 *   showDispatch: boolean,
 *   showMapPresets: boolean,
 *   floodZonesLabel: string,
 * }>}
 */
export const USE_CASES = {
  [USE_CASE_LIVE]: {
    id: USE_CASE_LIVE,
    label: 'Live',
    description: 'Monitor this place now',
    panels: {
      bookmarks: true,
      stormReplay: false,
      placeHistory: false,
      prediction: true,
      yourRisk: true,
      placeOutlook: true,
      corridorRisk: true,
      floodExposure: true,
      currentRoute: true,
      riverResponse: true,
    },
    layers: {
      route: false,
      warnings: true,
      incidents: false,
      gauges: true,
      floodZones: true,
    },
    showDispatch: true,
    showMapPresets: false,
    floodZonesLabel: 'planning flood zones (FZ2/FZ3)',
  },
  [USE_CASE_HISTORY]: {
    id: USE_CASE_HISTORY,
    label: 'History',
    description: 'Analyse a past storm at this place',
    panels: {
      bookmarks: true,
      // One catalogue UI — PlaceHistoryPanel (richer). StormReplayPanel was the same list as chips.
      stormReplay: false,
      placeHistory: true,
      prediction: true,
      yourRisk: false,
      placeOutlook: false,
      corridorRisk: true,
      floodExposure: false,
      currentRoute: false,
      riverResponse: false,
    },
    layers: {
      route: false,
      warnings: false,
      incidents: false,
      gauges: false,
      floodZones: true,
    },
    showDispatch: false,
    showMapPresets: false,
    floodZonesLabel: 'event impact footprint (approximate)',
  },
};

export const USE_CASE_OPTIONS = [USE_CASES[USE_CASE_LIVE], USE_CASES[USE_CASE_HISTORY]];

/**
 * @param {string|null|undefined} id
 */
export function resolveUseCase(id) {
  if (id && USE_CASES[id]) return USE_CASES[id];
  return USE_CASES[USE_CASE_LIVE];
}

/**
 * Build a preset-shaped object for LeanMap from the active use case.
 * @param {CockpitUseCaseId|string} useCaseId
 */
export function presetForUseCase(useCaseId) {
  const uc = resolveUseCase(useCaseId);
  return {
    id: uc.id,
    label: uc.label,
    layers: { ...uc.layers },
    warningCap: uc.id === USE_CASE_HISTORY ? 0 : 12,
    gaugeCap: uc.id === USE_CASE_HISTORY ? 0 : 16,
  };
}
