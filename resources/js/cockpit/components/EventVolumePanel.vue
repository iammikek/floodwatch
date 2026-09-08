<script setup>
import { computed } from 'vue';
import PanelHeading from './PanelHeading.vue';

const props = defineProps({
  /** floodwatch.storm_volume.v1 */
  volumeDoc: { type: Object, default: null },
  loading: { type: Boolean, default: false },
  source: { type: String, default: 'pending' },
  stormLabel: { type: String, default: null },
});

const pred = computed(() => props.volumeDoc?.prediction ?? null);
const method = computed(() => props.volumeDoc?.method ?? null);
const gauge = computed(() => method.value?.gauge ?? props.volumeDoc?.observables?.gauge ?? null);
const road = computed(() => props.volumeDoc?.road ?? null);
const available = computed(() => Boolean(props.volumeDoc?.available && pred.value));

function formatAreaKm2(v) {
  if (!Number.isFinite(v)) return '—';
  return `${v.toFixed(2)} km²`;
}

function formatDepth(v) {
  if (!Number.isFinite(v)) return '—';
  return `${v.toFixed(2)} m`;
}

function formatVolume(v) {
  if (!Number.isFinite(v)) return '—';
  if (v >= 1_000_000) return `${(v / 1_000_000).toFixed(2)} Mm³`;
  if (v >= 1000) return `${(v / 1000).toFixed(0)} thousand m³`;
  return `${v.toFixed(0)} m³`;
}

function formatKm(m) {
  if (!Number.isFinite(m)) return '—';
  if (m >= 1000) return `${(m / 1000).toFixed(2)} km`;
  return `${m.toFixed(0)} m`;
}

const methodCopy = computed(() => {
  if (method.value?.mode === 'gauge_rise' && gauge.value) {
    return (
      `Confidence ${pred.value?.confidenceLabel || 'Low'} · free surface = DEM floor ` +
      `+ ${formatDepth(gauge.value.riseM)} rise at ${gauge.value.label || 'gauge'} ` +
      `(peak ${formatDepth(gauge.value.stagePeakM)} vs summer ${formatDepth(gauge.value.stageBaselineM)}).`
    );
  }
  if (method.value?.fillPercentile != null) {
    return (
      `Confidence ${pred.value?.confidenceLabel || 'Low'} · bathtub fill at ` +
      `${method.value.fillPercentile}th percentile of DEM (gauge rise unavailable).`
    );
  }
  return `Confidence ${pred.value?.confidenceLabel || 'Low'} · approximate bathtub.`;
});

const reasonCopy = computed(() => {
  const reason = props.volumeDoc?.reason;
  if (reason === 'no_impact_geometry') {
    return 'No curated flood outline for this event (e.g. summer control).';
  }
  if (reason === 'no_dtm_tiles') {
    return 'LiDAR DTM not ingested for this place yet.';
  }
  if (reason === 'no_samples_in_polygon') {
    return 'Outline does not overlap available DTM tiles.';
  }
  if (props.source === 'error') return 'Volume estimate unavailable from the data lake.';
  return 'Volume estimate not available for this event.';
});
</script>

<template>
  <div class="box" :class="{ 'is-waiting': loading }">
    <PanelHeading :source="source">Event volume · approximate</PanelHeading>
    <template v-if="loading">
      <p class="waiting-copy">Waiting for volume estimate…</p>
    </template>
    <template v-else-if="!available">
      <p class="title">{{ stormLabel || 'Selected event' }}</p>
      <p class="copy">{{ reasonCopy }}</p>
    </template>
    <template v-else>
      <p class="title">{{ stormLabel || volumeDoc?.stormLabel || 'Event' }}</p>
      <div class="stat"><span>Flooded area</span><b>{{ formatAreaKm2(pred.areaKm2) }}</b></div>
      <div class="stat"><span>Mean depth</span><b>{{ formatDepth(pred.meanDepthM) }}</b></div>
      <div class="stat"><span>Max depth</span><b>{{ formatDepth(pred.maxDepthM) }}</b></div>
      <div class="stat"><span>Volume</span><b>{{ formatVolume(pred.volumeM3) }}</b></div>
      <p class="copy">{{ methodCopy }}</p>

      <template v-if="road?.available">
        <p class="title" style="margin-top: 0.75rem; font-size: 0.95rem">
          {{ road.label || 'A361 approach' }}
        </p>
        <div class="stat"><span>Max depth on strip</span><b>{{ formatDepth(road.maxDepthM) }}</b></div>
        <div class="stat"><span>Mean wet depth</span><b>{{ formatDepth(road.meanDepthM) }}</b></div>
        <div class="stat"><span>Length ≥ 0.3 m</span><b>{{ formatKm(road.thresholdsM?.['0.3']) }}</b></div>
        <div class="stat"><span>Length ≥ 0.5 m</span><b>{{ formatKm(road.thresholdsM?.['0.5']) }}</b></div>
        <div class="stat"><span>Length ≥ 1.0 m</span><b>{{ formatKm(road.thresholdsM?.['1']) }}</b></div>
        <p class="copy">Approximate centreline depth strip — not surveyed carriageway geometry.</p>
      </template>

      <p class="copy">{{ method?.notes }}</p>
    </template>
  </div>
</template>
