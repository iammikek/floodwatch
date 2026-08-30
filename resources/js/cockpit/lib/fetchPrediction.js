/**
 * Load floodwatch.prediction.v0 for a corridor via Laravel (same-origin).
 * Falls back to bundled mock when the API is unavailable.
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
 * @param {{ scenarioId?: string, fetchImpl?: typeof fetch, preferMock?: boolean }} [opts]
 */
export async function fetchPrediction(
  corridorId,
  { scenarioId = 'risk', fetchImpl = fetch, preferMock = false } = {},
) {
  if (preferMock) {
    return { source: 'mock', doc: mockPrediction(scenarioId) };
  }

  const url = `/flood-watch/predictions?corridor=${encodeURIComponent(corridorId)}`;
  try {
    const res = await fetchImpl(url, {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
    });
    if (!res.ok) {
      throw new Error(`Prediction request failed: ${res.status}`);
    }
    const doc = await res.json();
    if (!doc || doc.schema !== 'floodwatch.prediction.v0') {
      throw new Error('Prediction schema mismatch');
    }
    return { source: 'lake', doc };
  } catch (err) {
    return {
      source: 'mock',
      doc: mockPrediction(scenarioId),
      error: err instanceof Error ? err.message : String(err),
    };
  }
}
