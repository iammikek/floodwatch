/**
 * Load History volume-compare rows from lake-owned storm membership.
 * Membership: GET /flood-watch/storms?volume_compare=true (curated in lake storms catalogue).
 */
import { fetchStorms } from './fetchStorms.js';
import { fetchStormVolume } from './fetchStormVolume.js';

/**
 * @param {{ corridor?: string, place?: string, stormIds?: string[], fetchImpl?: typeof fetch }} [opts]
 */
export async function fetchStormVolumeCompare({
  corridor = 'a361-muchelney',
  place = corridor,
  stormIds = null,
  fetchImpl = fetch,
} = {}) {
  let ids = Array.isArray(stormIds) ? stormIds.filter(Boolean) : null;
  if (!ids) {
    const catalogue = await fetchStorms({
      corridor,
      volumeCompare: true,
      fetchImpl,
    });
    ids = catalogue.items.map((s) => s.id).filter(Boolean);
  }

  if (!ids.length) {
    return {
      source: 'unavailable',
      rows: [],
      attempted: 0,
      unavailable: 0,
      errors: 0,
    };
  }

  const results = await Promise.all(
    ids.map(async (stormId) => {
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
    attempted: ids.length,
    unavailable,
    errors,
  };
}
