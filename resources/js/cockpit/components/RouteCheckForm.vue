<script setup>
import { ref } from 'vue';
import {
  geolocationBlockedReason,
  geolocationErrorMessage,
  getCurrentPosition,
} from '../lib/geolocation.js';
import { reverseGeocodeFromCoords } from '../lib/reverseGeocode.js';

const props = defineProps({
  disabled: { type: Boolean, default: false },
  checking: { type: Boolean, default: false },
});

const from = defineModel('from', { type: String, required: true });
const to = defineModel('to', { type: String, required: true });

const emit = defineEmits(['check', 'gps-error']);

const gpsLoading = ref(false);

function onSubmit() {
  if (from.value.trim() === '' || to.value.trim() === '') return;
  emit('check');
}

function swap() {
  const a = from.value;
  from.value = to.value;
  to.value = a;
}

async function useMyLocation() {
  if (props.disabled || props.checking || gpsLoading.value) return;

  const blocked = geolocationBlockedReason();
  if (blocked) {
    emit('gps-error', blocked);
    return;
  }

  gpsLoading.value = true;
  try {
    const position = await getCurrentPosition();

    let result;
    try {
      result = await reverseGeocodeFromCoords({
        lat: position.coords.latitude,
        lng: position.coords.longitude,
      });
    } catch (err) {
      emit(
        'gps-error',
        err instanceof Error ? err.message : 'Reverse geocode request failed.',
      );
      return;
    }

    if (result.valid && result.inArea && result.location) {
      from.value = result.location;
      return;
    }

    emit('gps-error', result.error ?? 'Could not resolve your location.');
  } catch (err) {
    emit(
      'gps-error',
      err instanceof Error ? err.message : geolocationErrorMessage(err),
    );
  } finally {
    gpsLoading.value = false;
  }
}
</script>

<template>
  <form class="route-form" @submit.prevent="onSubmit">
    <label class="route-field">
      <span class="label">From</span>
      <div class="route-from-row">
        <input
          v-model="from"
          type="text"
          name="from"
          autocomplete="address-line1"
          placeholder="Postcode or place"
          aria-label="Route from"
          :disabled="disabled || checking || gpsLoading"
        />
        <button
          type="button"
          class="route-gps"
          title="Use my location"
          aria-label="Use my location"
          :disabled="disabled || checking || gpsLoading"
          @click="useMyLocation"
        >
          {{ gpsLoading ? '…' : '📍' }}
        </button>
      </div>
    </label>
    <button
      type="button"
      class="route-swap"
      title="Swap From and To"
      aria-label="Swap From and To"
      :disabled="disabled || checking || gpsLoading"
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
        :disabled="disabled || checking || gpsLoading"
      />
    </label>
    <button
      type="submit"
      class="route-submit"
      :disabled="disabled || checking || gpsLoading || !from.trim() || !to.trim()"
    >
      {{ checking ? 'Checking…' : 'Check route' }}
    </button>
  </form>
</template>
