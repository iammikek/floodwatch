/**
 * Collapse noisy prediction.drivers into a short “why this verdict” view.
 */

/**
 * @param {unknown} drivers
 * @returns {{
 *   consensus: { label: string, detail: string } | null,
 *   gauges: { label: string, signal: string }[],
 *   analogues: { label: string, detail: string }[],
 *   matchedCount: number,
 * }}
 */
export function summarizeDrivers(drivers) {
  const list = Array.isArray(drivers) ? drivers : [];
  const consensusRaw = list.find((d) => d?.type === 'analogue_consensus') ?? null;
  const gaugeRows = list
    .filter((d) => d?.type === 'gauge_trajectory' && d.signal && d.signal !== 'no_data')
    .map((d) => ({
      label: String(d.label || d.ref || 'Gauge'),
      signal: humanSignal(d.signal),
    }));

  const analogues = list.filter((d) => d?.type === 'historic_analogue');
  const byMonth = new Map();
  for (const row of analogues) {
    const key = monthKey(row);
    const cur = byMonth.get(key) || {
      label: key,
      count: 0,
      bestSim: null,
      outcomes: { impact: 0, watch: 0, clear: 0 },
    };
    cur.count += 1;
    const sim = Number(row.similarity);
    if (Number.isFinite(sim) && (cur.bestSim == null || sim > cur.bestSim)) {
      cur.bestSim = sim;
    }
    const outcome = String(row.outcome || 'clear');
    if (outcome in cur.outcomes) cur.outcomes[outcome] += 1;
    else cur.outcomes.clear += 1;
    byMonth.set(key, cur);
  }

  const analogueRows = [...byMonth.values()]
    .sort((a, b) => (b.bestSim ?? 0) - (a.bestSim ?? 0))
    .slice(0, 3)
    .map((row) => ({
      label: row.label,
      detail: analogueDetail(row),
    }));

  let consensus = null;
  if (consensusRaw) {
    const impactPct = pct(consensusRaw.impactRate);
    const watchPct = pct(consensusRaw.watchRate);
    const n = analogues.length || Number(String(consensusRaw.ref || '').replace(/^k/, '')) || 0;
    consensus = {
      label: n ? `${n} matched windows` : 'Matched windows',
      detail: `Impact ${impactPct} · watch ${watchPct}`,
    };
  }

  return {
    consensus,
    gauges: gaugeRows,
    analogues: analogueRows,
    matchedCount: analogues.length,
  };
}

function humanSignal(signal) {
  const s = String(signal || '');
  const map = {
    elevated_and_rising: 'Elevated & rising',
    rising_toward_high: 'Rising toward high',
    rising: 'Rising',
    steady: 'Steady',
    steady_or_falling: 'Steady / falling',
    unknown_slope: 'Trend unclear',
  };
  return map[s] || s.replace(/_/g, ' ');
}

function monthKey(row) {
  const label = String(row?.label || '');
  if (label && label !== 'historic_analogue') {
    return label.replace(/\s+analogue$/i, '').trim() || label;
  }
  const ref = String(row?.ref || '');
  if (/^\d{4}-\d{2}/.test(ref)) {
    const d = new Date(ref);
    if (!Number.isNaN(d.getTime())) {
      return d.toLocaleString('en-GB', { month: 'short', year: 'numeric' });
    }
  }
  return 'Historic window';
}

function analogueDetail(row) {
  const parts = [];
  if (row.outcomes.impact) parts.push(`${row.outcomes.impact} impact`);
  if (row.outcomes.watch) parts.push(`${row.outcomes.watch} watch`);
  if (row.outcomes.clear) parts.push(`${row.outcomes.clear} clear`);
  const outcomeBit = parts.length ? parts.join(', ') : 'no outcomes';
  const simBit =
    row.bestSim != null ? ` · closest ${(row.bestSim * 100).toFixed(0)}% match` : '';
  const countBit = row.count > 1 ? `${row.count} hours` : '1 hour';
  return `${countBit}: ${outcomeBit}${simBit}`;
}

function pct(rate) {
  const n = Number(rate);
  if (!Number.isFinite(n)) return '—';
  return `${Math.round(n * 100)}%`;
}
