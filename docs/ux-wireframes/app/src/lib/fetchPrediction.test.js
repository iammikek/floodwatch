import { describe, expect, it, vi } from 'vitest';
import { fetchPrediction, mockPrediction } from '../lib/fetchPrediction.js';

describe('fetchPrediction', () => {
  it('returns mock when no base URL', async () => {
    const { source, doc } = await fetchPrediction('a361-muchelney', { baseUrl: '' });
    expect(source).toBe('mock');
    expect(doc.schema).toBe('floodwatch.prediction.v0');
    expect(doc.prediction.verdict).toBeTruthy();
  });

  it('fetches lake JSON when base URL set', async () => {
    const payload = mockPrediction('stable');
    const fetchImpl = vi.fn(async () => ({
      ok: true,
      json: async () => payload,
    }));
    const { source, doc } = await fetchPrediction('a361-muchelney', {
      baseUrl: 'http://lake.test',
      fetchImpl,
    });
    expect(source).toBe('lake');
    expect(doc.prediction.verdict).toBe('clear');
    expect(fetchImpl).toHaveBeenCalledWith(
      'http://lake.test/v1/predictions?corridor=a361-muchelney',
    );
  });

  it('throws on non-OK lake response', async () => {
    await expect(
      fetchPrediction('a361-muchelney', {
        baseUrl: 'http://lake.test',
        fetchImpl: async () => ({ ok: false, status: 500 }),
      }),
    ).rejects.toThrow(/500/);
  });
});
