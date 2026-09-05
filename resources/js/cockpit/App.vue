<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import scenarioRisk from './data/scenario-risk.json';
import scenarioStable from './data/scenario-stable.json';
import { seriesForGauge } from './data/expandSeries.js';
import { fetchPrediction } from './lib/fetchPrediction.js';
import { CORRIDOR_CENTER, fetchLiveMapData } from './lib/fetchLiveMapData.js';
import { DEFAULT_ROUTE, fetchIncidents, fetchLiveRoadData, fetchRouteCheck } from './lib/fetchLiveRoadData.js';
import { initialRoute, loadRecentRoutes, loadStoredRoute, rememberRecentRoute, saveStoredRoute } from './lib/routeStorage.js';
import { fetchBookmarks } from './lib/fetchBookmarks.js';
import { resolveRouteFromOnLoad } from './lib/defaultBookmarkRoute.js';
import { fetchStorms } from './lib/fetchStorms.js';
import {
  USE_CASE_HISTORY,
  USE_CASE_LIVE,
  USE_CASE_OPTIONS,
  USE_CASE_TRANSPORT,
  presetForUseCase,
  resolveUseCase,
} from './lib/cockpitUseCases.js';
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
const useCaseId = ref(USE_CASE_LIVE);
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
const placeIncidents = ref([]);

const scenario = computed(() => scenarios[scenarioId.value]);
const activeUseCase = computed(() => resolveUseCase(useCaseId.value));
const panels = computed(() => activeUseCase.value.panels);
const preset = computed(() => presetForUseCase(useCaseId.value));
const replayMode = computed(() => useCaseId.value === USE_CASE_HISTORY);
const transportMode = computed(() => useCaseId.value === USE_CASE_TRANSPORT);
const showRoutePanels = computed(() => Boolean(panels.value.routeCheck));
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
  if (replayMode.value) {
    return 'Place: live gauges/warnings hidden during storm replay';
  }
  if (floods.value.some((f) => (f.severityLevel ?? 4) <= 2)) {
    return 'Place: flood warnings active nearby';
  }
  if (elevatedCount.value > 0) return 'Place: elevated river levels nearby';
  return 'Place: no elevated lake signals in view';
});

const roadsRisk = computed(() => {
  if (mapLoading.value) return '';
  if (useDemoFixtures.value) return scenario.value.roadsRisk;
  if (replayMode.value) {
    return 'Area roads: live incidents omitted during storm replay';
  }
  const n = incidents.value.length;
  if (n > 0) return `Area roads: ${n} live incident(s) near place`;
  return floods.value.length > 0
    ? `Area roads: ${floods.value.length} lake warning(s); no road incidents in view`
    : 'Area roads: no live incidents or lake warnings in view';
});

