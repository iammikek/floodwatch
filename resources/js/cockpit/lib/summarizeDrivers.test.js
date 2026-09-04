import { describe, expect, it } from 'vitest';
import { summarizeDrivers } from './summarizeDrivers.js';

describe('summarizeDrivers', () => {
  it('collapses same-month analogues and skips no_data gauges', () => {
    const summary = summarizeDrivers([
      {
        type: 'gauge_trajectory',
        label: 'Gaw Bridge · River Parrett',
        signal: 'elevated_and_rising',
      },
      {
        type: 'gauge_trajectory',
        label: 'Midelney · River Isle',
        signal: 'no_data',
      },
      {
        type: 'historic_analogue',
        label: 'Aug 2026 analogue',
        ref: '2026-08-10T12:00:00Z',
        similarity: 0.98,
        outcome: 'clear',
      },
      {
        type: 'historic_analogue',
        label: 'Aug 2026 analogue',
        ref: '2026-08-10T13:00:00Z',
        similarity: 0.97,
        outcome: 'clear',
      },
      {
        type: 'historic_analogue',
        label: 'Feb 2020 analogue',
        ref: '2020-02-16T12:00:00Z',
        similarity: 0.9,
        outcome: 'impact',
      },
      {
        type: 'analogue_consensus',
        ref: 'k3',
        label: '3 matched windows',
        impactRate: 0.33,
        watchRate: 0,
      },
    ]);

    expect(summary.gauges).toHaveLength(1);
    expect(summary.gauges[0].signal).toBe('Elevated & rising');
    expect(summary.analogues).toHaveLength(2);
    expect(summary.analogues[0].label).toBe('Aug 2026');
    expect(summary.analogues[0].detail).toContain('2 hours');
    expect(summary.analogues[0].detail).toContain('clear');
    expect(summary.consensus?.detail).toContain('Impact 33%');
    expect(summary.matchedCount).toBe(3);
  });
});
