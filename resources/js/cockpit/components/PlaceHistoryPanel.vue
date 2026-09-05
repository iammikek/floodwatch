<script setup>
defineProps({
  incidents: { type: Array, default: () => [] },
  source: { type: String, default: 'pending' },
  selectedId: { type: String, default: null },
});

const emit = defineEmits(['select']);

function kindLabel(kind) {
  const map = {
    major_flood: 'Major flood',
    named_storm: 'Named storm',
    wet_spell: 'Wet spell',
    control: 'Control',
  };
  return map[kind] || kind || 'Event';
}

function formatWhen(asOf) {
  if (!asOf) return '';
  const d = new Date(asOf);
  if (Number.isNaN(d.getTime())) return String(asOf);
  return d.toLocaleString('en-GB', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  });
}
</script>

<template>
  <div class="box">
    <p class="label">Historical events</p>
    <template v-if="source === 'pending'">
      <p class="copy">Loading place history…</p>
    </template>
    <template v-else-if="!incidents.length">
      <p class="copy">
        Curated flood and storm events for this place will appear here.
      </p>
    </template>
    <ul v-else class="place-history-list">
      <li v-for="item in incidents" :key="item.id">
        <button
          type="button"
          class="history-link"
          :class="{ 'is-selected': selectedId === item.id }"
          @click="emit('select', item.id)"
        >
          <span class="history-meta">
            <span class="history-kind" :data-severity="item.severity || 'medium'">
              {{ kindLabel(item.kind) }}
            </span>
            <span class="copy">{{ formatWhen(item.asOf) }}</span>
          </span>
          <strong>{{ item.label }}</strong>
          <span v-if="item.impactSummary" class="copy">{{ item.impactSummary }}</span>
        </button>
      </li>
    </ul>
  </div>
</template>
