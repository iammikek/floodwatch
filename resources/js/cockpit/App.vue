<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import scenarioRisk from './data/scenario-risk.json';
import scenarioStable from './data/scenario-stable.json';
import { PLACE_PRESET_ID, PRESETS } from './data/presets.js';
import { seriesForGauge } from './data/expandSeries.js';
import { fetchPrediction } from './lib/fetchPrediction.js';
import { CORRIDOR_CENTER, fetchLiveMapData } from './lib/fetchLiveMapData.js';
import { DEFAULT_ROUTE, fetchIncidents, fetchLiveRoadData, fetchRouteCheck } from './lib/fetchLiveRoadData.js';
import { initialRoute, loadRecentRoutes, loadStoredRoute, rememberRecentRoute, saveStoredRoute } from './lib/routeStorage.js';
import { fetchBookmarks } from './lib/fetchBookmarks.js';
import { resolveRouteFromOnLoad } from './lib/defaultBookmarkRoute.js';
import { SHOW_ROUTE_VIEW } from './lib/cockpitFlags.js';
import { fetchStorms } from './lib/fetchStorms.js';
import RecentRoutesPanel from './components/RecentRoutesPanel.vue';
import BookmarksPanel from './components/BookmarksPanel.vue';
import CorridorRisk from './components/CorridorRisk.vue';
import RiverResponse from './components/RiverResponse.vue';
import LeanMap from './components/LeanMap.vue';
import InspectorPanel from './components/InspectorPanel.vue';
import PredictionPanel from './components/PredictionPanel.vue';
import PanelHeading from './components/PanelHeading.vue';
import RouteCheckForm from './components/RouteCheckForm.vue';
import StormReplayPanel from './components/StormReplayPanel.vue';
import PlaceHistoryPanel from './components/PlaceHistoryPanel.vue';

const CORRIDOR_ID = 'a361-muchelney';

const scenarios = {
  risk: scenarioRisk,
  stable: scenarioStable,
};

/** Demo fixtures only — default path is live lake + Laravel road feeds. */
const useDemoFixtures = ref(false);
const scenarioId = ref('risk');
const presetId = ref(PLACE_PRESET_ID);
const selected = ref(null);
const predictionDoc = ref(null);
const predictionSource = ref('pending');
const mapSource = ref('pending');
const roadSource = ref('pending');
const liveGauges = ref([]);
const liveFloods = ref([]);
const liveIncidents = ref([]);
const liveRoute = ref(null);
const statusNotes = ref([]);
/** True while any live feed is in flight (disables Refresh). */
const loading = ref(true);
const predictionLoading = ref(true);
const mapLoading = ref(true);
const routeChecking = ref(false);
const initial = initialRoute(DEFAULT_ROUTE);
const routeFrom = ref(initial.from);
const routeTo = ref(initial.to);
const recentRoutes = ref(loadRecentRoutes());
const bookmarks = ref([]);
const bookmarksAuthenticated = ref(false);
const bookmarksLoading = ref(true);
const activePlace = ref(null);
const routeFitToken = ref(0);
const mapFocusToken = ref(0);
const mapFocusCenter = ref(null);
const inspectorRef = ref(null);
const storms = ref([]);
const stormsSource = ref('pending');
const selectedStormId = ref(null);
const replayMode = ref(false);
const placeIncidents = ref([]);

const scenario = computed(() => scenarios[scenarioId.value]);
const preset = computed(() => PRESETS[presetId.value] ?? PRESETS.place);
const routeBusy = computed(() => loading.value || routeChecking.value);

const floods = computed(() => {
  if (mapLoading.value && !useDemoFixtures.value) return [];
  return useDemoFixtures.value ? scenario.value.floods : liveFloods.value;
});
const gauges = computed(() => {
  if (mapLoading.value && !useDemoFixtures.value) return [];
  return useDemoFixtures.value ? scenario.value.riverLevels : liveGauges.value;
});
const incidents = computed(() => {
  if (mapLoading.value && !useDemoFixtures.value) return [];
  return useDemoFixtures.value ? scenario.value.incidents : liveIncidents.value;
});
const elevatedCount = computed(
  () => gauges.value.filter((g) => g.levelStatus === 'elevated').length,
);

