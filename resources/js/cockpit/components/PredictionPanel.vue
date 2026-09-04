<script setup>
import { computed } from 'vue';
import Sparkline from './Sparkline.vue';
import PanelHeading from './PanelHeading.vue';
import { rainfallSeries, seriesForGauge, keyGaugeId } from '../data/expandSeries.js';

const props = defineProps({
  /** floodwatch.prediction.v1 */
  predictionDoc: { type: Object, required: true },
  gauges: { type: Array, default: () => [] },
  /** Overall prediction feed: lake | static | pending */
  source: { type: String, default: 'static' },
});

const p = computed(() => props.predictionDoc.prediction);
const rain = computed(() => rainfallSeries(props.predictionDoc));
const kgId = computed(() => keyGaugeId(props.predictionDoc));
const keyGauge = computed(() => props.gauges.find((g) => g.id === kgId.value) ?? null);
const keySeries = computed(() => seriesForGauge(props.predictionDoc, kgId.value));
const primaryAnalysis = computed(() => props.predictionDoc?.observables?.primaryAnalysis ?? null);
const guide = computed(
  () =>
    keyGauge.value?.typicalHigh ??
    primaryAnalysis.value?.p95 ??
    null,
);

const gaugeTitle = computed(() => {
  if (keyGauge.value?.station) return keyGauge.value.station;
  const primaryId = props.predictionDoc?.observables?.primaryMeasureId;
  const driver = (props.predictionDoc?.drivers ?? []).find(
    (d) =>
      d.type === 'gauge_trajectory' &&
      (d.ref === kgId.value || d.ref === primaryId),
  );
  if (driver?.label) {
    return String(driver.label).split('·')[0].trim();
  }
  return kgId.value ?? 'Primary gauge';
});

const latestStage = computed(() => {
  if (Number.isFinite(primaryAnalysis.value?.level)) return Number(primaryAnalysis.value.level);
  if (keySeries.value.length) return Number(keySeries.value[keySeries.value.length - 1].v);
  if (Number.isFinite(keyGauge.value?.value)) return Number(keyGauge.value.value);
  return null;
});

const seriesRange = computed(() => {
  if (!keySeries.value.length) return null;
  const values = keySeries.value.map((pt) => Number(pt.v)).filter(Number.isFinite);
  if (!values.length) return null;
  return { min: Math.min(...values), max: Math.max(...values) };
});

function formatMetres(value) {
  if (!Number.isFinite(value)) return '—';
  return `${value.toFixed(2)} m`;
}

/** Green only when this series is actually present on a live lake prediction. */
const rainfallSource = computed(() => {
  if (props.source === 'pending') return 'pending';
  if (props.source === 'lake' && rain.value.length > 0) return 'lake';
  return 'static';
});

const gaugeSeriesSource = computed(() => {
  if (props.source === 'pending') return 'pending';
  if (props.source === 'lake' && keySeries.value.length > 0) return 'lake';
  return 'static';
});

const verdictClass = computed(() => {
  const v = p.value.verdict;
  if (v === 'at_risk' || v === 'likely_impassable') return 'danger';
  if (v === 'watch') return 'warn';
  return 'ok';
});

const impactLabel = computed(() => {
  if (p.value.timeToImpactHours == null) return 'No timed impact';
  return `~${p.value.timeToImpactHours}h to impact`;
});

function formatWindow(window) {
  if (!window?.from) return '—';
  const fmt = (iso) =>
    new Date(iso).toLocaleString('en-GB', {
      day: 'numeric',
      month: 'short',
      hour: '2-digit',
      minute: '2-digit',
    });
  return `${fmt(window.from)} – ${fmt(window.to)}`;
}

function riskClass(risk) {
  if (risk === 'high') return 'danger';
  if (risk === 'medium') return 'warn';
  return '';
}
</script>

