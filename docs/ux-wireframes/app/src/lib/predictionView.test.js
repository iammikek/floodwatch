import { describe, expect, it } from 'vitest';
import { gaugesFromPrediction } from './predictionView.js';

describe('gaugesFromPrediction', () => {
  it('builds gauge rows from observables + drivers', () => {
    const doc = {
      as_of: '2026-08-29T09:00:00Z',
      drivers: [
        {
          type: 'gauge_trajectory',
          ref: 'gauge-gaw-bridge',
          label: 'Gaw Bridge · River Parrett',
          signal: 'rising_toward_high',
        },
      ],
      observables: {
        primaryAnalysis: { p95: 1.9 },
        gaugeSeries: { 'gauge-gaw-bridge': [1.1, 1.2] },
      },
    };
    const rows = gaugesFromPrediction(doc);
    expect(rows).toHaveLength(1);
    expect(rows[0].station).toBe('Gaw Bridge');
    expect(rows[0].levelStatus).toBe('elevated');
    expect(rows[0].value).toBe(1.2);
  });
});