const mapCenter = computed(() =>
  useDemoFixtures.value ? scenario.value.location.center : CORRIDOR_CENTER.center,
);
const mapZoom = computed(() =>
  useDemoFixtures.value ? scenario.value.location.zoom : CORRIDOR_CENTER.zoom,
);
const locationLabel = computed(() => {
  if (activePlace.value?.label) return activePlace.value.label;
  if (useDemoFixtures.value) return scenario.value.location.label;
  return CORRIDOR_CENTER.label;
});

const houseRisk = computed(() => {
  if (mapLoading.value) return '';
  if (useDemoFixtures.value) return scenario.value.houseRisk;
  if (floods.value.some((f) => (f.severityLevel ?? 4) <= 2)) {
    return 'Place: flood warnings active nearby';
  }
  if (elevatedCount.value > 0) return 'Place: elevated river levels nearby';
  return 'Place: no elevated lake signals in view';
});

const roadsRisk = computed(() => {
  if (mapLoading.value) return '';
  if (useDemoFixtures.value) return scenario.value.roadsRisk;
  const n = incidents.value.length;
  if (n > 0) return `Area roads: ${n} live incident(s) near place`;
  return floods.value.length > 0
    ? `Area roads: ${floods.value.length} lake warning(s); no road incidents in view`
    : 'Area roads: no live incidents or lake warnings in view';
});

const corridorHeadline = computed(() => {
  if (predictionLoading.value && mapLoading.value) return '';
  if (useDemoFixtures.value) return scenario.value.corridor.headline;
  if (SHOW_ROUTE_VIEW && liveRoute.value?.verdictLabel && liveRoute.value.verdict !== 'error') {
    return `${liveRoute.value.verdictLabel} — ${liveRoute.value.from} → ${liveRoute.value.to}`;
  }
  const verdict = predictionDoc.value?.prediction?.verdictLabel;
  if (verdict) {
    return replayMode.value ? `Replay: ${verdict}` : verdict;
  }
  if (floods.value.some((f) => (f.severityLevel ?? 4) <= 2)) {
    return 'Flood warnings are active near this place.';
  }
  if (elevatedCount.value > 0) return 'River levels are elevated near this place.';
  return 'No elevated lake signals for this place window.';
});

const corridorGuidance = computed(() => {
  if (predictionLoading.value && mapLoading.value) return '';
  if (useDemoFixtures.value) return scenario.value.corridor.guidance;
  if (SHOW_ROUTE_VIEW && liveRoute.value?.summary) return liveRoute.value.summary;
  return (
    predictionDoc.value?.prediction?.summary ||
    'Live gauges and warnings via Laravel; storm replay when archive data is available.'
  );
});

const routeLabel = computed(() => {
  if (loading.value || routeChecking.value) return '';
  if (useDemoFixtures.value) return scenario.value.route.verdictLabel;
  if (liveRoute.value?.verdictLabel) return liveRoute.value.verdictLabel;
  const v = predictionDoc.value?.prediction?.verdict;
  if (v === 'likely_impassable' || v === 'at_risk') return 'At risk';
  if (v === 'watch') return 'Watch';
  return 'Clear';
});

const routeSummary = computed(() => {
  if (loading.value || routeChecking.value) return '';
  if (useDemoFixtures.value) {
    return `${scenario.value.route.summary} (${routeFrom.value} → ${routeTo.value})`;
  }
  if (liveRoute.value?.summary) {
    return `${liveRoute.value.summary} (${liveRoute.value.from} → ${liveRoute.value.to})`;
  }
  return 'Route check unavailable.';
});

const routeGeometry = computed(() => {
  if (!SHOW_ROUTE_VIEW || loading.value) return [];
  if (useDemoFixtures.value) return scenario.value.route.geometry;
  return liveRoute.value?.routeGeometry ?? [];
});

const selectedSeries = computed(() => {
  if (!selected.value || selected.value.type !== 'gauge' || !predictionDoc.value) return [];
  return seriesForGauge(predictionDoc.value, selected.value.id);
});

const selectedStorm = computed(
  () => storms.value.find((s) => s.id === selectedStormId.value) ?? null,
);

function clearLiveFeeds() {
  predictionDoc.value = null;
  predictionSource.value = 'pending';
  mapSource.value = 'pending';
  roadSource.value = 'pending';
  liveGauges.value = [];
  liveFloods.value = [];
  liveIncidents.value = [];
  liveRoute.value = null;
}

