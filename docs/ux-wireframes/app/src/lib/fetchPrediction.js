/**
 * Load floodwatch.prediction.v0 for a corridor.
 * Prefer lake API when VITE_LAKE_API_URL is set; else use bundled mock.
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

export async function fetchPrediction(corridorId, { baseUrl, scenarioId = 'risk', fetchImpl = fetch } = {}) {
  const base = (baseUrl ?? import.meta.env.VITE_LAKE_API_URL ?? '').replace(/\/$/, '');
  if (!base) {
    return { source: 'mock', doc: mockPrediction(scenarioId) };
  }
  const url = `${base}/v1/predictions?corridor=${encodeURIComponent(corridorId)}`;
  const res = await fetchImpl(url);
  if (!res.ok) {
    throw new Error(`Prediction request failed: ${res.status}`);
  }
  const doc = await res.json();
  return { source: 'lake', doc };
}
