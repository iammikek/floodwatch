import { describe, expect, it, vi } from 'vitest';
import {
  fetchLiveRoadData,
  normalizeIncident,
} from './fetchLiveRoadData.js';

describe('fetchLiveRoadData', () => {
  it('normalizes incident rows', () => {
    const row = normalizeIncident({
      id: 'x1',
      road: 'A361',
      status: 'Closed',
      description: 'Flooding',
      lat: 51.1,
      lng: -2.8,
    });
    expect(row.type).toBe('incident');
    expect(row.statusLabel).toBe('Closed');
  });

  it('fetches incidents and route-check via Laravel', async () => {
    const fetchImpl = vi.fn(async (url) => {
      if (String(url).includes('/flood-watch/incidents')) {
        return {
          ok: true,
          json: async () => ({
            items: [
              {
                id: 'i1',
                road: 'A361',
                statusLabel: 'Closed',
                description: 'Flooding',
                lat: 51.11,
                lng: -2.81,
              },
            ],
          }),
        };
      }
      if (String(url).includes('/flood-watch/route-check')) {
        return {
          ok: true,
          json: async () => ({
            verdict: 'clear',
            verdict_label: 'Clear',
            summary: 'No issues.',
            route_geometry: [[-2.82, 51.12], [-2.8, 51.14]],
            from: 'Muchelney, Somerset',
            to: 'Bridgwater, Somerset',
          }),
        };
      }
      return { ok: false, status: 404 };
    });

    const { source, incidents, route } = await fetchLiveRoadData({ fetchImpl });
    expect(source).toBe('live');
    expect(incidents).toHaveLength(1);
    expect(route.verdictLabel).toBe('Clear');
    expect(route.routeGeometry).toHaveLength(2);
  });

  it('passes custom From/To into the route-check query', async () => {
    const fetchImpl = vi.fn(async (url) => {
      if (String(url).includes('/flood-watch/incidents')) {
        return { ok: true, json: async () => ({ items: [] }) };
      }
      if (String(url).includes('/flood-watch/route-check')) {
        expect(String(url)).toContain('from=Langport');
        expect(String(url)).toContain('to=Taunton');
        return {
          ok: true,
          json: async () => ({
            verdict: 'delays',
            verdict_label: 'Delays',
            summary: 'Slow going.',
            route_geometry: [],
            from: 'Langport',
            to: 'Taunton',
          }),
        };
      }
      return { ok: false, status: 404 };
    });

    const { route } = await fetchLiveRoadData({
      fetchImpl,
      from: 'Langport',
      to: 'Taunton',
    });
    expect(route.from).toBe('Langport');
    expect(route.to).toBe('Taunton');
    expect(route.verdictLabel).toBe('Delays');
  });

  it('returns empty road data when both feeds fail', async () => {
    const { source, incidents, route, error } = await fetchLiveRoadData({
      fetchImpl: async () => ({ ok: false, status: 503 }),
    });
    expect(source).toBe('error');
    expect(incidents).toEqual([]);
    expect(route).toBeNull();
    expect(error).toMatch(/503/);
  });
});