async function loadStorms() {
  stormsSource.value = 'pending';
  try {
    const result = await fetchStorms({ corridor: CORRIDOR_ID });
    storms.value = result.items;
    stormsSource.value = result.source;
    placeIncidents.value = result.items.map((storm) => ({
      id: storm.id,
      label: storm.label,
      asOf: storm.as_of,
      notes: storm.notes || '',
      kind: storm.kind || null,
      severity: storm.severity || null,
      impactSummary: storm.impact_summary || storm.notes || '',
      expectedVerdict: storm.expected_verdict || null,
    }));
  } catch (err) {
    storms.value = [];
    stormsSource.value = 'error';
    placeIncidents.value = [];
    statusNotes.value.push(
      `Storm catalogue unavailable: ${err instanceof Error ? err.message : String(err)}`,
    );
  }
}

/**
 * @param {{ asOf?: string|null }} [opts]
 */
function syncBusyFlag() {
  loading.value = predictionLoading.value || mapLoading.value;
}

/**
 * @param {{ asOf?: string|null }} [opts]
 */
async function loadLive({ asOf = null } = {}) {
  loading.value = true;
  predictionLoading.value = true;
  mapLoading.value = true;
  statusNotes.value = [];
  selected.value = null;
  clearLiveFeeds();
  replayMode.value = Boolean(asOf);
  try {
    if (useDemoFixtures.value) {
      predictionSource.value = 'mock';
      predictionDoc.value = (
        await fetchPrediction(CORRIDOR_ID, {
          scenarioId: scenarioId.value,
          preferMock: true,
        })
      ).doc;
      mapSource.value = 'mock';
      roadSource.value = 'mock';
      liveGauges.value = scenario.value.riverLevels;
      liveFloods.value = scenario.value.floods;
      liveIncidents.value = scenario.value.incidents;
      if (SHOW_ROUTE_VIEW) {
        liveRoute.value = {
          verdict: scenario.value.route.verdict,
          verdictLabel: scenario.value.route.verdictLabel,
          summary: scenario.value.route.summary,
          routeGeometry: scenario.value.route.geometry,
          from: routeFrom.value,
          to: routeTo.value,
        };
      }
      return;
    }

    const mapCenterPoint = activePlace.value?.lat != null
      ? [activePlace.value.lat, activePlace.value.lng]
      : CORRIDOR_CENTER.center;

    const predictionPromise = fetchPrediction(CORRIDOR_ID, {
      scenarioId: scenarioId.value,
      asOf: asOf || undefined,
    }).then((prediction) => {
      predictionSource.value = prediction.source;
      predictionDoc.value = prediction.doc;
      if (prediction.error && prediction.source === 'error') {
        statusNotes.value.push(`Prediction unavailable: ${prediction.error}`);
      }
      predictionLoading.value = false;
      syncBusyFlag();
      return prediction;
    });

    const mapPromise = fetchLiveMapData({
      center: mapCenterPoint,
    }).then((mapData) => {
      mapSource.value = mapData.source;
      liveGauges.value = mapData.gauges;
      liveFloods.value = mapData.floods;
      if (mapData.error && mapData.source === 'error') {
        statusNotes.value.push(`Map overlays unavailable: ${mapData.error}`);
      } else if (mapData.error) {
        statusNotes.value.push(`Map overlays (partial): ${mapData.error}`);
      }
      mapLoading.value = false;
      syncBusyFlag();
      return mapData;
    });

    const roadPromise = SHOW_ROUTE_VIEW
      ? fetchLiveRoadData({
          lat: mapCenterPoint[0],
          lng: mapCenterPoint[1],
          from: routeFrom.value,
          to: routeTo.value,
        })
      : fetchIncidents({
          lat: mapCenterPoint[0],
          lng: mapCenterPoint[1],
        })
          .then((incidents) => ({ source: 'live', incidents, route: null }))
          .catch((err) => ({
            source: 'error',
            incidents: [],
            route: null,
            error: err instanceof Error ? err.message : String(err),
          }));

    const roadData = await roadPromise;
    roadSource.value = roadData.source;
    liveIncidents.value = roadData.incidents ?? [];
    liveRoute.value = SHOW_ROUTE_VIEW ? roadData.route : null;
    if (SHOW_ROUTE_VIEW) {
      if (roadData.error && roadData.source === 'error') {
        statusNotes.value.push(`Roads unavailable: ${roadData.error}`);
      } else if (roadData.error) {
        statusNotes.value.push(`Roads (partial): ${roadData.error}`);
      }
    }

    await Promise.all([predictionPromise, mapPromise]);
  } catch (err) {
    statusNotes.value.push(err instanceof Error ? err.message : String(err));
  } finally {
    predictionLoading.value = false;
    mapLoading.value = false;
    loading.value = false;
  }
}

