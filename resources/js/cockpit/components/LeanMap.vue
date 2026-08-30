<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import L from 'leaflet';
import { PRESETS } from '../data/presets.js';
import PanelHeading from './PanelHeading.vue';

const props = defineProps({
  center: { type: Array, required: true },
  zoom: { type: Number, default: 11 },
  floods: { type: Array, default: () => [] },
  incidents: { type: Array, default: () => [] },
  gauges: { type: Array, default: () => [] },
  routeGeometry: { type: Array, default: () => [] },
  preset: { type: Object, required: true },
  selectedId: { type: String, default: null },
  source: { type: String, default: 'static' },
});

const emit = defineEmits(['select', 'update:preset']);

const mapEl = ref(null);
let map = null;
let overlay = null;

function divIcon(kind, selected) {
  const cls = ['marker-dot', kind, selected ? 'selected' : ''].filter(Boolean).join(' ');
  return L.divIcon({
    className: '',
    html: `<div class="${cls}"></div>`,
    iconSize: [12, 12],
    iconAnchor: [6, 6],
  });
}

function rebuildOverlays() {
  if (!map) return;
  if (overlay) {
    overlay.clearLayers();
  } else {
    overlay = L.layerGroup().addTo(map);
  }

  const layers = props.preset.layers;
  const warningCap = props.preset.warningCap ?? 8;
  const gaugeCap = props.preset.gaugeCap ?? 12;

  if (layers.route && props.routeGeometry?.length >= 2) {
    const latLngs = props.routeGeometry.map(([lng, lat]) => [lat, lng]);
    L.polyline(latLngs, { color: '#2563eb', weight: 4, opacity: 0.85 }).addTo(overlay);
  }

  if (layers.warnings) {
    const list = [...props.floods]
      .sort((a, b) => (a.severityLevel ?? 4) - (b.severityLevel ?? 4))
      .slice(0, warningCap);
    for (const f of list) {
      if (f.lat == null || f.lng == null) continue;
      const marker = L.marker([f.lat, f.lng], {
        icon: divIcon('warning', props.selectedId === f.id),
        keyboard: true,
        title: f.description,
      });
      marker.on('click', () => emit('select', f));
      marker.addTo(overlay);
    }
  }

  if (layers.incidents) {
    for (const i of props.incidents) {
      if (i.lat == null || i.lng == null) continue;
      const marker = L.marker([i.lat, i.lng], {
        icon: divIcon('incident', props.selectedId === i.id),
        title: `${i.road} ${i.statusLabel}`,
      });
      marker.on('click', () => emit('select', i));
      marker.addTo(overlay);
    }
  }

  if (layers.gauges) {
    const list = [...props.gauges]
      .sort((a, b) => {
        const ae = a.levelStatus === 'elevated' ? 1 : 0;
        const be = b.levelStatus === 'elevated' ? 1 : 0;
        if (be !== ae) return be - ae;
        return (Number(b.value) || 0) - (Number(a.value) || 0);
      })
      .slice(0, gaugeCap);
    for (const g of list) {
      if (g.lat == null || g.lng == null) continue;
      const kind = g.levelStatus === 'elevated' ? 'elevated' : 'expected';
      const marker = L.marker([g.lat, g.lng], {
        icon: divIcon(kind, props.selectedId === g.id),
        title: g.station,
      });
      marker.on('click', () => emit('select', g));
      marker.addTo(overlay);
    }
  }
}

onMounted(() => {
  map = L.map(mapEl.value, {
    zoomControl: true,
    attributionControl: false,
  }).setView(props.center, props.zoom);

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 18,
  }).addTo(map);

  rebuildOverlays();
});

onBeforeUnmount(() => {
  if (map) {
    map.remove();
    map = null;
    overlay = null;
  }
});

watch(
  () => [
    props.floods,
    props.incidents,
    props.gauges,
    props.routeGeometry,
    props.preset,
    props.selectedId,
    props.center,
    props.zoom,
  ],
  () => {
    if (!map) return;
    map.setView(props.center, props.zoom, { animate: false });
    rebuildOverlays();
  },
  { deep: true },
);
</script>

<template>
  <div class="map-wrap">
    <div class="map-controls">
      <div class="box">
        <PanelHeading :source="source">View preset</PanelHeading>
        <button
          v-for="p in Object.values(PRESETS)"
          :key="p.id"
          type="button"
          class="preset-btn"
          :aria-pressed="preset.id === p.id"
          @click="emit('update:preset', p.id)"
        >
          {{ p.label }}
        </button>
      </div>
      <div class="box dashed">
        <span>route {{ preset.layers.route ? 'on' : 'off' }}</span><br />
        <span>warnings {{ preset.layers.warnings ? 'on' : 'off' }}</span><br />
        <span>roads {{ preset.layers.incidents ? 'on' : 'off' }}</span><br />
        <span>gauges {{ preset.layers.gauges ? 'on' : 'off' }}</span>
      </div>
    </div>
    <div ref="mapEl" class="map-el" />
  </div>
</template>
