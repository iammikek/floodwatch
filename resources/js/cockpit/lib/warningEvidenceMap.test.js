import { describe, expect, it } from 'vitest';
import {
  A361_SEVERITY_FALLBACK_LINE,
  historicWarningMarkerKind,
  mapWarningEvidenceForMap,
  shouldAccentA361ForEvidence,
  summarizeWarningEvidenceMap,
} from './warningEvidenceMap.js';

const evidence2014 = {
  schema: 'floodwatch.storm_warning_evidence.v0',
  items: [
    {
      floodAreaID: '112FWF3C0B',
      title: 'River Parrett (upper) at Thorney and Kingsbury Episcopi',
      severity: 'Flood Warning',
      severityLevel: 2,
      issued_at: '2014-01-01T12:00:00Z',
    },
    {
      floodAreaID: '112FWF3C0B',
      title: 'River Parrett (upper) at Thorney and Kingsbury Episcopi',
      severity: 'Flood Warning',
      severityLevel: 2,
      issued_at: '2014-01-04T12:00:00Z',
    },
    {
      floodAreaID: '112FWFEAS10A',
      title: 'A361 East Lyng to Burrowbridge',
      severity: 'Severe Flood Warning',
      severityLevel: 1,
      issued_at: '2014-02-05T12:00:00Z',
    },
  ],
};

describe('warningEvidenceMap', () => {
  it('dedupes flood areas keeping worst severity then latest issue', () => {
    const markers = mapWarningEvidenceForMap(evidence2014);
    expect(markers).toHaveLength(2);
    const thorney = markers.find((m) => m.floodAreaID === '112FWF3C0B');
    expect(thorney?.issued_at).toBe('2014-01-04T12:00:00Z');
    const a361 = markers.find((m) => m.floodAreaID === '112FWFEAS10A');
    expect(a361?.severityLevel).toBe(1);
    expect(a361?.lat).toBeCloseTo(51.076);
    expect(a361?.roadAccent).toBe(true);
  });

  it('summarizes 2014 Severe and accents A361', () => {
    const summary = summarizeWarningEvidenceMap(evidence2014);
    expect(summary.total).toBe(2);
    expect(summary.severe).toBe(1);
    expect(summary.warning).toBe(1);
    expect(summary.peakLevel).toBe(1);
    expect(summary.roadAccent).toBe(true);
    expect(shouldAccentA361ForEvidence(evidence2014)).toBe(true);
  });

  it('does not accent A361 for Dennis moors-only alerts', () => {
    const dennis = {
      items: [
        {
          floodAreaID: '112WAFYPM',
          title: 'River Yeo and River Parrett Moors around Muchelney and Thorney',
          severity: 'Flood Alert',
          severityLevel: 3,
          issued_at: '2020-02-13T12:00:00Z',
        },
        {
          floodAreaID: '112FWFCUR10A',
          title: 'Curry Moor and Hay Moor',
          severity: 'Flood Warning',
          severityLevel: 2,
          issued_at: '2020-02-16T12:00:00Z',
        },
      ],
    };
    expect(shouldAccentA361ForEvidence(dennis)).toBe(false);
    expect(summarizeWarningEvidenceMap(dennis).warning).toBe(1);
    expect(mapWarningEvidenceForMap(dennis)).toHaveLength(2);
  });

  it('returns empty for curated-empty evidence', () => {
    expect(mapWarningEvidenceForMap({ items: [] })).toEqual([]);
    expect(shouldAccentA361ForEvidence({ items: [] })).toBe(false);
    expect(summarizeWarningEvidenceMap(null).total).toBe(0);
  });

  it('maps marker kinds and keeps a fallback A361 line', () => {
    expect(historicWarningMarkerKind(1)).toBe('historic-severe');
    expect(historicWarningMarkerKind(2)).toBe('historic-warning');
    expect(historicWarningMarkerKind(3)).toBe('historic-alert');
    expect(A361_SEVERITY_FALLBACK_LINE.length).toBeGreaterThanOrEqual(2);
  });
});
