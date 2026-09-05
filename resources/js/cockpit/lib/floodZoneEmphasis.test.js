import { describe, expect, it } from 'vitest';
import {
  bboxIntersects,
  emphasizeFloodZones,
  floodZoneStyleForFeature,
  normalizeEventSeverity,
  normalizeImpactBbox,
  normalizeImpactGeometry,
} from './floodZoneEmphasis.js';

const sample = {
  type: 'FeatureCollection',
  features: [
    {
      type: 'Feature',
      properties: { flood_zone: 'FZ2' },
      geometry: {
        type: 'Polygon',
        coordinates: [
          [
            [-2.96, 51.05],
            [-2.94, 51.05],
            [-2.94, 51.07],
            [-2.96, 51.07],
            [-2.96, 51.05],
          ],
        ],
      },
    },
    {
      type: 'Feature',
      properties: { flood_zone: 'FZ3' },
      geometry: {
        type: 'Polygon',
        coordinates: [
          [
            [-2.84, 51.11],
            [-2.82, 51.11],
            [-2.82, 51.13],
            [-2.84, 51.13],
            [-2.84, 51.11],
          ],
        ],
      },
    },
  ],
};

describe('floodZoneEmphasis', () => {
  it('keeps both zones for live / high without impact clip', () => {
    expect(emphasizeFloodZones(sample, null).features).toHaveLength(2);
    expect(emphasizeFloodZones(sample, { severity: 'high' }).features).toHaveLength(2);
  });

  it('drops FZ2 for low severity control events', () => {
    const out = emphasizeFloodZones(sample, { severity: 'low' });
    expect(out.features).toHaveLength(1);
    expect(out.features[0].properties.flood_zone).toBe('FZ3');
  });

  it('returns empty features when bounds_mode is none', () => {
    expect(
      emphasizeFloodZones(sample, { bounds_mode: 'none', severity: 'low' }).features,
    ).toHaveLength(0);
  });

  it('clips to curated impact_geometry polygons', () => {
    const tightGeom = {
      type: 'FeatureCollection',
      features: [
        {
          type: 'Feature',
          properties: { kind: 'curated_impact_v0' },
          geometry: {
            type: 'Polygon',
            coordinates: [
              [
                [-2.85, 51.10],
                [-2.80, 51.10],
                [-2.80, 51.14],
                [-2.85, 51.14],
                [-2.85, 51.10],
              ],
            ],
          },
        },
      ],
    };
    const tight = emphasizeFloodZones(sample, {
      severity: 'high',
      bounds_mode: 'impact',
      impact_geometry: tightGeom,
    });
    expect(tight.features).toHaveLength(1);
    expect(tight.features[0].properties.flood_zone).toBe('FZ3');
    expect(normalizeImpactGeometry({ bounds_mode: 'impact', impact_geometry: tightGeom })?.features).toHaveLength(
      1,
    );
  });

  it('clips to curated impact_bbox so event footprints differ', () => {
    const tight = emphasizeFloodZones(sample, {
      severity: 'high',
      bounds_mode: 'impact',
      impact_bbox: [-2.85, 51.10, -2.80, 51.14],
    });
    expect(tight.features).toHaveLength(1);
    expect(tight.features[0].properties.flood_zone).toBe('FZ3');

    const wide = emphasizeFloodZones(sample, {
      severity: 'high',
      bounds_mode: 'impact',
      impact_bbox: [-2.98, 51.02, -2.68, 51.22],
    });
    expect(wide.features).toHaveLength(2);
  });

  it('styles high emphasis stronger than live', () => {
    const live = floodZoneStyleForFeature({
      properties: { flood_zone: 'FZ3', _emphasis: 'live' },
    });
    const high = floodZoneStyleForFeature({
      properties: { flood_zone: 'FZ3', _emphasis: 'high' },
    });
    expect(high.fillOpacity).toBeGreaterThan(live.fillOpacity);
  });

  it('normalizes impact bbox and severity helpers', () => {
    expect(normalizeEventSeverity('weird')).toBe('live');
    expect(normalizeImpactBbox([-2.9, 51.1, -2.8, 51.2])).toEqual([-2.9, 51.1, -2.8, 51.2]);
    expect(normalizeImpactBbox([1, 2, 3])).toBeNull();
    expect(bboxIntersects([-2.9, 51.1, -2.8, 51.2], [-2.85, 51.15, -2.7, 51.3])).toBe(true);
  });
});
