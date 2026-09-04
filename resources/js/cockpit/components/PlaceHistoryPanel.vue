<script setup>
defineProps({
  incidents: { type: Array, default: () => [] },
  source: { type: String, default: 'pending' },
});

const emit = defineEmits(['select']);
</script>

<template>
  <div class="box">
    <p class="label">Historical incidents here</p>
    <template v-if="!incidents.length">
      <p class="copy">
        Storm catalogue entries for this corridor will appear here as place history.
      </p>
    </template>
    <ul v-else class="place-history-list">
      <li v-for="item in incidents" :key="item.id">
        <button type="button" class="history-link" @click="emit('select', item.id)">
          <strong>{{ item.label }}</strong>
          <span class="copy">{{ item.asOf }}</span>
          <span v-if="item.notes" class="copy">{{ item.notes }}</span>
        </button>
      </li>
    </ul>
  </div>
</template>
