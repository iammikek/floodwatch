import { describe, expect, it, vi } from 'vitest';
import { fetchPrediction, mockPrediction } from '../lib/fetchPrediction.js';

describe('fetchPrediction', () => {
  it('returns mock when preferMock is set', async () => {
    const { source, doc } = await fetchPrediction('a361-muchelney', { preferMock: true });
    expect(source).toBe('mock');
    expect(doc.schema).toBe('floodwatch.prediction.v1');
    expect(doc.prediction.verdict).toBeTruthy();
  });

  it('fetches Laravel JSON when API succeeds', async () => {
    const payload = mockPrediction('stable');
    const fetchImpl = vi.fn(async () => ({
      ok: true,
      json: async () => payload,
    }));
    const { source, doc } = await fetchPrediction('a361-muchelney', { fetchImpl });
    expect(source).toBe('lake');
    expect(doc.prediction.verdict).toBe('clear');
    expect(fetchImpl).toHaveBeenCalledWith(
      '/flood-watch/predictions?corridor=a361-muchelney',
      expect.objectContaining({ credentials: 'same-origin' }),
    );
  });

  it('returns error with no doc when API fails', async () => {
    const { source, doc, error } = await fetchPrediction('a361-muchelney', {
      scenarioId: 'risk',
      fetchImpl: async () => ({ ok: false, status: 503 }),
    });
    expect(source).toBe('error');
    expect(doc).toBeNull();
    expect(error).toMatch(/503/);
  });

  it('returns error with no doc on schema mismatch', async () => {
    const { source, doc, error } = await fetchPrediction('a361-muchelney', {
      fetchImpl: async () => ({
        ok: true,
        json: async () => ({ schema: 'floodwatch.prediction.v0' }),
      }),
    });
    expect(source).toBe('error');
    expect(doc).toBeNull();
    expect(error).toMatch(/schema mismatch/i);
  });
});
