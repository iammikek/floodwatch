<script setup>
import SourceDot from './SourceDot.vue';

defineProps({
  source: {
    type: String,
    default: 'static',
    validator: (v) => ['lake', 'live', 'static', 'pending'].includes(v),
  },
});
</script>

<template>
  <div class="panel-heading">
    <p class="label"><slot /></p>
    <SourceDot :source="source" />
  </div>
</template>
