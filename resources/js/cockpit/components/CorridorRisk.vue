<script setup>
import { computed } from 'vue';

const props = defineProps({
  floods: { type: Array, default: () => [] },
  incidents: { type: Array, default: () => [] },
  elevatedCount: { type: Number, default: 0 },
  headline: { type: String, required: true },
  guidance: { type: String, required: true },
  routeLabel: { type: String, required: true },
});

const counts = computed(() => {
  const out = { severe: 0, warning: 0, alert: 0 };
  for (const flood of props.floods) {
    const level = Number(flood.severityLevel ?? 4);
    const label = String(flood.severity ?? '').toLowerCase();
    if (level === 1 || label.startsWith('severe')) out.severe += 1;
    else if (level === 2 || label.includes('warning')) out.warning += 1;
    else if (level === 3 || label.includes('alert')) out.alert += 1;
  }
  return out;
});
</script>

<template>
  <div class="grid-3">
    <div class="box">
      <p class="label">Corridor risk</p>
      <p class="title">{{ headline }}</p>
      <p class="copy">{{ guidance }}</p>
    </div>
    <div class="box">
      <p class="label">Flood exposure</p>
      <div class="stat"><span>Severe warnings</span><b class="danger">{{ counts.severe }}</b></div>
      <div class="stat"><span>Flood warnings</span><b class="warn">{{ counts.warning }}</b></div>
      <div class="stat"><span>Flood alerts</span><b class="warn">{{ counts.alert }}</b></div>
    </div>
    <div class="box">
      <p class="label">Current route</p>
      <p class="title">{{ routeLabel }}</p>
      <p class="copy">{{ elevatedCount }} elevated gauges</p>
      <p class="copy">{{ incidents.length }} active incidents</p>
    </div>
  </div>
</template>
