<script setup>
import { computed, ref, watch } from 'vue';
import PanelHeading from './PanelHeading.vue';

const props = defineProps({
  gauges: { type: Array, default: () => [] },
  selectedId: { type: String, default: null },
  loading: { type: Boolean, default: false },
  source: { type: String, default: 'static' },
  /** When true, live gauges are intentionally hidden (storm / place-history replay). */
  replayMode: { type: Boolean, default: false },
  replayLabel: { type: String, default: null },
});

const emit = defineEmits(['select']);

/** @type {import('vue').Ref<'all'|'elevated'|'expected'|'low'>} */
const statusFilter = ref('all');

const counts = computed(() => {
  const out = { elevated: 0, expected: 0, low: 0, unknown: 0 };
  for (const g of props.gauges) {
    const key = out[g.levelStatus] !== undefined ? g.levelStatus : 'unknown';
    out[key] += 1;
  }
  return out;
});

const total = computed(() => Math.max(1, props.gauges.length));

const widths = computed(() => ({
  elevated: (counts.value.elevated / total.value) * 100,
  expected: (counts.value.expected / total.value) * 100,
  low: (counts.value.low / total.value) * 100,
  unknown: (counts.value.unknown / total.value) * 100,
}));

function sortGauges(list) {
  return [...list].sort((a, b) => {
    const ae = a.levelStatus === 'elevated' ? 1 : 0;
    const be = b.levelStatus === 'elevated' ? 1 : 0;
    if (be !== ae) return be - ae;
    return (Number(b.value) || 0) - (Number(a.value) || 0);
  });
}

const filteredGauges = computed(() => {
  if (statusFilter.value === 'all') return props.gauges;
  return props.gauges.filter((g) => g.levelStatus === statusFilter.value);
});

const priority = computed(() => sortGauges(filteredGauges.value).slice(0, 4));

const priorityLabel = computed(() => {
  if (statusFilter.value === 'all') return 'Priority gauges';
  return `Priority gauges · ${statusFilter.value}`;
});

watch(
  () => props.gauges,
  () => {
    if (statusFilter.value === 'all') return;
    const stillPresent = props.gauges.some((g) => g.levelStatus === statusFilter.value);
    if (!stillPresent) statusFilter.value = 'all';
  },
);

/**
 * @param {'all'|'elevated'|'expected'|'low'} next
 */
function setStatusFilter(next) {
  statusFilter.value = statusFilter.value === next ? 'all' : next;
}

function statusClass(status) {
  if (status === 'elevated') return 'danger';
  if (status === 'expected') return '';
  return '';
}

function formatTime(iso) {
  if (!iso) return '';
  const d = new Date(iso);
  return d.toLocaleString('en-GB', {
    day: 'numeric',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
  });
}
</script>

<template>
  <div class="box" :class="{ 'is-waiting': loading }">
    <PanelHeading :source="source">River response</PanelHeading>
    <template v-if="loading">
      <p class="waiting-copy">Waiting for gauges…</p>
    </template>
    <template v-else-if="replayMode">
      <p class="title">Live gauges hidden for historical replay</p>
      <p class="copy">
        Monitored levels are for the live network, not
        {{ replayLabel || 'this storm window' }}. Use corridor prediction
        (drivers / matched windows) for historical gauge context. Return to live
        to see current monitored gauges.
      </p>
    </template>
    <template v-else>
      <div class="grid-2">
        <div>
          <p class="title">{{ gauges.length }} monitored gauges in the current map area</p>
          <p class="copy">
            Judge whether the corridor is stable, elevated, or reacting unevenly across nearby gauges.
            Click a status count to filter priority gauges.
          </p>
        </div>
        <div>
          <div class="bar" aria-hidden="true">
            <span class="elevated" :style="{ width: widths.elevated + '%' }" />
            <span class="expected" :style="{ width: widths.expected + '%' }" />
            <span class="low" :style="{ width: widths.low + '%' }" />
            <span class="unknown" :style="{ width: widths.unknown + '%' }" />
          </div>
          <div
            class="grid-4"
            style="margin-top: 0.65rem"
            role="group"
            aria-label="Filter gauges by status"
          >
            <button
              type="button"
              class="box status-filter"
              style="padding: 0.45rem"
              :aria-pressed="statusFilter === 'elevated'"
              :disabled="!counts.elevated"
              @click="setStatusFilter('elevated')"
            >
              <p class="label">Elevated</p>
              <p class="title danger" style="font-size: 1.25rem">{{ counts.elevated }}</p>
            </button>
            <button
              type="button"
              class="box status-filter"
              style="padding: 0.45rem"
              :aria-pressed="statusFilter === 'expected'"
              :disabled="!counts.expected"
              @click="setStatusFilter('expected')"
            >
              <p class="label">Expected</p>
              <p class="title" style="font-size: 1.25rem">{{ counts.expected }}</p>
            </button>
            <button
              type="button"
              class="box status-filter"
              style="padding: 0.45rem"
              :aria-pressed="statusFilter === 'low'"
              :disabled="!counts.low"
              @click="setStatusFilter('low')"
            >
              <p class="label">Low</p>
              <p class="title" style="font-size: 1.25rem">{{ counts.low }}</p>
            </button>
            <button
              type="button"
              class="box status-filter"
              style="padding: 0.45rem"
              :aria-pressed="statusFilter === 'all'"
              @click="setStatusFilter('all')"
            >
              <p class="label">Monitored</p>
              <p class="title" style="font-size: 1.25rem">{{ gauges.length }}</p>
            </button>
          </div>
        </div>
      </div>

      <p class="label" style="margin-top: 0.85rem">{{ priorityLabel }}</p>
      <div v-if="priority.length" class="gauge-grid">
        <button
          v-for="g in priority"
          :key="g.id"
          type="button"
          class="box gauge-card"
          :class="{ 'is-selected': selectedId === g.id }"
          @click="emit('select', g)"
        >
          <div style="display: flex; justify-content: space-between; gap: 0.5rem">
            <div>
              <p class="title" style="font-size: 0.95rem">{{ g.station }}</p>
              <p class="copy">{{ g.river }}</p>
            </div>
            <span class="tag" :class="statusClass(g.levelStatus)">{{ g.levelStatus }}</span>
          </div>
          <p class="copy">
            <b>{{ Number(g.value).toFixed(2) }} {{ g.unit }}</b>
            · {{ formatTime(g.dateTime) }}
          </p>
        </button>
      </div>
      <p v-else class="copy" style="margin-top: 0.35rem">
        <template v-if="statusFilter !== 'all'">
          No {{ statusFilter }} gauges in this map area.
        </template>
        <template v-else>No gauges in this map area.</template>
      </p>
    </template>
  </div>
</template>