function requestRouteFit() {
  routeFitToken.value += 1;
}

function focusMapOn(lat, lng, zoom = 12) {
  if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;
  mapFocusCenter.value = { center: [lat, lng], zoom };
  mapFocusToken.value += 1;
}

function noteRecentRoute(from, to) {
  recentRoutes.value = rememberRecentRoute({ from, to });
}

async function maybeFitRoute(route) {
  if (route?.routeGeometry?.length >= 2) {
    await nextTick();
    requestRouteFit();
  }
}

async function loadBookmarks() {
  bookmarksLoading.value = true;
  try {
    const result = await fetchBookmarks();
    bookmarks.value = result.items;
    bookmarksAuthenticated.value = result.authenticated;
  } catch {
    bookmarks.value = [];
    bookmarksAuthenticated.value = false;
  } finally {
    bookmarksLoading.value = false;
  }
}

function applyDefaultPlaceOnLoad() {
  if (useDemoFixtures.value) return;
  const defaultBookmark = bookmarks.value.find((b) => b.is_default) || bookmarks.value[0];
  if (defaultBookmark) {
    activePlace.value = {
      id: defaultBookmark.id,
      label: defaultBookmark.label || defaultBookmark.location,
      location: defaultBookmark.location,
      lat: defaultBookmark.lat,
      lng: defaultBookmark.lng,
    };
    focusMapOn(defaultBookmark.lat, defaultBookmark.lng);
    return;
  }
  if (SHOW_ROUTE_VIEW) {
    const resolved = resolveRouteFromOnLoad({
      storedRoute: loadStoredRoute(),
      bookmarks: bookmarks.value,
      fallbackFrom: DEFAULT_ROUTE.from,
    });
    routeFrom.value = resolved.from;
    if (resolved.bookmark) {
      focusMapOn(resolved.bookmark.lat, resolved.bookmark.lng);
    }
  }
}

/**
 * Re-run only the From→To route check (kept for later alternate view).
 */
async function runRouteCheck() {
  if (!SHOW_ROUTE_VIEW) return;
  const from = routeFrom.value.trim();
  const to = routeTo.value.trim();
  if (!from || !to || routeBusy.value) return;

  routeChecking.value = true;
  statusNotes.value = statusNotes.value.filter((n) => !String(n).startsWith('Route check:'));
  try {
    if (useDemoFixtures.value) {
      liveRoute.value = {
        verdict: scenario.value.route.verdict,
        verdictLabel: scenario.value.route.verdictLabel,
        summary: scenario.value.route.summary,
        routeGeometry: scenario.value.route.geometry,
        from,
        to,
      };
      roadSource.value = 'mock';
      noteRecentRoute(from, to);
      await maybeFitRoute(liveRoute.value);
      return;
    }

    const route = await fetchRouteCheck({ from, to });
    liveRoute.value = route;
    roadSource.value = 'live';
    if (route.verdict === 'error') {
      statusNotes.value.push(`Route check: ${route.summary || 'Could not resolve route.'}`);
    } else {
      noteRecentRoute(from, to);
      await maybeFitRoute(route);
    }
  } catch (err) {
    statusNotes.value.push(
      `Route check: ${err instanceof Error ? err.message : String(err)}`,
    );
  } finally {
    routeChecking.value = false;
  }
}

function onRouteGpsError(message) {
  statusNotes.value = statusNotes.value.filter((n) => !String(n).startsWith('GPS:'));
  statusNotes.value.push(`GPS: ${message}`);
}

function applyRecentRoute(route) {
  if (!SHOW_ROUTE_VIEW) return;
  routeFrom.value = route.from;
  routeTo.value = route.to;
  runRouteCheck();
}

