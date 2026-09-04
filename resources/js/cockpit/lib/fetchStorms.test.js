import { describe, expect, it, vi } from 'vitest';
import { fetchStorms } from './fetchStorms.js';

describe('fetchStorms', () => {
  it('loads storm catalogue from Laravel', async () => {
    const fetchImpl = vi.fn(async () => ({
      ok: true,
      json: async () => ({
        storms: [
          {
            id: 'eval-2020-02',
            label: 'Storm Dennis (Feb 2020)',
            as_of: '2020-02-16T12:00:00Z',
          },
        ],
      }),
    }));

    const result = await fetchStorms({
      corridor: 'a361-muchelney',
      fetchImpl,
    });

    expect(fetchImpl).toHaveBeenCalledWith(
      '/flood-watch/storms?corridor=a361-muchelney',
      expect.objectContaining({ credentials: 'same-origin' }),
    );
    expect(result.source).toBe('lake');
    expect(result.items).toHaveLength(1);
  });

  it('returns error source when request fails', async () => {
    const fetchImpl = vi.fn(async () => ({ ok: false, status: 503 }));
    const result = await fetchStorms({ fetchImpl });
    expect(result.source).toBe('error');
    expect(result.items).toEqual([]);
  });
});
