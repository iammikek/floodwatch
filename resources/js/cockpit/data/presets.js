/**
 * Map layer presets. Place mode is the default product surface;
 * route-oriented presets are retained for the later Check route alternate view.
 */
export const PRESETS = {
  place: {
    id: 'place',
    label: 'Place',
    layers: {
      route: false,
      warnings: true,
      incidents: false,
      gauges: true,
    },
    warningCap: 12,
    gaugeCap: 16,
  },
  dispatch: {
    id: 'dispatch',
    label: 'Dispatch',
    layers: {
      route: true,
      warnings: true,
      incidents: true,
      gauges: false,
    },
    warningCap: 8,
  },
  hydrology: {
    id: 'hydrology',
    label: 'Hydrology',
    layers: {
      route: false,
      warnings: true,
      incidents: false,
      gauges: true,
    },
    warningCap: 5,
    gaugeCap: 12,
  },
  minimal: {
    id: 'minimal',
    label: 'Minimal',
    layers: {
      route: false,
      warnings: false,
      incidents: false,
      gauges: false,
    },
    warningCap: 0,
  },
};

/** Default preset for the place-first cockpit. */
export const PLACE_PRESET_ID = 'place';
