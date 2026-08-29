/**
 * Expand compact hour-value arrays into { t, v } points for sparklines.
 * Works with prediction.observables or legacy trends objects.
 */
export function expandHourSeries(values, seriesStartIso) {
  const start = Date.parse(seriesStartIso);
  if (!Array.isArray(values) || Number.isNaN(start)) return [];
  return values.map((v, i) => ({
    t: new Date(start + i * 3600_000).toISOString(),
    v: Number(v),
  }));
}

function observables(bundle) {
  return bundle?.observables ?? bundle ?? {};
}

export function seriesForGauge(bundle, gaugeId) {
  const obs = observables(bundle);
  if (!obs.gaugeSeries?.[gaugeId]) return [];
  return expandHourSeries(obs.gaugeSeries[gaugeId], obs.seriesStart);
}

export function rainfallSeries(bundle) {
  const obs = observables(bundle);
  if (!obs.rainfallUpstreamMm) return [];
  return expandHourSeries(obs.rainfallUpstreamMm, obs.seriesStart);
}

export function keyGaugeId(bundle) {
  return observables(bundle).keyGaugeId ?? null;
}
