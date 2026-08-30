import { describe, expect, it, vi } from 'vitest';
import {
  bboxFromCenter,
  centroidFromGeometry,
  fetchLiveMapData,
  normalizeGauge,
  normalizeWarning,
  severityToLevel,
} from './fetchLiveMapData.js';

describe('fetchLiveMapData helpers', () => {
  it('builds bbox around center', () => {
    const bbox = bboxFromCenter([51.12, -2.82], 20);
    const [minLng, minLat, maxLng, maxLat] = bbox.split(',').map(Number);
    expect(minLat).toBeLessThan(51.12);
    expect(maxLat).toBeGreaterThan(51.12);
    expect(minLng).toBeLessThan(-2.82);
    expect(maxLng).toBeGreaterThan(-2.82);
  });

  it('normalizes gauge and warning rows', () => {
    const gauge = normalizeGauge({
      station: 'Gaw Bridge',
      river: 'Parrett',
      value: 2.1,
      lat: 51.1,
      lng: -2.8,
      levelStatus: 'elevated',
    });
    expect(gauge.type).toBe('gauge');
    expect(gauge.id).toContain('Gaw Bridge');

    const warn = normalizeWarning({
      floodAreaID: '112FW',
      severityLevel: 2,
      description: 'Flooding',
      lat: 51.11,
      lng: -2.81,
    });
    expect(warn.type).toBe('warning');
    expect(warn.id).toBe('112FW');
  });

  it('derives map coords from lake Warning geometry + severity text', () => {
    expect(severityToLevel('Flood Warning', null)).toBe(2);
    const [lat, lng] = centroidFromGeometry({
      type: 'Polygon',
      coordinates: [
        [
          [-2.85, 51.1],
          [-2.8, 51.1],
          [-2.8, 51.14],
          [-2.85, 51.14],
          [-2.85, 51.1],
        ],
      ],
    });
    expect(lat).toBeCloseTo(51.12, 2);
    expect(lng).toBeCloseTo(-2.825, 2);

    const lakeWarn = normalizeWarning({
      id: 'http://ea.example/floods/1',
      severity: 'Flood Alert',
      title: 'Somerset Levels',
      geometry: { type: 'Point', coordinates: [-2.82, 51.12] },
    });
    expect(lakeWarn.description).toBe('Somerset Levels');
    expect(lakeWarn.severityLevel).toBe(3);
    expect(lakeWarn.lat).toBe(51.12);
    expect(lakeWarn.lng).toBe(-2.82);
  });

  it('fetches lake gauges and warnings via Laravel proxies', async () => {
    const fetchImpl = vi.fn(async (url) => {
      if (String(url).includes('/flood-watch/river-levels')) {
        return {
          ok: true,
          json: async () => [
            {
              station: 'Gaw Bridge',
              river: 'Parrett',
              value: 2.2,
              lat: 51.1,
              lng: -2.8,
              levelStatus: 'elevated',
              dateTime: '2026-08-29T12:00:00Z',
            },
          ],
        };
      }
      if (String(url).includes('/api/lake/warnings')) {
        return {
          ok: true,
          json: async () => ({
            items: [
              {
                floodAreaID: 'A1',
                severity: 'Flood Warning',
                severityLevel: 2,
                description: 'Parrett',
                lat: 51.12,
                lng: -2.82,
              },
            ],
          }),
        };
      }
      return { ok: false, status: 404 };
    });

    const { source, gauges, floods } = await fetchLiveMapData({ fetchImpl });
    expect(source).toBe('lake');
    expect(gauges).toHaveLength(1);
    expect(gauges[0].station).toBe('Gaw Bridge');
    expect(floods).toHaveLength(1);
    expect(floods[0].type).toBe('warning');
  });

  it('falls back to mock overlays when both requests fail', async () => {
    const { source, gauges, floods, error } = await fetchLiveMapData({
      fetchImpl: async () => ({ ok: false, status: 503 }),
      mockGauges: [{ id: 'g1', type: 'gauge', station: 'Mock', lat: 1, lng: 2, levelStatus: 'low' }],
      mockFloods: [{ id: 'w1', type: 'warning', lat: 1, lng: 2, description: 'Mock' }],
    });
    expect(source).toBe('mock');
    expect(gauges[0].id).toBe('g1');
    expect(floods[0].id).toBe('w1');
    expect(error).toMatch(/503/);
  });

  it('keeps lake source when only one feed fails', async () => {
    const fetchImpl = vi.fn(async (url) => {
      if (String(url).includes('/flood-watch/river-levels')) {
        return {
          ok: true,
          json: async () => [
            { station: 'Gaw Bridge', value: 1, lat: 51.1, lng: -2.8, levelStatus: 'expected' },
          ],
        };
      }
      return { ok: false, status: 502 };
    });
    const { source, gauges, floods, error } = await fetchLiveMapData({
      fetchImpl,
      mockFloods: [{ id: 'fallback-warn', type: 'warning', lat: 1, lng: 2 }],
    });
    expect(source).toBe('lake');
    expect(gauges[0].station).toBe('Gaw Bridge');
    expect(floods[0].id).toBe('fallback-warn');
    expect(error).toMatch(/502/);
  });
});
