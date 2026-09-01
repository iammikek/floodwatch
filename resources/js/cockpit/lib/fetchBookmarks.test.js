import { describe, expect, it, vi } from 'vitest';
import { fetchBookmarks } from './fetchBookmarks.js';

describe('fetchBookmarks', () => {
  it('loads bookmark items from Laravel', async () => {
    const fetchImpl = vi.fn(async () => ({
      ok: true,
      json: async () => ({
        authenticated: true,
        items: [
          { id: 1, label: 'Home', location: 'TA10 0DP', is_default: true },
        ],
      }),
    }));

    const result = await fetchBookmarks({ fetchImpl });
    expect(result.items).toHaveLength(1);
    expect(result.items[0].label).toBe('Home');
    expect(result.items[0].is_default).toBe(true);
    expect(result.authenticated).toBe(true);
  });
});
