import { describe, expect, it } from 'vitest';
import { boundsFromRouteGeometry } from './fitRouteBounds.js';

describe('boundsFromRouteGeometry', () => {
  it('returns null for short or invalid geometry', () => {
    expect(boundsFromRouteGeometry([])).toBeNull();
    expect(boundsFromRouteGeometry([[-2.8, 51.1]])).toBeNull();
    expect(boundsFromRouteGeometry(null)).toBeNull();
  });

  it('computes south-west and north-east corners', () => {
    const bounds = boundsFromRouteGeometry([
      [-2.82, 51.12],
      [-2.8, 51.14],
      [-2.79, 51.11],
    ]);
    expect(bounds).toEqual([
      [51.11, -2.82],
      [51.14, -2.79],
    ]);
  });
});