function applyBookmarkPlace(bookmark) {
  activePlace.value = {
    id: bookmark.id,
    label: bookmark.label || bookmark.location,
    location: bookmark.location,
    lat: bookmark.lat,
    lng: bookmark.lng,
  };
  selectedStormId.value = null;
  focusMapOn(bookmark.lat, bookmark.lng);
  loadLive();
}

function applyBookmarkFrom(bookmark) {
  if (!SHOW_ROUTE_VIEW) {
    applyBookmarkPlace(bookmark);
    return;
  }
  routeFrom.value = bookmark.location;
  if (routeTo.value.trim()) {
    runRouteCheck();
    return;
  }
  focusMapOn(bookmark.lat, bookmark.lng);
}

async function onSelectStorm(stormId) {
  selectedStormId.value = stormId;
  if (!stormId) {
    await loadLive();
    return;
  }
  const storm = storms.value.find((s) => s.id === stormId);
  if (!storm?.as_of) {
    statusNotes.value.push('Storm has no as_of timestamp for replay.');
    return;
  }
  await loadLive({ asOf: storm.as_of });
}

function clearStormReplay() {
  selectedStormId.value = null;
  loadLive();
}

watch([routeFrom, routeTo], ([from, to]) => {
  if (!SHOW_ROUTE_VIEW) return;
  saveStoredRoute({ from, to });
});

watch(useDemoFixtures, () => {
  if (useDemoFixtures.value) {
    presetId.value = scenario.value.preset || PLACE_PRESET_ID;
  } else {
    presetId.value = PLACE_PRESET_ID;
  }
  selectedStormId.value = null;
  loadLive();
});

watch(scenarioId, () => {
  if (!useDemoFixtures.value) return;
  selected.value = null;
  presetId.value = scenario.value.preset || PLACE_PRESET_ID;
  loadLive();
});

watch(selected, async (feature) => {
  if (!feature) return;
  await Promise.resolve();
  inspectorRef.value?.focus?.();
});

function onKeydown(event) {
  if (event.key === 'Escape' && selected.value) {
    selected.value = null;
  }
}

onMounted(() => {
  // Storms are independent of place focus — do not block live map/prediction.
  void loadStorms();
  void (async () => {
    await loadBookmarks();
    applyDefaultPlaceOnLoad();
    await loadLive();
  })();
  window.addEventListener('keydown', onKeydown);
});

onUnmounted(() => {
  window.removeEventListener('keydown', onKeydown);
});

function setScenario(id) {
  scenarioId.value = id;
}

function setPreset(id) {
  presetId.value = id;
}

function onSelect(feature) {
  selected.value = feature;
}

const dataSourceLabel = computed(() => {
  if (predictionLoading.value || mapLoading.value) return 'loading…';
  if (useDemoFixtures.value) return 'demo fixtures';
  const replay = replayMode.value ? ' · replay' : '';
  return `prediction:${predictionSource.value} · map:${mapSource.value}${replay}`;
});

/** @param {'prediction'|'map'|'roads'|'either'} kind */
function resolvePanelSource(kind) {
  if (useDemoFixtures.value) return 'static';
  if (kind === 'prediction') {
    if (predictionLoading.value || predictionSource.value === 'pending') return 'pending';
    if (predictionSource.value === 'error') return 'pending';
    return predictionSource.value === 'lake' ? 'lake' : 'static';
  }
  if (kind === 'map') {
    if (mapLoading.value || mapSource.value === 'pending') return 'pending';
    if (mapSource.value === 'error') return 'pending';
    return mapSource.value === 'lake' ? 'lake' : 'static';
  }
  if (kind === 'roads') {
    if (roadSource.value === 'pending') return 'pending';
    if (roadSource.value === 'error') return 'pending';
    return roadSource.value === 'live' ? 'live' : 'static';
  }
  const pred = predictionSource.value === 'lake';
  const map = mapSource.value === 'lake';
  const roads = roadSource.value === 'live';
  if (
    predictionSource.value === 'pending' &&
    mapSource.value === 'pending' &&
    roadSource.value === 'pending'
  ) {
    return 'pending';
  }
  if (pred || map) return 'lake';
  if (roads) return 'live';
  return 'static';
}

