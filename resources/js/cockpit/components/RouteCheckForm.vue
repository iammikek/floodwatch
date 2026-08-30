<script setup>
defineProps({
  disabled: { type: Boolean, default: false },
  checking: { type: Boolean, default: false },
});

const from = defineModel('from', { type: String, required: true });
const to = defineModel('to', { type: String, required: true });

const emit = defineEmits(['check']);

function onSubmit() {
  if (from.value.trim() === '' || to.value.trim() === '') return;
  emit('check');
}

function swap() {
  const a = from.value;
  from.value = to.value;
  to.value = a;
}
</script>

<template>
  <form class="route-form" @submit.prevent="onSubmit">
    <label class="route-field">
      <span class="label">From</span>
      <input
        v-model="from"
        type="text"
        name="from"
        autocomplete="address-line1"
        placeholder="Postcode or place"
        aria-label="Route from"
        :disabled="disabled || checking"
      />
    </label>
    <button
      type="button"
      class="route-swap"
      title="Swap From and To"
      aria-label="Swap From and To"
      :disabled="disabled || checking"
      @click="swap"
    >
      ⇄
    </button>
    <label class="route-field">
      <span class="label">To</span>
      <input
        v-model="to"
        type="text"
        name="to"
        autocomplete="address-line2"
        placeholder="Postcode or place"
        aria-label="Route to"
        :disabled="disabled || checking"
      />
    </label>
    <button
      type="submit"
      class="route-submit"
      :disabled="disabled || checking || !from.trim() || !to.trim()"
    >
      {{ checking ? 'Checking…' : 'Check route' }}
    </button>
  </form>
</template>
