<script setup>
import { computed } from 'vue';
import PanelHeading from './PanelHeading.vue';

const props = defineProps({
  /** @type {{ stormId: string, doc: object }[]} */
  rows: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  source: { type: String, default: 'pending' },
  selectedId: { type: String, default: null },
});

const emit = defineEmits(['select']);

function formatArea(v) {
  if (!Number.isFinite(v)) return '—';
  return v.toFixed(1);
}

function formatDepth(v) {
  if (!Number.isFinite(v)) return '—';
  return v.toFixed(2);
}

function formatVolMm3(v) {
  if (!Number.isFinite(v)) return '—';
  return v.toFixed(1);
}

function formatKm(m) {
  if (!Number.isFinite(m)) return '—';
  return (m / 1000).toFixed(1);
}

const tableRows = computed(() =>
  props.rows.map((row) => {
    const doc = row.doc || {};
    const pred = doc.prediction || {};
    const road = doc.road || {};
    const gauge = doc.method?.gauge || doc.observables?.gauge || {};
    return {
      id: row.stormId || doc.stormId,
      label: doc.stormLabel || row.stormId,
      selected: (row.stormId || doc.stormId) === props.selectedId,
      riseM: gauge.riseM,
      areaKm2: pred.areaKm2,
      meanDepthM: pred.meanDepthM,
      volumeMm3: pred.volumeMm3 ?? (pred.volumeM3 != null ? pred.volumeM3 / 1e6 : null),
      roadMaxM: road.maxDepthM,
      roadGe1Km: road.thresholdsM?.['1'] != null ? road.thresholdsM['1'] / 1000 : null,
    };
  }),
);
</script>

<template>
  <div class="box" :class="{ 'is-waiting': loading }">
    <PanelHeading :source="source">Event volume · compare</PanelHeading>
    <template v-if="loading">
      <p class="waiting-copy">Waiting for storm volumes…</p>
    </template>
    <template v-else-if="!tableRows.length">
      <p class="copy">No comparable volume estimates yet for this place.</p>
    </template>
    <template v-else>
      <p class="copy">
        Same bathtub method across golden events — approximate, not surveyed inundation.
      </p>
      <div class="volume-compare-scroll">
        <table class="volume-compare-table">
          <thead>
            <tr>
              <th scope="col">Event</th>
              <th scope="col">Rise m</th>
              <th scope="col">Area km²</th>
              <th scope="col">Mean m</th>
              <th scope="col">Vol Mm³</th>
              <th scope="col">A361 max m</th>
              <th scope="col">≥1 m km</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="row in tableRows"
              :key="row.id"
              :class="{ 'is-selected': row.selected }"
              tabindex="0"
              @click="emit('select', row.id)"
              @keydown.enter.prevent="emit('select', row.id)"
            >
              <th scope="row">{{ row.label }}</th>
              <td>{{ formatDepth(row.riseM) }}</td>
              <td>{{ formatArea(row.areaKm2) }}</td>
              <td>{{ formatDepth(row.meanDepthM) }}</td>
              <td>{{ formatVolMm3(row.volumeMm3) }}</td>
              <td>{{ formatDepth(row.roadMaxM) }}</td>
              <td>{{ formatKm(row.roadGe1Km != null ? row.roadGe1Km * 1000 : null) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </div>
</template>
