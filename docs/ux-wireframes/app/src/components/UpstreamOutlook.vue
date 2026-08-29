<script setup>
import { computed } from 'vue';
import Sparkline from './Sparkline.vue';
import { rainfallSeries, seriesForGauge } from '../data/expandSeries.js';

const props = defineProps({
  trends: { type: Object, required: true },
  gauges: { type: Array, default: () => [] },
});

const rain = computed(() => rainfallSeries(props.trends));
const keyGauge = computed(() =>
  props.gauges.find((g) => g.id === props.trends.keyGaugeId) ?? null,
);
const keySeries = computed(() => seriesForGauge(props.trends, props.trends.keyGaugeId));
const guide = computed(() => keyGauge.value?.typicalHigh ?? null);

const rainLabels = computed(() => {
  if (rain.value.length < 2) return [];
  const first = new Date(rain.value[0].t);
  const mid = new Date(rain.value[Math.floor(rain.value.length / 2)].t);
  const last = new Date(rain.value[rain.value.length - 1].t);
  const fmt = (d) =>
    d.toLocaleString('en-GB', { hour: '2-digit', minute: '2-digit' });
  return [fmt(first), fmt(mid), fmt(last)];
});
</script>

<template>
  <section class="box outlook">
    <p class="label">Upstream outlook · next 24h</p>
    <p class="title">{{ trends.headline }}</p>
    <p class="copy">
      Catchment: {{ trends.catchment }} · Lag estimate: {{ trends.lagEstimate }}
    </p>

    <div class="grid-2" style="margin-top: 0.75rem">
      <div>
        <p class="label">Rainfall (upstream, mm)</p>
        <Sparkline :points="rain" />
        <div class="timeline">
          <span v-for="(lab, i) in rainLabels" :key="i">{{ lab }}</span>
        </div>
        <p class="annot">Mock until lake /v1/rainfall exists</p>
      </div>
      <div>
        <p class="label">
          Key gauge · {{ keyGauge?.station ?? trends.keyGaugeId }} (m)
        </p>
        <Sparkline :points="keySeries" :guide="guide" stroke="#7a1f1f" />
        <p class="copy">Dashed = typical high · series from mock hour aggregates</p>
        <p class="annot">Can map to measurements?aggregate=hour later</p>
      </div>
    </div>

    <div class="grid-3" style="margin-top: 0.75rem">
      <div class="box" style="padding: 0.55rem">
        <p class="label">Dispatch implication</p>
        <p class="title" style="font-size: 0.95rem">{{ trends.dispatchImplication }}</p>
      </div>
      <div class="box dashed" style="padding: 0.55rem">
        <p class="label">Confidence</p>
        <p class="title" style="font-size: 0.95rem">{{ trends.confidence }}</p>
        <p class="copy">{{ trends.confidenceNote }}</p>
      </div>
      <div class="box dashed" style="padding: 0.55rem">
        <p class="label">Data deps</p>
        <p class="copy" style="margin: 0">
          Rainfall API · measurements hour series · (later) catchment lag
        </p>
      </div>
    </div>
  </section>
</template>
