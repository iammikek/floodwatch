import { describe, expect, it } from 'vitest';
import { resolveRouteFromOnLoad } from './defaultBookmarkRoute.js';

describe('resolveRouteFromOnLoad', () => {
  const bookmarks = [
    { id: 2, label: 'Work', location: 'Bridgwater', is_default: false },
    { id: 1, label: 'Home', location: 'Langport', lat: 51.04, lng: -2.83, is_default: true },
  ];

  it('prefers stored route over default bookmark', () => {
    const result = resolveRouteFromOnLoad({
      storedRoute: { from: 'Taunton', to: 'Yeovil' },
      bookmarks,
      fallbackFrom: 'Muchelney',
    });
    expect(result.from).toBe('Taunton');
    expect(result.bookmark).toBeNull();
  });

  it('uses default bookmark when no stored route', () => {
    const result = resolveRouteFromOnLoad({
      storedRoute: null,
      bookmarks,
      fallbackFrom: 'Muchelney',
    });
    expect(result.from).toBe('Langport');
    expect(result.bookmark?.label).toBe('Home');
  });

  it('falls back to corridor default when no bookmark', () => {
    const result = resolveRouteFromOnLoad({
      storedRoute: null,
      bookmarks: [],
      fallbackFrom: 'Muchelney, Somerset',
    });
    expect(result.from).toBe('Muchelney, Somerset');
    expect(result.bookmark).toBeNull();
  });
});
