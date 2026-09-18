import { describe, expect, it, vi } from 'vitest';
import { fetchStormVolumeCompare } from './fetchStormVolumeCompare.js';

describe('fetchStormVolumeCompare', () => {
  it('resolves compare membership from lake storms then loads volumes', async () => {
    const fetchImpl = vi.fn(async (url) => {
      const href = String(url);
      if (href.includes('/flood-watch/storms?') && href.includes('volume_compare=true')) {
        return {
          ok: true,
          json: async () => ({
            storms: [
              { id: 'place-2026-01-chandra-levels', volume_compare: true },
              { id: 'eval-2020-02', volume_compare: true },
              { id: 'eval-2014-01', volume_compare: true },
              { id: 'place-2020-02-ciara', volume_compare: true },
            ],
          }),
        };
      }
      const id = href.match(/storms\/([^/]+)\/volume/)?.[1];
      const available = id !== 'eval-2014-01';
      return {
        ok: true,
        json: async () => ({
          stormId: id,
          stormLabel: id,
          available,
          prediction: available
            ? { areaKm2: 10, meanDepthM: 1, volumeMm3: 5, volumeM3: 5e6 }
            : null,
          road: available ? { available: true, maxDepthM: 1.2, thresholdsM: { 1: 2000 } } : null,
        }),
      };
    });
    const result = await fetchStormVolumeCompare({ fetchImpl });
    expect(result.source).toBe('lake');
    expect(result.rows.map((r) => r.stormId)).toEqual([
      'place-2026-01-chandra-levels',
      'eval-2020-02',
      'place-2020-02-ciara',
    ]);
    expect(result.unavailable).toBe(1);
    expect(fetchImpl.mock.calls[0][0]).toContain('volume_compare=true');
  });

  it('honours explicit stormIds without catalogue fetch', async () => {
    const fetchImpl = vi.fn(async (url) => {
      const id = String(url).match(/storms\/([^/]+)\/volume/)?.[1];
      return {
        ok: true,
        json: async () => ({
          stormId: id,
          available: true,
          prediction: { areaKm2: 1, meanDepthM: 0.5, volumeMm3: 1, volumeM3: 1e6 },
          road: { available: false },
        }),
      };
    });
    const result = await fetchStormVolumeCompare({
      stormIds: ['eval-2020-02'],
      fetchImpl,
    });
    expect(result.rows).toHaveLength(1);
    expect(fetchImpl).toHaveBeenCalledTimes(1);
    expect(String(fetchImpl.mock.calls[0][0])).toContain('/volume');
  });
});
