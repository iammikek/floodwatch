<script setup>
import { onBeforeUnmount, onMounted, ref, watch, nextTick, computed } from 'vue';
import L from 'leaflet';
import { PRESETS } from '../data/presets.js';
import { boundsFromRouteGeometry } from '../lib/fitRouteBounds.js';
import { fetchFloodZones } from '../lib/fetchFloodZones.js';
import {
  emphasizeFloodZones,
  floodZoneStyleForFeature,
  normalizeImpactBbox,
} from '../lib/floodZoneEmphasis.js';
import PanelHeading from './PanelHeading.vue';

const presetOptions = computed(() => Object.values(PRESETS ?? {}));

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
  /** Increment to fit the map viewport to the active route polyline. */
  routeFitToken: { type: Number, default: 0 },
  /** Increment to pan the map to mapFocusCenter (bookmark / point focus). */
  mapFocusToken: { type: Number, default: 0 },
  /** @type {{ center: [number, number], zoom?: number } | null} */
  mapFocusCenter: { type: Object, default: null },
  /**
   * Selected place-history / storm replay event.
   * Planning FZ polygons are static; severity drives filter + style emphasis.
   * @type {{ id?: string, label?: string, severity?: string, kind?: string } | null}
   */
  historyEvent: { type: Object, default: null },
  /** When false, hide Place/Dispatch/Hydrology/Minimal switcher (use-case owns layers). */
  showPresets: { type: Boolean, default: false },
  /** Short label for flood-bounds status line. */
  layerStatusLabel: { type: String, default: 'flood bounds' },
  /**
   * When true, skip polygon fetches so lake-bound prediction can finish first.
   * Layers reload when this returns to false.
   */
  deferHeavyLayers: { type: Boolean, default: false },
});

const emit = defineEmits(['select', 'update:preset']);

const mapEl = ref(null);
let map = null;
let overlay = null;
let floodZonesLayer = null;
/** Raw lake FeatureCollection (unfiltered). */
let floodZonesGeo = { type: 'FeatureCollection', features: [] };
let floodZonesToken = 0;
let impactBoundsLayer = null;
let moveTimer = null;
const floodZonesStatus = ref('idle');

const floodBoundsCaption = computed(() => {
  if (!(props.preset ?? PRESETS.dispatch).layers?.floodZones) return 'off';
  const base = floodZonesStatus.value;
  if (!props.historyEvent) return base;
  const mode = String(props.historyEvent.bounds_mode || '').toLowerCase();
  if (mode === 'none') return 'none (event)';
  if (base === 'off' || base === 'error') return base;
  const sev = props.historyEvent.severity || 'event';
  return `${base} · ${sev}`;
});

function divIcon(kind, selected) {
  const cls = ['marker-dot', kind, selected ? 'selected' : ''].filter(Boolean).join(' ');
  return L.divIcon({
    className: '',
    html: `<div class="${cls}"></div>`,
    iconSize: [12, 12],
    iconAnchor: [6, 6],
  });
}

function floodZoneStyle(feature) {
  return floodZoneStyleForFeature(feature);
}

function paintedFloodZones() {
  return emphasizeFloodZones(floodZonesGeo, props.historyEvent);
}

function syncImpactBoundsOutline() {
  if (!map) return;
  if (impactBoundsLayer) {
    map.removeLayer(impactBoundsLayer);
    impactBoundsLayer = null;
  }
  const bbox = normalizeImpactBbox(props.historyEvent?.impact_bbox);
  if (!bbox || String(props.historyEvent?.bounds_mode || '').toLowerCase() === 'none') {
    return;
  }
  const [w, s, e, n] = bbox;
  impactBoundsLayer = L.rectangle(
    [
      [s, w],
      [n, e],
    ],
    {
      color: '#7c2d12',
      weight: 2,
      dashArray: '6 4',
      fill: false,
      opacity: 0.9,
      interactive: false,
    },
  ).addTo(map);
}

function layersEnabled() {
  const preset = props.preset ?? PRESETS.dispatch;
  return preset.layers ?? PRESETS.dispatch.layers;
}