const predictionPanelSource = computed(() => resolvePanelSource('prediction'));
const mapPanelSource = computed(() => resolvePanelSource('map'));
const roadsPanelSource = computed(() => resolvePanelSource('roads'));
const eitherPanelSource = computed(() => resolvePanelSource('either'));
const stormsPanelSource = computed(() => {
  if (stormsSource.value === 'pending') return 'pending';
  if (stormsSource.value === 'lake') return 'lake';
  return 'static';
});

const inspectorPanelSource = computed(() => {
  if (!selected.value) return 'static';
  if (selected.value.type === 'incident') return roadsPanelSource.value;
  if (selected.value.type === 'gauge') return mapPanelSource.value;
  if (selected.value.type === 'warning') return mapPanelSource.value;
  return 'static';
});
</script>

<template>
  <div class="page page-place">
    <header class="topbar">
      <div>
        <h1>Monitor place</h1>
        <p>
          Corridor <code>{{ CORRIDOR_ID }}</code> —
          source: <strong>{{ dataSourceLabel }}</strong>
          <span v-if="predictionLoading || mapLoading"> · loading…</span>
          <span v-if="replayMode && selectedStorm"> · storm {{ selectedStorm.label }}</span>
        </p>
      </div>
      <div class="toolbar" role="group" aria-label="Data mode">
        <button
          type="button"
          :aria-pressed="!useDemoFixtures"
          @click="useDemoFixtures = false"
        >
          Live lake (via Laravel)
        </button>
        <button
          type="button"
          :aria-pressed="useDemoFixtures"
          @click="useDemoFixtures = true"
        >
          Demo fixtures
        </button>
        <template v-if="useDemoFixtures">
          <button
            type="button"
            :aria-pressed="scenarioId === 'risk'"
            @click="setScenario('risk')"
          >
            Scenario: at risk
          </button>
          <button
            type="button"
            :aria-pressed="scenarioId === 'stable'"
            @click="setScenario('stable')"
          >
            Scenario: stable
          </button>
        </template>
        <button type="button" @click="loadLive()" :disabled="loading">
          Refresh
        </button>
      </div>
    </header>

    <div
      v-for="(note, idx) in statusNotes"
      :key="idx"
      class="note"
      style="border-color: #7a1f1f; background: #fdecec"
    >
      <strong>Notice.</strong> {{ note }}
    </div>

    <div class="frame">
      <div class="chrome">
        <span class="dot" /><span class="dot" /><span class="dot" />
        floodwatch.local · place-first
      </div>

      <div class="layout">
        <aside class="sidebar">
          <div class="box">
            <PanelHeading :source="mapPanelSource">Place focus</PanelHeading>
            <p class="title" style="font-size: 0.95rem">{{ locationLabel }}</p>
            <p class="copy">
              Gauges and flood bounds (EA Flood Zones) load for this map area
              ({{ CORRIDOR_CENTER.radiusKm }} km).
            </p>
          </div>
          <BookmarksPanel
            :bookmarks="bookmarks"
            :authenticated="bookmarksAuthenticated"
            :loading="bookmarksLoading"
            :disabled="loading"
            place-mode
            @select="applyBookmarkPlace"
          />
          <StormReplayPanel
            :storms="storms"
            :selected-id="selectedStormId"
            :loading="predictionLoading"
            :source="stormsPanelSource"
            :replay-active="replayMode"
            @select="onSelectStorm"
            @clear="clearStormReplay"
          />
          <PlaceHistoryPanel
            :incidents="placeIncidents"
            :source="stormsPanelSource"
            :selected-id="selectedStormId"
            @select="onSelectStorm"
          />
          <template v-if="SHOW_ROUTE_VIEW">
            <div class="box" :class="{ 'is-waiting': routeBusy }">
              <PanelHeading :source="roadsPanelSource">Active route</PanelHeading>
              <template v-if="routeBusy">
                <p class="waiting-copy">Waiting for route…</p>
              </template>
              <template v-else>
                <p class="title" style="font-size: 0.95rem">
                  {{ routeFrom.trim() || '—' }} → {{ routeTo.trim() || '—' }}
                </p>
                <p class="copy">{{ routeLabel || 'Set From/To in Route check, then Check route.' }}</p>
              </template>
            </div>
            <RecentRoutesPanel
              :routes="recentRoutes"
              :disabled="routeBusy"
              @select="applyRecentRoute"
            />
          </template>
        </aside>

        <div class="main">
          <PredictionPanel
            v-if="predictionDoc && !predictionLoading"
            class="primary-panel"
            :prediction-doc="predictionDoc"
            :gauges="gauges"
            :source="predictionPanelSource"
            :replay-label="replayMode && selectedStorm ? selectedStorm.label : null"
          />
          <div v-else-if="predictionLoading" class="box outlook primary-panel is-waiting">
            <PanelHeading source="pending">Corridor prediction · historic EA analogues</PanelHeading>
            <p class="waiting-copy">Waiting for prediction…</p>
          </div>
          <div v-else class="box outlook primary-panel">
            <PanelHeading source="pending">Corridor prediction · historic EA analogues</PanelHeading>
            <p class="title">Prediction unavailable</p>
            <p class="copy">
              No live prediction to show. We do not substitute demo data in live mode.
            </p>
          </div>

          <div class="grid-2 support-grid">
            <div class="box" :class="{ 'is-waiting': mapLoading }">
              <PanelHeading :source="mapPanelSource">Your risk</PanelHeading>
              <template v-if="mapLoading">
                <p class="waiting-copy">Waiting for risk signals…</p>
              </template>
              <template v-else>
                <p class="title">{{ houseRisk }}</p>
                <p class="copy">{{ roadsRisk }}</p>
              </template>
            </div>
            <div class="box" :class="{ 'is-waiting': predictionLoading && mapLoading }">
              <PanelHeading :source="eitherPanelSource">Place outlook</PanelHeading>
              <template v-if="predictionLoading && mapLoading">
                <p class="waiting-copy">Waiting for place outlook…</p>
              </template>
              <template v-else>
                <p class="title">{{ corridorHeadline }}</p>
                <p class="copy">{{ corridorGuidance }}</p>
              </template>
            </div>
          </div>

          <template v-if="SHOW_ROUTE_VIEW">
            <div class="grid-2 support-grid">
              <div class="box">
                <PanelHeading :source="roadsPanelSource">Route check</PanelHeading>
                <RouteCheckForm
                  v-model:from="routeFrom"
                  v-model:to="routeTo"
                  :disabled="loading"
                  :checking="routeChecking"
                  @check="runRouteCheck"
                  @gps-error="onRouteGpsError"
                />
                <div class="route-result" :class="{ 'is-waiting': routeBusy }">
                  <template v-if="routeBusy">
                    <p class="waiting-copy">Waiting for route check…</p>
                  </template>
                  <template v-else>
                    <p class="title">{{ routeLabel }}</p>
                    <p class="copy">{{ routeSummary }}</p>
                  </template>
                </div>
              </div>
            </div>
          </template>

          <CorridorRisk
            class="corridor-summary"
            :floods="floods"
            :incidents="incidents"
            :elevated-count="elevatedCount"
            :headline="corridorHeadline"
            :guidance="corridorGuidance"
            :route-label="SHOW_ROUTE_VIEW ? routeLabel : (predictionDoc?.prediction?.verdictLabel || '—')"
            :loading="mapLoading && predictionLoading"
            :route-loading="routeChecking"
            :corridor-source="eitherPanelSource"
            :flood-source="mapPanelSource"
            :route-source="SHOW_ROUTE_VIEW ? roadsPanelSource : predictionPanelSource"
          />

          <div class="map-shell map-shell-place" :class="{ 'is-waiting': mapLoading }">
            <LeanMap
              :center="mapCenter"
              :zoom="mapZoom"
              :floods="floods"
              :incidents="incidents"
              :gauges="gauges"
              :route-geometry="routeGeometry"
              :route-fit-token="routeFitToken"
              :map-focus-token="mapFocusToken"
              :map-focus-center="mapFocusCenter"
              :preset="preset"
              :selected-id="selected?.id ?? null"
              :source="mapPanelSource"
              @select="onSelect"
              @update:preset="setPreset"
            />
            <div ref="inspectorRef" tabindex="-1" class="inspector-focus">
              <InspectorPanel
                :feature="selected"
                :series="selectedSeries"
                :source="inspectorPanelSource"
              />
            </div>
          </div>

          <RiverResponse
            :gauges="gauges"
            :loading="mapLoading"
            :source="mapPanelSource"
            :selected-id="selected?.id ?? null"
            @select="onSelect"
          />
        </div>
      </div>
    </div>
  </div>
</template>
