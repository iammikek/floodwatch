import { describe, expect, it, vi } from 'vitest';
import { ROUTE_STORAGE_KEY, initialRoute, loadStoredRoute, saveStoredRoute } from './routeStorage.js';
import { DEFAULT_ROUTE } from './fetchLiveRoadData.js';

describe('routeStorage', () => {
  it('returns null when storage is empty', () => {
    const getItem = vi.fn(() => null);
    vi.stubGlobal('localStorage', { getItem, setItem: vi.fn() });
    expect(loadStoredRoute()).toBeNull();
    vi.unstubAllGlobals();
  });

  it('loads and saves a route pair', () => {
    const store = new Map();
    vi.stubGlobal('localStorage', {
      getItem: (k) => store.get(k) ?? null,
      setItem: (k, v) => store.set(k, v),
    });

    saveStoredRoute({ from: 'Langport', to: 'Taunton' });
    expect(JSON.parse(store.get(ROUTE_STORAGE_KEY))).toEqual({
      from: 'Langport',
      to: 'Taunton',
    });
    expect(loadStoredRoute()).toEqual({ from: 'Langport', to: 'Taunton' });
    vi.unstubAllGlobals();
  });

  it('falls back to defaults when nothing stored', () => {
    vi.stubGlobal('localStorage', { getItem: () => null, setItem: vi.fn() });
    expect(initialRoute(DEFAULT_ROUTE)).toEqual(DEFAULT_ROUTE);
    vi.unstubAllGlobals();
  });

  it('ignores invalid stored payloads', () => {
    vi.stubGlobal('localStorage', {
      getItem: () => JSON.stringify({ from: '', to: 'X' }),
      setItem: vi.fn(),
    });
    expect(loadStoredRoute()).toBeNull();
    vi.unstubAllGlobals();
  });
});
