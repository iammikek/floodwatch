import { describe, expect, it, vi } from 'vitest';
import { fetchStormVolume } from './fetchStormVolume.js';

describe('fetchStormVolume', () => {
  it('returns lake source when available', async () => {
    const fetchImpl = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({
        schema: 'floodwatch.storm_volume.v0',
        available: true,
        prediction: { volumeM3: 1000 },
      }),
    });
    const result = await fetchStormVolume({
      stormId: 'eval-2020-02',
      place: 'a361-muchelney',
      fetchImpl,
    });
    expect(result.source).toBe('lake');
    expect(result.doc.available).toBe(true);
    expect(fetchImpl).toHaveBeenCalledWith(
      '/flood-watch/storms/eval-2020-02/volume?place=a361-muchelney',
      expect.objectContaining({ credentials: 'same-origin' }),
    );
  });

  it('marks unavailable when lake says so', async () => {
    const fetchImpl = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({ available: false, reason: 'no_impact_geometry' }),
    });
    const result = await fetchStormVolume({ stormId: 'eval-stable-summer', fetchImpl });
    expect(result.source).toBe('unavailable');
    expect(result.doc.reason).toBe('no_impact_geometry');
  });
});
