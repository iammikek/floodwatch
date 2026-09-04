<script setup>
defineProps({
  storms: { type: Array, default: () => [] },
  selectedId: { type: String, default: null },
  loading: { type: Boolean, default: false },
  source: { type: String, default: 'pending' },
  replayActive: { type: Boolean, default: false },
});

const emit = defineEmits(['select', 'clear']);
</script>

<template>
  <div class="box">
    <p class="label">Storm replay</p>
    <p v-if="replayActive" class="annot">Replaying selected storm (not live)</p>
    <template v-if="!storms.length">
      <p class="copy">No curated storms for this place yet.</p>
    </template>
    <template v-else>
      <button
        v-for="storm in storms"
        :key="storm.id"
        type="button"
        class="sidebar-chip"
        :class="{ 'is-default': selectedId === storm.id }"
        :disabled="loading"
        :aria-pressed="selectedId === storm.id"
        @click="emit('select', storm.id)"
      >
        {{ storm.label }}
      </button>
      <button
        v-if="selectedId"
        type="button"
        class="sidebar-chip"
        :disabled="loading"
        @click="emit('clear')"
      >
        Return to live
      </button>
      <p class="copy">Pick a storm to run corridor prediction as-of that event.</p>
    </template>
  </div>
</template>