function rebuildOverlays() {
  if (!map) return;
  if (overlay) {
    overlay.clearLayers();
  } else {
    overlay = L.layerGroup().addTo(map);
  }

  const layers = layersEnabled();
  const warningCap = props.preset?.warningCap ?? PRESETS.dispatch.warningCap ?? 8;
  const gaugeCap = props.preset?.gaugeCap ?? PRESETS.dispatch.gaugeCap ?? 12;

  const painted = paintedFloodZones();
  if (layers.floodZones && painted?.features?.length) {
    if (!floodZonesLayer) {
      floodZonesLayer = L.geoJSON(null, { style: floodZoneStyle }).addTo(map);
      // Keep under marker overlay: bring markers to front via overlay group order.
      floodZonesLayer.bringToBack();
    }
    floodZonesLayer.clearLayers();
    floodZonesLayer.addData(painted);
    floodZonesLayer.bringToBack();
  } else if (floodZonesLayer) {
    floodZonesLayer.clearLayers();
  }
  syncImpactBoundsOutline();

  if (layers.route && props.routeGeometry?.length >= 2) {
    const latLngs = props.routeGeometry.map(([lng, lat]) => [lat, lng]);
    L.polyline(latLngs, { color: '#2563eb', weight: 4, opacity: 0.85 }).addTo(overlay);
  }

  if (layers.warnings && !props.historyEvent) {
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

  if (layers.incidents && !props.historyEvent) {
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

  if (layers.gauges && !props.historyEvent) {
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

async function loadFloodZonesForViewport() {
  if (!map || !layersEnabled().floodZones) {
    floodZonesGeo = { type: 'FeatureCollection', features: [] };
    floodZonesStatus.value = 'off';
    rebuildOverlays();
    return;
  }

  if (props.deferHeavyLayers) {
    floodZonesStatus.value = 'waiting';
    return;
  }

  const event = props.historyEvent;
  const mode = String(event?.bounds_mode || '').toLowerCase();
  if (event && mode === 'none') {
    floodZonesGeo = { type: 'FeatureCollection', features: [] };
    floodZonesStatus.value = 'none';
    rebuildOverlays();
    return;
  }

  const impact = normalizeImpactBbox(event?.impact_bbox);
  let west;
  let south;
  let east;
  let north;
  let clipped = false;

  if (impact) {
    [west, south, east, north] = impact;
  } else {
    const b = map.getBounds();
    west = b.getWest();
    south = b.getSouth();
    east = b.getEast();
    north = b.getNorth();
    // Lake rejects inline bboxes wider than ~0.5°. When zoomed out, fetch a
    // centre-clamped window so planning bounds stay visible.
    const maxSpan = 0.45;
    const width = Math.abs(east - west);
    const height = Math.abs(north - south);
    if (width > maxSpan || height > maxSpan) {
      const c = map.getCenter();
      const halfW = Math.min(width, maxSpan) / 2;
      const halfH = Math.min(height, maxSpan) / 2;
      west = c.lng - halfW;
      east = c.lng + halfW;
      south = c.lat - halfH;
      north = c.lat + halfH;
      clipped = true;
    }
  }

  const bbox = [west, south, east, north].join(',');
  const token = ++floodZonesToken;
  floodZonesStatus.value = 'loading';
  try {
    const geo = await fetchFloodZones({ bbox });
    if (token !== floodZonesToken) return;
    floodZonesGeo = geo;
    if (!geo.features.length) {
      floodZonesStatus.value = 'empty';
    } else if (impact) {
      floodZonesStatus.value = 'event';
    } else {
      floodZonesStatus.value = clipped ? 'lake·focus' : 'lake';
    }
    rebuildOverlays();
  } catch {
    if (token !== floodZonesToken) return;
    floodZonesGeo = { type: 'FeatureCollection', features: [] };
    floodZonesStatus.value = 'error';
    rebuildOverlays();
  }
}

function scheduleFloodZonesReload() {
  if (moveTimer) clearTimeout(moveTimer);
  moveTimer = setTimeout(() => {
    loadFloodZonesForViewport();
  }, 450);
}

function fitMapToRoute() {
  if (!map) return;
  const bounds = boundsFromRouteGeometry(props.routeGeometry);
  if (!bounds) return;
  map.fitBounds(bounds, { padding: [36, 36], maxZoom: 13 });
}

onMounted(async () => {
  await nextTick();
  if (!mapEl.value) return;

  map = L.map(mapEl.value, {
    zoomControl: true,
    attributionControl: false,
  }).setView(props.center, props.zoom);

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 18,
  }).addTo(map);

  rebuildOverlays();
  if (!props.deferHeavyLayers) {
    await loadFloodZonesForViewport();
  } else {
    floodZonesStatus.value = 'waiting';
  }
  map.on('moveend', scheduleFloodZonesReload);
});

watch(
  () => props.deferHeavyLayers,
  (deferred, wasDeferred) => {
    if (deferred) {
      if (moveTimer) clearTimeout(moveTimer);
      floodZonesToken += 1;
      floodZonesStatus.value = 'waiting';
      return;
    }
    if (wasDeferred && layersEnabled().floodZones) {
      scheduleFloodZonesReload();
    }
  },
);

onBeforeUnmount(() => {
  if (moveTimer) clearTimeout(moveTimer);
  if (impactBoundsLayer && map) {
    map.removeLayer(impactBoundsLayer);
    impactBoundsLayer = null;
  }
  if (map) {
    map.off('moveend', scheduleFloodZonesReload);
    map.remove();
    map = null;
    overlay = null;
    floodZonesLayer = null;
  }
});

function sameCenter(a, b) {
  if (!Array.isArray(a) || !Array.isArray(b) || a.length < 2 || b.length < 2) return false;
  return Number(a[0]) === Number(b[0]) && Number(a[1]) === Number(b[1]);
}

watch(
  () => [props.center, props.zoom],
  ([center, zoom], previous) => {
    if (!map) return;
    const [prevCenter, prevZoom] = previous ?? [];
    if (sameCenter(center, prevCenter) && zoom === prevZoom) return;
    map.setView(center, zoom, { animate: false });
  },
);

watch(
  () => [
    props.floods,
    props.incidents,
    props.gauges,
    props.routeGeometry,
    props.preset,
    props.selectedId,
  ],
  () => {
    rebuildOverlays();
    if (props.preset?.layers?.floodZones) {
      scheduleFloodZonesReload();
    } else {
      floodZonesGeo = { type: 'FeatureCollection', features: [] };
      floodZonesStatus.value = 'off';
      rebuildOverlays();
    }
  },
  { deep: true },
);

watch(
  () => [
    props.historyEvent?.id,
    props.historyEvent?.severity,
    props.historyEvent?.bounds_mode,
    JSON.stringify(props.historyEvent?.impact_bbox ?? null),
  ],
  () => {
    const bbox = normalizeImpactBbox(props.historyEvent?.impact_bbox);
    if (map && bbox && String(props.historyEvent?.bounds_mode || '').toLowerCase() !== 'none') {
      const [w, s, e, n] = bbox;
      map.fitBounds(
        [
          [s, w],
          [n, e],
        ],
        { padding: [28, 28], maxZoom: 13, animate: true },
      );
    }
    if (props.preset?.layers?.floodZones) {
      scheduleFloodZonesReload();
    } else {
      rebuildOverlays();
    }
  },
);

watch(
  () => props.routeFitToken,
  async (token) => {
    if (!map || !token) return;
    rebuildOverlays();
    await nextTick();
    fitMapToRoute();
  },
);

watch(
  () => props.mapFocusToken,
  async (token) => {
    if (!map || !token || !props.mapFocusCenter?.center) return;
    const [lat, lng] = props.mapFocusCenter.center;
    if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;
    await nextTick();
    map.setView([lat, lng], props.mapFocusCenter.zoom ?? 12, { animate: true });
  },
);
</script>

<template>
  <div class="map-wrap">
    <div class="map-controls">
      <div v-if="showPresets" class="box">
        <PanelHeading :source="source">View preset</PanelHeading>
        <button
          v-for="p in presetOptions"
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
        <span>mode {{ historyEvent ? 'history' : 'live' }}</span><br />
        <span>warnings {{ layersEnabled().warnings ? 'on' : 'off' }}</span><br />
        <span>gauges {{ layersEnabled().gauges ? 'on' : 'off' }}</span><br />
        <span>{{ layerStatusLabel }} · {{ floodBoundsCaption }}</span>
      </div>
    </div>
    <div ref="mapEl" class="map-el" />
  </div>
</template>
