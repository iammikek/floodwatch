<script setup>
/**
 * Lake / static connection indicator for cockpit panels.
 * Green = live data lake; red = static fixtures; grey = loading.
 */
defineProps({
  source: {
    type: String,
    default: 'static',
    validator: (v) => ['lake', 'live', 'static', 'pending'].includes(v),
  },
});

const labels = {
  lake: 'Connected to data lake',
  live: 'Live via Laravel (roads / route)',
  static: 'Static / fixture data',
  pending: 'Loading data source…',
};
</script>

<template>
  <span
    class="source-dot"
    :class="source === 'live' ? 'lake' : source"
    role="img"
    :aria-label="labels[source] ?? labels.static"
    :title="labels[source] ?? labels.static"
  />
</template>

<style scoped>
.source-dot {
  display: inline-block;
  width: 0.55rem;
  height: 0.55rem;
  border-radius: 50%;
  border: 1px solid var(--line, #2a2a2a);
  flex-shrink: 0;
  align-self: center;
}
.source-dot.lake {
  background: #2d8a4e;
}
.source-dot.static {
  background: #c0392b;
}
.source-dot.pending {
  background: #b0b0b0;
}
</style>
