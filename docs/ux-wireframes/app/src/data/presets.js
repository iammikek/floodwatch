export const PRESETS = {
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
      route: true,
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
      route: true,
      warnings: false,
      incidents: false,
      gauges: false,
    },
    warningCap: 0,
  },
};
