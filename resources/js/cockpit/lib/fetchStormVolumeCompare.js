/**
 * Golden storms for History volume comparison (impact events with LiDAR coverage).
 * Chandra first — founding place event.
 */
import { fetchStormVolume } from './fetchStormVolume.js';

export const VOLUME_COMPARE_STORM_IDS = [
  'place-2026-01-chandra-levels',
  'eval-2020-02',
  'eval-2014-01',
  'place-2020-02-ciara',
];

/**
 * Load volume docs for several storms in parallel.
 * @param {{ stormIds?: string[], place?: string, fetchImpl?: typeof fetch }} [opts]
 */
export async function fetchStormVolumeCompare({
  stormIds = VOLUME_COMPARE_STORM_IDS,
  place = 'a361-muchelney',
  fetchImpl = fetch,
} = {}) {
  const results = await Promise.all(
    stormIds.map(async (stormId) => {
      const result = await fetchStormVolume({ stormId, place, fetchImpl });
      return { stormId, ...result };
    }),
  );
  const rows = results.filter((r) => r.doc?.available && r.doc?.prediction);
  const errors = results.filter((r) => r.source === 'error').length;
  const unavailable = results.filter(
    (r) => r.source === 'unavailable' || !r.doc?.available,
  ).length;
  return {
    source: rows.length ? 'lake' : errors ? 'error' : 'unavailable',
    rows,
    attempted: stormIds.length,
    unavailable,
    errors,
  };
}
