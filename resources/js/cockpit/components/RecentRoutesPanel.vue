<script setup>
defineProps({
  routes: { type: Array, default: () => [] },
  disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['select']);
</script>

<template>
  <div class="box">
    <p class="label">Recent routes</p>
    <template v-if="routes.length">
      <button
        v-for="(route, index) in routes"
        :key="`${route.from}-${route.to}-${index}`"
        type="button"
        class="sidebar-chip"
        :disabled="disabled"
        @click="emit('select', route)"
      >
        {{ route.from }} → {{ route.to }}
      </button>
    </template>
    <p v-else class="copy">Check a route to build a quick-pick list.</p>
  </div>
</template>