<template>
  <section class="box outlook">
    <PanelHeading :source="source">Corridor prediction · historic EA analogues</PanelHeading>
    <p class="title" :class="verdictClass">{{ p.verdictLabel }}</p>
    <p class="copy">{{ p.summary }}</p>

    <div class="grid-3" style="margin-top: 0.75rem">
      <div class="box" style="padding: 0.55rem">
        <p class="label">Time to impact</p>
        <p class="title" style="font-size: 1.1rem">{{ impactLabel }}</p>
        <p class="copy">{{ formatWindow(p.impactWindow) }}</p>
      </div>
      <div class="box" style="padding: 0.55rem">
        <p class="label">Confidence</p>
        <p class="title" style="font-size: 1.1rem">
          {{ p.confidenceLabel }}
          <span class="copy">({{ Math.round(p.confidence * 100) }}%)</span>
        </p>
        <p class="copy">{{ predictionDoc.method.notes }}</p>
      </div>
      <div class="box" style="padding: 0.55rem">
        <p class="label">Dispatch</p>
        <p class="title" style="font-size: 0.95rem">{{ predictionDoc.dispatch.implication }}</p>
        <p class="copy">
          Safe to pass (profile TBD):
          <b :class="predictionDoc.dispatch.safeToPass ? 'ok' : 'danger'">
            {{ predictionDoc.dispatch.safeToPass ? 'yes' : 'no' }}
          </b>
        </p>
      </div>
    </div>

    <div class="grid-2" style="margin-top: 0.75rem">
      <div class="box" style="padding: 0.55rem">
        <PanelHeading :source="rainfallSource">Supporting · upstream rainfall (mm)</PanelHeading>
        <Sparkline :points="rain" />
        <p class="annot">Observable only — prediction uses history + trajectory</p>
      </div>
      <div class="box" style="padding: 0.55rem">
        <PanelHeading :source="gaugeSeriesSource">
          Supporting · {{ gaugeTitle }} (m)
        </PanelHeading>
        <Sparkline :points="keySeries" :guide="guide" stroke="#7a1f1f" />
        <div class="height-key" aria-label="Stage height key">
          <p class="label">Height key</p>
          <div class="stat">
            <span>Unit</span>
            <b>Stage metres (m)</b>
          </div>
          <div class="stat">
            <span>Latest</span>
            <b>{{ formatMetres(latestStage) }}</b>
          </div>
          <div class="stat">
            <span class="height-key-guide">Typical high</span>
            <b>{{ formatMetres(guide) }}</b>
          </div>
          <div v-if="seriesRange" class="stat">
            <span>Chart window</span>
            <b>{{ formatMetres(seriesRange.min) }} – {{ formatMetres(seriesRange.max) }}</b>
          </div>
          <p class="copy">
            Dashed line = typical high (historic p95 of mined EA stage for this gauge).
            Higher = deeper water at the station, not road depth.
          </p>
        </div>
      </div>
    </div>

    <div class="grid-2" style="margin-top: 0.75rem">
      <div>
        <p class="label">Drivers</p>
        <div
          v-for="(d, i) in predictionDoc.drivers"
          :key="i"
          class="stat"
        >
          <span>{{ d.label }}</span>
          <b>{{ d.signal || (d.similarity != null ? `sim ${d.similarity}` : d.type) }}</b>
        </div>
      </div>
      <div>
        <p class="label">Predicted affected areas</p>
        <template v-if="predictionDoc.affectedAreas.length">
          <div
            v-for="a in predictionDoc.affectedAreas"
            :key="a.id"
            class="stat"
          >
            <span>{{ a.label }}</span>
            <b :class="riskClass(a.risk)">{{ a.risk }}</b>
          </div>
        </template>
        <p v-else class="copy">None in prediction window</p>
      </div>
    </div>

    <p class="annot">
      Contract: {{ predictionDoc.schema }} · method {{ predictionDoc.method.name }} ·
      corridor {{ predictionDoc.corridor.label }}
    </p>
  </section>
</template>