const corridorHeadline = computed(() => {
  if (predictionLoading.value && mapLoading.value) return '';
  if (useDemoFixtures.value) return scenario.value.corridor.headline;
  if (showRoutePanels.value && liveRoute.value?.verdictLabel && liveRoute.value.verdict !== 'error') {
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
  if (showRoutePanels.value && liveRoute.value?.summary) return liveRoute.value.summary;
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
  if (!showRoutePanels.value || loading.value) return [];
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
      if (showRoutePanels.value) {
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

    // Prediction first: map polygon/gauge lake traffic waits so hindcast is not starved.
    const prediction = await fetchPrediction(CORRIDOR_ID, {
      scenarioId: scenarioId.value,
      asOf: asOf || undefined,
    });
    predictionSource.value = prediction.source;
    predictionDoc.value = prediction.doc;
    if (prediction.error && prediction.source === 'error') {
      statusNotes.value.push(`Prediction unavailable: ${prediction.error}`);
    }
    predictionLoading.value = false;
    syncBusyFlag();

    // Historical replay: prediction + flood-zone emphasis only.
    // Live gauges / warnings / road incidents belong to "now", not the storm window.
    // Flood-zone polygons load async in LeanMap once predictionLoading clears.
    if (asOf) {
      mapSource.value = 'replay';
      roadSource.value = 'replay';
      liveGauges.value = [];
      liveFloods.value = [];
      liveIncidents.value = [];
      liveRoute.value = null;
      mapLoading.value = false;
      syncBusyFlag();
      return;
    }

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

    const roadPromise = showRoutePanels.value
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

    const [roadData] = await Promise.all([roadPromise, mapPromise]);
    roadSource.value = roadData.source;
    liveIncidents.value = roadData.incidents ?? [];
    liveRoute.value = showRoutePanels.value ? roadData.route : null;
    if (showRoutePanels.value) {
      if (roadData.error && roadData.source === 'error') {
        statusNotes.value.push(`Roads unavailable: ${roadData.error}`);
      } else if (roadData.error) {
        statusNotes.value.push(`Roads (partial): ${roadData.error}`);
      }
    }
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
  // Seed route From for Transport even when starting in Live.
  const resolved = resolveRouteFromOnLoad({
    storedRoute: loadStoredRoute(),
    bookmarks: bookmarks.value,
    fallbackFrom: DEFAULT_ROUTE.from,
  });
  routeFrom.value = resolved.from;
  if (resolved.bookmark && !activePlace.value) {
    focusMapOn(resolved.bookmark.lat, resolved.bookmark.lng);
  }
}

/**
 * Re-run only the From→To route check (kept for later alternate view).
 */
async function runRouteCheck() {
  if (!showRoutePanels.value) return;
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
  if (!showRoutePanels.value) return;
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
  if (!showRoutePanels.value) {
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
  useCaseId.value = USE_CASE_HISTORY;
  if (!stormId) {
    selectedStormId.value = null;
    await loadLive();
    return;
  }
  const storm = storms.value.find((s) => s.id === stormId);
  if (!storm?.as_of) {
    selectedStormId.value = stormId;
    statusNotes.value.push('Storm has no as_of timestamp for replay.');
    return;
  }
  // Defer polygon layers before historyEvent changes so prediction gets the lake first.
  predictionLoading.value = true;
  selectedStormId.value = stormId;
  await loadLive({ asOf: storm.as_of });
}

function clearStormReplay() {
  selectedStormId.value = null;
  useCaseId.value = USE_CASE_LIVE;
  loadLive();
}

/**
 * Top-bar dashboard type: Live | History | Transport.
 * @param {string} id
 */
async function setUseCase(id) {
  if (id === USE_CASE_HISTORY) {
    useCaseId.value = USE_CASE_HISTORY;
    if (selectedStormId.value) {
      const storm = storms.value.find((s) => s.id === selectedStormId.value);
      if (storm?.as_of) {
        await loadLive({ asOf: storm.as_of });
        return;
      }
    }
    clearLiveFeeds();
    mapSource.value = 'replay';
    roadSource.value = 'replay';
    predictionDoc.value = null;
    predictionSource.value = 'pending';
    predictionLoading.value = false;
    mapLoading.value = false;
    loading.value = false;
    return;
  }
  selectedStormId.value = null;
  useCaseId.value = id === USE_CASE_TRANSPORT ? USE_CASE_TRANSPORT : USE_CASE_LIVE;
  await loadLive();
  if (useCaseId.value === USE_CASE_TRANSPORT) {
    const from = routeFrom.value.trim();
    const to = routeTo.value.trim();
    if (from && to) await runRouteCheck();
  }
}

watch([routeFrom, routeTo], ([from, to]) => {
  if (!showRoutePanels.value) return;
  saveStoredRoute({ from, to });
});

watch(useDemoFixtures, () => {
  if (useDemoFixtures.value) {
    useCaseId.value = USE_CASE_LIVE;
  } else {
    useCaseId.value = USE_CASE_LIVE;
  }
  selectedStormId.value = null;
  loadLive();
});

watch(scenarioId, () => {
  if (!useDemoFixtures.value) return;
  selected.value = null;
  useCaseId.value = USE_CASE_LIVE;
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
    if (mapSource.value === 'replay') return 'static';
    return mapSource.value === 'lake' ? 'lake' : 'static';
  }
  if (kind === 'roads') {
    if (roadSource.value === 'pending') return 'pending';
    if (roadSource.value === 'error') return 'pending';
    if (roadSource.value === 'replay') return 'static';
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
        <h1>
          {{
            replayMode
              ? 'Analyse place history'
              : transportMode
                ? 'Check transport'
                : 'Monitor place'
          }}
        </h1>
        <p>
          Corridor <code>{{ CORRIDOR_ID }}</code> —
          source: <strong>{{ dataSourceLabel }}</strong>
          <span v-if="predictionLoading || mapLoading"> · loading…</span>
          <span v-if="replayMode && selectedStorm"> · storm {{ selectedStorm.label }}</span>
        </p>
      </div>
      <div class="toolbar topbar-modes segmented" role="group" aria-label="Dashboard type">
        <button
          v-for="uc in USE_CASE_OPTIONS"
          :key="uc.id"
          type="button"
          :aria-pressed="useCaseId === uc.id"
          :disabled="loading"
          @click="setUseCase(uc.id)"
        >
          {{ uc.label }}
        </button>
      </div>
      <div class="toolbar" role="toolbar" aria-label="Data controls">
        <div class="segmented" role="group" aria-label="Data source">
          <button
            type="button"
            :aria-pressed="!useDemoFixtures"
            :disabled="loading"
            @click="useDemoFixtures = false"
          >
            Live lake
          </button>
          <button
            type="button"
            :aria-pressed="useDemoFixtures"
            :disabled="loading"
            @click="useDemoFixtures = true"
          >
            Demo fixtures
          </button>
        </div>
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
        <button
          type="button"
          :disabled="loading || (replayMode && !selectedStorm)"
          @click="replayMode && selectedStorm ? loadLive({ asOf: selectedStorm.as_of }) : loadLive()"
        >
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
              <template v-if="replayMode">
                Pick a past event below to run as-of prediction and map
                {{ activeUseCase.floodZonesLabel }}. Use Live for gauges and warnings.
              </template>
              <template v-else-if="transportMode">
                Set From → To to check corridor road exposure, incidents, and route geometry.
                Live keeps place gauges; History is storm replay.
              </template>
              <template v-else>
                Live gauges and {{ activeUseCase.floodZonesLabel }} load for this map area
                ({{ CORRIDOR_CENTER.radiusKm }} km). Switch to History or Transport for other jobs.
              </template>
            </p>
          </div>
          <BookmarksPanel
            v-if="panels.bookmarks"
            :bookmarks="bookmarks"
            :authenticated="bookmarksAuthenticated"
            :loading="bookmarksLoading"
            :disabled="loading"
            :place-mode="!showRoutePanels"
            @select="showRoutePanels ? applyBookmarkFrom($event) : applyBookmarkPlace($event)"
          />
          <StormReplayPanel
            v-if="panels.stormReplay"
            :storms="storms"
            :selected-id="selectedStormId"
            :loading="predictionLoading"
            :source="stormsPanelSource"
            :replay-active="replayMode"
            @select="onSelectStorm"
            @clear="clearStormReplay"
          />
          <PlaceHistoryPanel
            v-if="panels.placeHistory"
            :incidents="placeIncidents"
            :source="stormsPanelSource"
            :selected-id="selectedStormId"
            @select="onSelectStorm"
          />
          <template v-if="panels.activeRoute">
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
              v-if="panels.recentRoutes"
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
            :show-dispatch="activeUseCase.showDispatch"
          />
          <div v-else-if="replayMode && !selectedStorm" class="box outlook primary-panel">
            <PanelHeading source="pending">Storm replay · historic EA analogues</PanelHeading>
            <p class="title">Pick a historical event</p>
            <p class="copy">
              Choose a storm from the sidebar to run corridor prediction as-of that date.
            </p>
          </div>
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

          <div v-if="panels.yourRisk || panels.placeOutlook" class="grid-2 support-grid">
            <div v-if="panels.yourRisk" class="box" :class="{ 'is-waiting': mapLoading }">
              <PanelHeading :source="mapPanelSource">Your risk</PanelHeading>
              <template v-if="mapLoading">
                <p class="waiting-copy">Waiting for risk signals…</p>
              </template>
              <template v-else>
                <p class="title">{{ houseRisk }}</p>
                <p class="copy">{{ roadsRisk }}</p>
              </template>
            </div>
            <div
              v-if="panels.placeOutlook"
              class="box"
              :class="{ 'is-waiting': predictionLoading && mapLoading }"
            >
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

          <template v-if="panels.routeCheck">
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
            v-if="panels.corridorRisk"
            class="corridor-summary"
            :floods="floods"
            :incidents="incidents"
            :elevated-count="elevatedCount"
            :headline="corridorHeadline"
            :guidance="corridorGuidance"
            :route-label="showRoutePanels ? routeLabel : (predictionDoc?.prediction?.verdictLabel || '—')"
            :loading="mapLoading && predictionLoading"
            :route-loading="routeChecking"
            :replay-mode="replayMode"
            :show-flood-exposure="panels.floodExposure"
            :show-current-route="panels.currentRoute"
            :corridor-source="eitherPanelSource"
            :flood-source="mapPanelSource"
            :route-source="showRoutePanels ? roadsPanelSource : predictionPanelSource"
          />

          <div
            class="map-shell map-shell-place"
            :class="{ 'is-waiting': mapLoading && !replayMode }"
          >
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
              :show-presets="activeUseCase.showMapPresets"
              :layer-status-label="activeUseCase.floodZonesLabel"
              :selected-id="selected?.id ?? null"
              :history-event="replayMode ? selectedStorm : null"
              :defer-heavy-layers="predictionLoading"
              :source="mapPanelSource"
              @select="onSelect"
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
            v-if="panels.riverResponse"
            :gauges="gauges"
            :loading="mapLoading"
            :replay-mode="false"
            :source="mapPanelSource"
            :selected-id="selected?.id ?? null"
            @select="onSelect"
          />
        </div>
      </div>
    </div>
  </div>
</template>
