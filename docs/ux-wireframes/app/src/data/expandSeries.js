/**
 * Expand compact hour-value arrays into { t, v } points for sparklines.
 */
export function expandHourSeries(values, seriesStartIso) {
  const start = Date.parse(seriesStartIso);
  if (!Array.isArray(values) || Number.isNaN(start)) return [];
  return values.map((v, i) => ({
    t: new Date(start + i * 3600_000).toISOString(),
    v: Number(v),
  }));
}

export function seriesForGauge(trends, gaugeId) {
  if (!trends?.gaugeSeries?.[gaugeId]) return [];
  return expandHourSeries(trends.gaugeSeries[gaugeId], trends.seriesStart);
}

export function rainfallSeries(trends) {
  if (!trends?.rainfallUpstreamMm) return [];
  return expandHourSeries(trends.rainfallUpstreamMm, trends.seriesStart);
}
