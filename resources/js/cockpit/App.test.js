import { describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';

vi.mock('./lib/fetchPrediction.js', () => ({
  fetchPrediction: vi.fn(async () => ({
    source: 'lake',
    doc: {
      schema: 'floodwatch.prediction.v1',
      corridor: { id: 'a361-muchelney', label: 'A361 Muchelney corridor' },
      prediction: {
        verdict: 'watch',
        verdictLabel: 'Watch corridor',
        timeToImpactHours: 4,
        impactWindow: {
          from: '2026-08-29T15:00:00Z',
          to: '2026-08-29T21:00:00Z',
        },
        confidence: 0.64,
        confidenceLabel: 'Medium',
        summary: 'Historic analogue watch.',
      },
      drivers: [],
      affectedAreas: [],
      dispatch: { implication: 'Watch closely', safeToPass: false },
      method: { name: 'historic_analogue_v1', notes: 'Analogue matcher' },
      observables: { rainfallUpstreamMm: [], gaugeSeries: {}, primaryAnalysis: { p95: 2.1 } },
    },
  })),
}));

vi.mock('./lib/fetchLiveMapData.js', () => ({
  CORRIDOR_CENTER: {
    center: [51.0, -2.8],
    zoom: 11,
    label: 'Muchelney corridor',
    radiusKm: 8,
  },
  fetchLiveMapData: vi.fn(async () => ({
    source: 'lake',
    gauges: [],
    floods: [],
  })),
}));

vi.mock('./lib/fetchLiveRoadData.js', () => ({
  DEFAULT_ROUTE: { from: 'TA10 0DP', to: 'TA7 0SB' },
  fetchLiveRoadData: vi.fn(async () => ({
    source: 'live',
    incidents: [],
    route: {
      verdict: 'clear',
      verdictLabel: 'Clear',
      summary: 'Route is clear.',
      routeGeometry: [],
      from: 'TA10 0DP',
      to: 'TA7 0SB',
    },
  })),
  fetchRouteCheck: vi.fn(async () => ({
    verdict: 'clear',
    verdictLabel: 'Clear',
    summary: 'Route is clear.',
    routeGeometry: [],
    from: 'TA10 0DP',
    to: 'TA7 0SB',
  })),
}));

vi.mock('./lib/routeStorage.js', () => ({
  initialRoute: vi.fn((fallback) => fallback),
  loadRecentRoutes: vi.fn(() => []),
  loadStoredRoute: vi.fn(() => ({ from: '', to: 'TA7 0SB' })),
  rememberRecentRoute: vi.fn(() => []),
  saveStoredRoute: vi.fn(),
}));

vi.mock('./lib/fetchBookmarks.js', () => ({
  fetchBookmarks: vi.fn(async () => ({
    authenticated: true,
    items: [],
  })),
}));

vi.mock('./lib/fetchStorms.js', () => ({
  fetchStorms: vi.fn(async () => ({
    source: 'lake',
    items: [
      {
        id: 'eval-2020-02',
        label: 'Storm Dennis (Feb 2020)',
        as_of: '2020-02-16T12:00:00Z',
        notes: 'test',
      },
    ],
  })),
}));

vi.mock('./lib/defaultBookmarkRoute.js', () => ({
  resolveRouteFromOnLoad: vi.fn(({ fallbackFrom }) => ({
    from: fallbackFrom,
    bookmark: null,
  })),
}));

import App from './App.vue';

async function flushApp() {
  await Promise.resolve();
  await Promise.resolve();
  await nextTick();
  await Promise.resolve();
  await nextTick();
}

describe('Cockpit App', () => {
  it('renders place-first main column with prediction ahead of map', async () => {
    const wrapper = mount(App, {
      global: {
        stubs: {
          LeanMap: { template: '<div class="lean-map-stub">Map</div>' },
        },
      },
    });

    await flushApp();

    expect(wrapper.text()).toContain('Monitor place');
    expect(wrapper.text()).not.toContain('Route check');
    expect(wrapper.text()).toContain('Storm replay');
    expect(wrapper.text()).toContain('Historical incidents here');

    const classOrder = Array.from(wrapper.find('.main').element.children).map((el) => el.className);
    expect(classOrder[0]).toContain('primary-panel');
    expect(classOrder.some((c) => c.includes('map-shell'))).toBe(true);
  });
});
