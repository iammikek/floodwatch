import { describe, expect, it, vi } from 'vitest';
import { fetchFloodZones, normalizeFloodZoneCollection } from './fetchFloodZones.js';

describe('normalizeFloodZoneCollection', () => {
  it('unwraps lake { data: FeatureCollection } envelopes', () => {
    const geo = normalizeFloodZoneCollection({
      count: 1,
      data: {
        type: 'FeatureCollection',
        features: [{ type: 'Feature', geometry: { type: 'Polygon', coordinates: [] } }],
      },
    });
    expect(geo.type).toBe('FeatureCollection');
    expect(geo.features).toHaveLength(1);
  });

  it('accepts bare FeatureCollection', () => {
    const geo = normalizeFloodZoneCollection({
      type: 'FeatureCollection',
      features: [{ type: 'Feature', geometry: { type: 'Point', coordinates: [0, 0] } }],
    });
    expect(geo.features).toHaveLength(1);
  });
});

describe('fetchFloodZones', () => {
  it('requests polygons with bbox and returns FeatureCollection', async () => {
    const fetchImpl = vi.fn(async () => ({
      ok: true,
      json: async () => ({
        type: 'FeatureCollection',
        features: [{ type: 'Feature', geometry: { type: 'Polygon', coordinates: [[[0, 0], [1, 0], [1, 1], [0, 0]]] } }],
      }),
    }));
    const geo = await fetchFloodZones({
      center: [51.12, -2.82],
      radiusKm: 10,
      fetchImpl,
    });
    expect(String(fetchImpl.mock.calls[0][0])).toContain('/flood-watch/polygons?');
    expect(String(fetchImpl.mock.calls[0][0])).toContain('bbox=');
    expect(geo.features).toHaveLength(1);
  });
});
