import { expandHourSeries } from '../data/expandSeries.js';

/** Map API observables.gaugeSeries keys to display labels when scenario gauges missing. */
export function gaugesFromPrediction(doc) {
  const series = doc?.observables?.gaugeSeries ?? {};
  const drivers = (doc?.drivers ?? []).filter((d) => d.type === 'gauge_trajectory');
  const byRef = Object.fromEntries(drivers.map((d) => [d.ref, d]));

  return Object.keys(series).map((ref) => {
    const driver = byRef[ref] || {};
    const label = driver.label || ref;
    const [station, river] = label.includes('·')
      ? label.split('·').map((s) => s.trim())
      : [label, ''];
    const values = series[ref] || [];
    const last = values.length ? values[values.length - 1] : null;
    return {
      id: ref,
      type: 'gauge',
      station,
      river,
      value: last,
      unit: 'm',
      levelStatus:
        driver.signal === 'elevated_and_rising' || driver.signal === 'rising_toward_high'
          ? 'elevated'
          : driver.signal === 'steady_or_falling'
            ? 'low'
            : 'expected',
      typicalHigh: doc?.observables?.primaryAnalysis?.p95 ?? null,
      dateTime: doc?.as_of ?? null,
    };
  });
}

export function sparkPointsForKey(doc, gaugeRef) {
  const obs = doc?.observables ?? {};
  const values = obs.gaugeSeries?.[gaugeRef] ?? [];
  if (!obs.seriesStart) {
    return values.map((v, i) => ({ t: String(i), v }));
  }
  return expandHourSeries(values, obs.seriesStart);
}
