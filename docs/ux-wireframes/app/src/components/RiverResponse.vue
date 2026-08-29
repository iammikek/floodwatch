<script setup>
import { computed } from 'vue';

const props = defineProps({
  gauges: { type: Array, default: () => [] },
  selectedId: { type: String, default: null },
});

const emit = defineEmits(['select']);

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

const priority = computed(() =>
  [...props.gauges]
    .sort((a, b) => {
      const ae = a.levelStatus === 'elevated' ? 1 : 0;
      const be = b.levelStatus === 'elevated' ? 1 : 0;
      if (be !== ae) return be - ae;
      return (Number(b.value) || 0) - (Number(a.value) || 0);
    })
    .slice(0, 4),
);

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
  <div class="box">
    <div class="grid-2">
      <div>
        <p class="label">River response</p>
        <p class="title">{{ gauges.length }} monitored gauges in the current map area</p>
        <p class="copy">
          Judge whether the corridor is stable, elevated, or reacting unevenly across nearby gauges.
        </p>
      </div>
      <div>
        <div class="bar" aria-hidden="true">
          <span class="elevated" :style="{ width: widths.elevated + '%' }" />
          <span class="expected" :style="{ width: widths.expected + '%' }" />
          <span class="low" :style="{ width: widths.low + '%' }" />
          <span class="unknown" :style="{ width: widths.unknown + '%' }" />
        </div>
        <div class="grid-4" style="margin-top: 0.65rem">
          <div class="box" style="padding: 0.45rem">
            <p class="label">Elevated</p>
            <p class="title danger" style="font-size: 1.25rem">{{ counts.elevated }}</p>
          </div>
          <div class="box" style="padding: 0.45rem">
            <p class="label">Expected</p>
            <p class="title" style="font-size: 1.25rem">{{ counts.expected }}</p>
          </div>
          <div class="box" style="padding: 0.45rem">
            <p class="label">Low</p>
            <p class="title" style="font-size: 1.25rem">{{ counts.low }}</p>
          </div>
          <div class="box" style="padding: 0.45rem">
            <p class="label">Monitored</p>
            <p class="title" style="font-size: 1.25rem">{{ gauges.length }}</p>
          </div>
        </div>
      </div>
    </div>

    <p class="label" style="margin-top: 0.85rem">Priority gauges</p>
    <div class="gauge-grid">
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
  </div>
</template>
