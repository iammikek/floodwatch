<script setup>
import { computed } from 'vue';
import Sparkline from './Sparkline.vue';

const props = defineProps({
  feature: { type: Object, default: null },
  /** Hour series for selected gauge, already expanded */
  series: { type: Array, default: () => [] },
});

const title = computed(() => {
  if (!props.feature) return 'Nothing selected';
  if (props.feature.type === 'gauge') return `${props.feature.station} · ${props.feature.river}`;
  if (props.feature.type === 'incident') return `${props.feature.road} · ${props.feature.statusLabel}`;
  if (props.feature.type === 'warning') return props.feature.description;
  return props.feature.id;
});

function formatTime(iso) {
  if (!iso) return '—';
  return new Date(iso).toLocaleString('en-GB', {
    day: 'numeric',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
  });
}

const slopeLabel = computed(() => {
  const pts = props.series;
  if (pts.length < 2) return null;
  const delta = pts[pts.length - 1].v - pts[0].v;
  if (Math.abs(delta) < 0.05) return 'Steady over window';
  return delta > 0 ? `Rising · +${delta.toFixed(2)} over window` : `Falling · ${delta.toFixed(2)} over window`;
});
</script>

<template>
  <div class="box" style="display: flex; flex-direction: column; gap: 0.55rem; min-height: 360px">
    <p class="label">Inspector · selected feature</p>

    <template v-if="!feature">
      <p class="title" style="font-size: 1rem">Select a marker or priority gauge</p>
      <p class="copy">Detail stays here — not in map popups.</p>
      <p class="annot" style="margin-top: auto">Lean map rule</p>
    </template>

    <template v-else-if="feature.type === 'gauge'">
      <p class="title">{{ title }}</p>
      <div class="stat"><span>Status</span><b :class="feature.levelStatus === 'elevated' ? 'danger' : ''">{{ feature.levelStatus }}</b></div>
      <div class="stat"><span>Level</span><b>{{ Number(feature.value).toFixed(2) }} {{ feature.unit }}</b></div>
      <div class="stat"><span>Updated</span><b>{{ formatTime(feature.dateTime) }}</b></div>
      <div class="stat">
        <span>Typical range</span>
        <b>{{ feature.typicalLow }}–{{ feature.typicalHigh }} {{ feature.unit }}</b>
      </div>

      <template v-if="series.length">
        <p class="label" style="margin-top: 0.35rem">Level trend (12h mock)</p>
        <Sparkline
          :points="series"
          :guide="feature.typicalHigh"
          stroke="#7a1f1f"
          :height="64"
        />
        <p v-if="slopeLabel" class="copy">{{ slopeLabel }}</p>
      </template>
    </template>

    <template v-else-if="feature.type === 'warning'">
      <p class="title">{{ title }}</p>
      <div class="stat"><span>Severity</span><b class="warn">{{ feature.severity }}</b></div>
      <div class="stat"><span>Area</span><b>{{ feature.floodAreaID }}</b></div>
      <p class="copy">{{ feature.message }}</p>
    </template>

    <template v-else-if="feature.type === 'incident'">
      <p class="title">{{ title }}</p>
      <div class="stat"><span>Status</span><b>{{ feature.statusLabel }}</b></div>
      <p class="copy">{{ feature.description }}</p>
    </template>
  </div>
</template>
