<script setup>
import { computed } from 'vue';
import PanelHeading from './PanelHeading.vue';

const props = defineProps({
  floods: { type: Array, default: () => [] },
  incidents: { type: Array, default: () => [] },
  elevatedCount: { type: Number, default: 0 },
  headline: { type: String, required: true },
  guidance: { type: String, required: true },
  routeLabel: { type: String, required: true },
  loading: { type: Boolean, default: false },
  /** Route-only refresh — greys the Current route tile without blanking floods. */
  routeLoading: { type: Boolean, default: false },
  /** Storm / place-history replay — hide live route and warning tiles. */
  replayMode: { type: Boolean, default: false },
  showFloodExposure: { type: Boolean, default: true },
  showCurrentRoute: { type: Boolean, default: true },
  corridorSource: { type: String, default: 'static' },
  floodSource: { type: String, default: 'static' },
  routeSource: { type: String, default: 'static' },
});

const counts = computed(() => {
  const out = { severe: 0, warning: 0, alert: 0 };
  if (props.loading || props.replayMode || !props.showFloodExposure) return out;
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
  <div
    class="grid-3"
    :class="{ 'is-waiting': loading, 'corridor-summary-replay': replayMode }"
  >
    <div class="box">
      <PanelHeading :source="corridorSource">Corridor risk</PanelHeading>
      <template v-if="loading">
        <p class="waiting-copy">Waiting for corridor signals…</p>
      </template>
      <template v-else>
        <p class="title">{{ headline }}</p>
        <p class="copy">{{ guidance }}</p>
      </template>
    </div>
    <div v-if="!replayMode && showFloodExposure" class="box">
      <PanelHeading :source="floodSource">Flood exposure</PanelHeading>
      <template v-if="loading">
        <p class="waiting-copy">Waiting for flood warnings…</p>
      </template>
      <template v-else>
        <div class="stat"><span>Severe warnings</span><b class="danger">{{ counts.severe }}</b></div>
        <div class="stat"><span>Flood warnings</span><b class="warn">{{ counts.warning }}</b></div>
        <div class="stat"><span>Flood alerts</span><b class="warn">{{ counts.alert }}</b></div>
      </template>
    </div>
    <div
      v-if="!replayMode && showCurrentRoute"
      class="box"
      :class="{ 'is-waiting': routeLoading && !loading }"
    >
      <PanelHeading :source="routeSource">Current route</PanelHeading>
      <template v-if="loading || routeLoading">
        <p class="waiting-copy">Waiting for route status…</p>
      </template>
      <template v-else>
        <p class="title">{{ routeLabel }}</p>
        <p class="copy">{{ elevatedCount }} elevated gauges</p>
        <p class="copy">{{ incidents.length }} active incidents</p>
      </template>
    </div>
  </div>
</template>
