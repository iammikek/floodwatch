import { describe, expect, it, vi } from 'vitest';
import { reverseGeocodeFromCoords } from './reverseGeocode.js';

describe('reverseGeocodeFromCoords', () => {
  it('returns location when Laravel resolves coords', async () => {
    const fetchImpl = vi.fn(async () => ({
      ok: true,
      json: async () => ({
        valid: true,
        in_area: true,
        location: 'Langport',
      }),
    }));

    const result = await reverseGeocodeFromCoords({
      lat: 51.04,
      lng: -2.83,
      fetchImpl,
    });
    expect(result.valid).toBe(true);
    expect(result.inArea).toBe(true);
    expect(result.location).toBe('Langport');
    expect(String(fetchImpl.mock.calls[0][0])).toContain('/flood-watch/reverse-geocode');
  });

  it('surfaces error payload on failure', async () => {
    const result = await reverseGeocodeFromCoords({
      lat: 51.5,
      lng: -0.12,
      fetchImpl: async () => ({
        ok: false,
        status: 422,
        json: async () => ({ error: 'Outside supported area.' }),
      }),
    });
    expect(result.valid).toBe(false);
    expect(result.error).toContain('Outside');
  });
});
