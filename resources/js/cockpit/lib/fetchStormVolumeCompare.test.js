import { describe, expect, it, vi } from 'vitest';
import {
  VOLUME_COMPARE_STORM_IDS,
  fetchStormVolumeCompare,
} from './fetchStormVolumeCompare.js';

describe('fetchStormVolumeCompare', () => {
  it('loads golden storms and keeps available rows', async () => {
    const fetchImpl = vi.fn(async (url) => {
      const id = String(url).match(/storms\/([^/]+)\/volume/)?.[1];
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
    const result = await fetchStormVolumeCompare({
      stormIds: VOLUME_COMPARE_STORM_IDS,
      fetchImpl,
    });
    expect(result.source).toBe('lake');
    expect(result.rows.map((r) => r.stormId)).toEqual([
      'place-2026-01-chandra-levels',
      'eval-2020-02',
      'place-2020-02-ciara',
    ]);
    expect(result.unavailable).toBe(1);
  });
});
