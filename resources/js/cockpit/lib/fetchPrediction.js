/**
 * Load floodwatch.prediction.v1 for a corridor via Laravel (same-origin).
 * Demo fixtures only when preferMock is set — live mode never invents a prediction.
 */
import predictionRisk from '../data/prediction-risk.json';
import predictionStable from '../data/prediction-stable.json';

const MOCKS = {
  risk: predictionRisk,
  stable: predictionStable,
};

export function mockPrediction(scenarioId = 'risk') {
  return structuredClone(MOCKS[scenarioId] ?? MOCKS.risk);
}

/**
 * @param {string} corridorId
 * @param {{ scenarioId?: string, fetchImpl?: typeof fetch, preferMock?: boolean, asOf?: string }} [opts]
 * @returns {Promise<{ source: 'lake'|'mock'|'error', doc: object|null, error?: string }>}
 */
export async function fetchPrediction(
  corridorId,
  { scenarioId = 'risk', fetchImpl = fetch, preferMock = false, asOf } = {},
) {
  if (preferMock) {
    return { source: 'mock', doc: mockPrediction(scenarioId) };
  }

  const params = new URLSearchParams({ corridor: corridorId });
  if (asOf) params.set('as_of', asOf);
  const url = `/flood-watch/predictions?${params.toString()}`;
  try {
    const res = await fetchImpl(url, {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
    });
    if (!res.ok) {
      throw new Error(`Prediction request failed: ${res.status}`);
    }
    const doc = await res.json();
    if (!doc || doc.schema !== 'floodwatch.prediction.v1') {
      throw new Error('Prediction schema mismatch');
    }
    return { source: 'lake', doc };
  } catch (err) {
    return {
      source: 'error',
      doc: null,
      error: err instanceof Error ? err.message : String(err),
    };
  }
}
