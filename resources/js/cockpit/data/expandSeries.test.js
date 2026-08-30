import { describe, expect, it, vi } from 'vitest';
import { expandHourSeries, seriesForGauge, rainfallSeries, keyGaugeId } from '../data/expandSeries.js';

describe('expandSeries', () => {
  it('expands hour values from seriesStart', () => {
    const pts = expandHourSeries([1, 2, 3], '2026-08-28T21:00:00Z');
    expect(pts).toHaveLength(3);
    expect(pts[0]).toEqual({ t: '2026-08-28T21:00:00.000Z', v: 1 });
    expect(pts[2].v).toBe(3);
  });

  it('reads observables nested on prediction docs', () => {
    const doc = {
      observables: {
        seriesStart: '2026-08-28T21:00:00Z',
        keyGaugeId: 'gauge-muchelney',
        rainfallUpstreamMm: [0.1, 0.2],
        gaugeSeries: { 'gauge-muchelney': [1.1, 1.2] },
      },
    };
    expect(keyGaugeId(doc)).toBe('gauge-muchelney');
    expect(seriesForGauge(doc, 'gauge-muchelney')).toHaveLength(2);
    expect(rainfallSeries(doc)).toHaveLength(2);
  });
});
