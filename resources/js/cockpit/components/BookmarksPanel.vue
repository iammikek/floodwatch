<script setup>
defineProps({
  bookmarks: { type: Array, default: () => [] },
  authenticated: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
  profileUrl: { type: String, default: '/profile' },
  /** When true, bookmarks focus the map place (not route From). */
  placeMode: { type: Boolean, default: false },
});

const emit = defineEmits(['select']);
</script>

<template>
  <div class="box">
    <p class="label">Bookmarks</p>
    <template v-if="loading">
      <p class="waiting-copy">Loading bookmarks…</p>
    </template>
    <template v-else-if="bookmarks.length">
      <button
        v-for="bookmark in bookmarks"
        :key="bookmark.id"
        type="button"
        class="sidebar-chip"
        :class="{ 'is-default': bookmark.is_default }"
        :disabled="disabled"
        @click="emit('select', bookmark)"
      >
        {{ bookmark.label }} · {{ bookmark.location }}
      </button>
      <p class="copy">
        <template v-if="placeMode">Tap to focus this place on the map.</template>
        <template v-else>Tap to set route From and refresh the map.</template>
        <a :href="profileUrl">Manage bookmarks</a>
      </p>
    </template>
    <template v-else-if="authenticated">
      <p class="copy">
        No bookmarks yet.
        <a :href="profileUrl">Add bookmarks in Profile</a>
      </p>
    </template>
    <template v-else>
      <p class="copy">
        <template v-if="placeMode">Sign in to use saved locations as place focus.</template>
        <template v-else>Sign in to use saved locations as route From.</template>
        <a href="/login">Sign in</a>
        ·
        <a :href="profileUrl">Profile</a>
      </p>
    </template>
  </div>
</template>
