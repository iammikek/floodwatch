<script setup>
import { computed } from 'vue';
import Sparkline from './Sparkline.vue';
import { rainfallSeries, seriesForGauge, keyGaugeId } from '../data/expandSeries.js';

const props = defineProps({
  /** floodwatch.prediction.v0 mock */
  predictionDoc: { type: Object, required: true },
  gauges: { type: Array, default: () => [] },
});

const p = computed(() => props.predictionDoc.prediction);
const rain = computed(() => rainfallSeries(props.predictionDoc));
const kgId = computed(() => keyGaugeId(props.predictionDoc));
const keyGauge = computed(() => props.gauges.find((g) => g.id === kgId.value) ?? null);
const keySeries = computed(() => seriesForGauge(props.predictionDoc, kgId.value));
const guide = computed(() => keyGauge.value?.typicalHigh ?? null);

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
    <p class="label">Corridor prediction · historic EA analogues</p>
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
      <div>
        <p class="label">Supporting · upstream rainfall (mm)</p>
        <Sparkline :points="rain" />
        <p class="annot">Observable only — prediction uses history + trajectory</p>
      </div>
      <div>
        <p class="label">Supporting · {{ keyGauge?.station ?? kgId }} (m)</p>
        <Sparkline :points="keySeries" :guide="guide" stroke="#7a1f1f" />
        <p class="copy">Dashed = typical high</p>
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
