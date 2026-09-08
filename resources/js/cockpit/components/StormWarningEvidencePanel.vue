<script setup>
import { computed } from 'vue';
import PanelHeading from './PanelHeading.vue';

const props = defineProps({
  /** floodwatch.storm_warning_evidence.v0 */
  evidence: { type: Object, default: null },
  stormLabel: { type: String, default: null },
  source: { type: String, default: 'static' },
});

const items = computed(() =>
  Array.isArray(props.evidence?.items) ? props.evidence.items : [],
);
const counts = computed(() => props.evidence?.counts || null);
const available = computed(() => Boolean(items.value.length));

function formatDay(iso) {
  if (!iso) return '—';
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return String(iso).slice(0, 10);
  return d.toLocaleDateString('en-GB', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  });
}

function severityClass(level) {
  if (level === 1) return 'danger';
  if (level === 2) return 'warn';
  return '';
}
</script>

<template>
  <div class="box">
    <PanelHeading :source="source">Historic flood warnings · EA</PanelHeading>
    <template v-if="!available">
      <p class="title">{{ stormLabel || 'Selected event' }}</p>
      <p class="copy">No curated AfA435 warning rows for this event yet.</p>
    </template>
    <template v-else>
      <p class="title">{{ stormLabel || evidence?.stormId || 'Event' }}</p>
      <div v-if="counts" class="stat">
        <span>Issues in window</span>
        <b>{{ counts.total }}</b>
      </div>
      <div v-if="counts" class="stat">
        <span>Flood warnings</span>
        <b class="warn">{{ counts.floodWarning }}</b>
      </div>
      <div v-if="counts" class="stat">
        <span>Flood alerts</span>
        <b>{{ counts.floodAlert }}</b>
      </div>
      <ul class="warning-evidence-list">
        <li v-for="(it, idx) in items" :key="`${it.floodAreaID}-${idx}`">
          <span class="when">{{ formatDay(it.issued_at) }}</span>
          <span class="sev" :class="severityClass(it.severityLevel)">{{ it.severity }}</span>
          <span class="name">{{ it.title }}</span>
          <span class="code">{{ it.floodAreaID }}</span>
        </li>
      </ul>
      <p class="copy">{{ evidence?.notes }}</p>
      <p class="copy">{{ evidence?.attribution }}</p>
    </template>
  </div>
</template>
