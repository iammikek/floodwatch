<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import scenarioRisk from './data/scenario-risk.json';
import scenarioStable from './data/scenario-stable.json';
import { PRESETS } from './data/presets.js';
import { seriesForGauge } from './data/expandSeries.js';
import { fetchPrediction } from './lib/fetchPrediction.js';
import { CORRIDOR_CENTER, fetchLiveMapData } from './lib/fetchLiveMapData.js';
import { DEFAULT_ROUTE, fetchLiveRoadData, fetchRouteCheck } from './lib/fetchLiveRoadData.js';
import CorridorRisk from './components/CorridorRisk.vue';
import RiverResponse from './components/RiverResponse.vue';
import LeanMap from './components/LeanMap.vue';
import InspectorPanel from './components/InspectorPanel.vue';
import PredictionPanel from './components/PredictionPanel.vue';
import PanelHeading from './components/PanelHeading.vue';
import RouteCheckForm from './components/RouteCheckForm.vue';

const CORRIDOR_ID = 'a361-muchelney';

const scenarios = {
  risk: scenarioRisk,
  stable: scenarioStable,
};

/** Demo fixtures only — default path is live lake + Laravel road feeds. */
const useDemoFixtures = ref(false);
const scenarioId = ref('risk');
const presetId = ref('dispatch');
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
const loading = ref(true);
const routeChecking = ref(false);
const routeFrom = ref(DEFAULT_ROUTE.from);
const routeTo = ref(DEFAULT_ROUTE.to);
const inspectorRef = ref(null);

const scenario = computed(() => scenarios[scenarioId.value]);
const preset = computed(() => PRESETS[presetId.value]);
const routeBusy = computed(() => loading.value || routeChecking.value);

const floods = computed(() => {
  if (loading.value) return [];
  return useDemoFixtures.value ? scenario.value.floods : liveFloods.value;
});
const gauges = computed(() => {
  if (loading.value) return [];
  return useDemoFixtures.value ? scenario.value.riverLevels : liveGauges.value;
});
const incidents = computed(() => {
  if (loading.value) return [];
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
const locationLabel = computed(() =>
  useDemoFixtures.value ? scenario.value.location.label : CORRIDOR_CENTER.label,
);

const houseRisk = computed(() => {
  if (loading.value) return '';
  if (useDemoFixtures.value) return scenario.value.houseRisk;
  if (floods.value.some((f) => (f.severityLevel ?? 4) <= 2)) {
    return 'House / area: flood warnings active nearby';
  }
  if (elevatedCount.value > 0) return 'House / area: elevated river levels nearby';
  return 'House / area: no elevated lake signals in view';
});

const roadsRisk = computed(() => {
  if (loading.value) return '';
  if (useDemoFixtures.value) return scenario.value.roadsRisk;
  const n = incidents.value.length;
  if (n > 0) return `Roads: ${n} live incident(s) near corridor`;
  return floods.value.length > 0
    ? `Roads: ${floods.value.length} lake warning(s); no road incidents in view`
    : 'Roads: no live incidents or lake warnings in view';
});

const corridorHeadline = computed(() => {
  if (loading.value) return '';
  if (useDemoFixtures.value) return scenario.value.corridor.headline;
  if (liveRoute.value?.verdictLabel && liveRoute.value.verdict !== 'error') {
    return `${liveRoute.value.verdictLabel} — ${liveRoute.value.from} → ${liveRoute.value.to}`;
  }
  const verdict = predictionDoc.value?.prediction?.verdictLabel;
  if (verdict) return verdict;
  if (floods.value.some((f) => (f.severityLevel ?? 4) <= 2)) {
    return 'Flood warnings are active on the monitored corridor.';
  }
  if (elevatedCount.value > 0) return 'River levels are elevated near the corridor.';
  return 'No elevated lake signals for this corridor window.';
});

const corridorGuidance = computed(() => {
  if (loading.value) return '';
  if (useDemoFixtures.value) return scenario.value.corridor.guidance;
  if (liveRoute.value?.summary) return liveRoute.value.summary;
  return (
    predictionDoc.value?.prediction?.summary ||
    'Live gauges, warnings, incidents, and route check via Laravel.'
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
  return 'Route check unavailable — showing prediction fallback.';
});

const routeGeometry = computed(() => {
  if (loading.value) return [];
  if (useDemoFixtures.value) return scenario.value.route.geometry;
  return liveRoute.value?.routeGeometry ?? [];
});

const selectedSeries = computed(() => {
  if (!selected.value || selected.value.type !== 'gauge' || !predictionDoc.value) return [];
  return seriesForGauge(predictionDoc.value, selected.value.id);
});

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

async function loadLive() {
  loading.value = true;
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
      liveRoute.value = {
        verdict: scenario.value.route.verdict,
        verdictLabel: scenario.value.route.verdictLabel,
        summary: scenario.value.route.summary,
        routeGeometry: scenario.value.route.geometry,
        from: routeFrom.value,
        to: routeTo.value,
      };
      return;
    }

    const [prediction, mapData, roadData] = await Promise.all([
      fetchPrediction(CORRIDOR_ID, { scenarioId: scenarioId.value }),
      fetchLiveMapData({
        center: CORRIDOR_CENTER.center,
        mockGauges: scenario.value.riverLevels,
        mockFloods: scenario.value.floods,
      }),
      fetchLiveRoadData({
        lat: CORRIDOR_CENTER.center[0],
        lng: CORRIDOR_CENTER.center[1],
        from: routeFrom.value,
        to: routeTo.value,
        mockIncidents: scenario.value.incidents,
        mockRoute: {
          verdict: scenario.value.route.verdict,
          verdictLabel: scenario.value.route.verdictLabel,
          summary: scenario.value.route.summary,
          routeGeometry: scenario.value.route.geometry,
          from: routeFrom.value,
          to: routeTo.value,
        },
      }),
    ]);

    predictionSource.value = prediction.source;
    predictionDoc.value = prediction.doc;
    if (prediction.error && prediction.source === 'mock') {
      statusNotes.value.push(`Prediction: ${prediction.error} — using mock prediction.`);
    }

    mapSource.value = mapData.source;
    liveGauges.value = mapData.gauges;
    liveFloods.value = mapData.floods;
    if (mapData.error && mapData.source === 'mock') {
      statusNotes.value.push(`Map overlays: ${mapData.error} — using demo fixtures.`);
    }

    roadSource.value = roadData.source;
    liveIncidents.value = roadData.incidents;
    liveRoute.value = roadData.route;
    if (roadData.error && roadData.source === 'mock') {
      statusNotes.value.push(`Roads: ${roadData.error} — using demo fixtures.`);
    } else if (roadData.error) {
      statusNotes.value.push(`Roads (partial): ${roadData.error}`);
    }
  } catch (err) {
    statusNotes.value.push(err instanceof Error ? err.message : String(err));
  } finally {
    loading.value = false;
  }
}

/**
 * Re-run only the From→To route check (keeps gauges / prediction / incidents).
 */
async function runRouteCheck() {
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
      return;
    }

    const route = await fetchRouteCheck({ from, to });
    liveRoute.value = route;
    roadSource.value = 'live';
    if (route.verdict === 'error') {
      statusNotes.value.push(`Route check: ${route.summary || 'Could not resolve route.'}`);
    }
  } catch (err) {
    statusNotes.value.push(
      `Route check: ${err instanceof Error ? err.message : String(err)}`,
    );
  } finally {
    routeChecking.value = false;
  }
}

watch(useDemoFixtures, () => {
  if (useDemoFixtures.value) {
    presetId.value = scenario.value.preset || 'dispatch';
  }
  loadLive();
});

watch(scenarioId, () => {
  if (!useDemoFixtures.value) return;
  selected.value = null;
  presetId.value = scenario.value.preset || 'dispatch';
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
  loadLive();
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
  if (loading.value) return 'loading…';
  if (useDemoFixtures.value) return 'demo fixtures';
  return `prediction:${predictionSource.value} · map:${mapSource.value} · roads:${roadSource.value}`;
});

/** @param {'prediction'|'map'|'roads'|'either'} kind */
function resolvePanelSource(kind) {
  if (useDemoFixtures.value) return 'static';
  if (loading.value) return 'pending';
  if (kind === 'prediction') {
    if (predictionSource.value === 'pending') return 'pending';
    return predictionSource.value === 'lake' ? 'lake' : 'static';
  }
  if (kind === 'map') {
    if (mapSource.value === 'pending') return 'pending';
    return mapSource.value === 'lake' ? 'lake' : 'static';
  }
  if (kind === 'roads') {
    if (roadSource.value === 'pending') return 'pending';
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

const inspectorPanelSource = computed(() => {
  if (!selected.value) return 'static';
  if (selected.value.type === 'incident') return roadsPanelSource.value;
  if (selected.value.type === 'gauge') return mapPanelSource.value;
  if (selected.value.type === 'warning') return mapPanelSource.value;
  return 'static';
});
</script>

<template>
  <div class="page">
    <header class="topbar">
      <div>
        <h1>Operator cockpit</h1>
        <p>
          Corridor <code>{{ CORRIDOR_ID }}</code> —
          source: <strong>{{ dataSourceLabel }}</strong>
          <span v-if="loading"> · loading…</span>
        </p>
      </div>
    </header>

    <div class="note">
      <strong>Product intent.</strong> Cockpit is fed by the data lake and Laravel road services:
      <code>GET /flood-watch/predictions</code>,
      <code>GET /flood-watch/river-levels</code>,
      <code>GET /api/lake/warnings</code>,
      <code>GET /flood-watch/incidents</code>,
      <code>GET /flood-watch/route-check</code>.
      Demo fixtures are opt-in only.
    </div>

    <div
      v-for="(note, idx) in statusNotes"
      :key="idx"
      class="note"
      style="border-color: #7a1f1f; background: #fdecec"
    >
      <strong>Notice.</strong> {{ note }}
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
      <button type="button" @click="loadLive" :disabled="loading">
        Refresh
      </button>
    </div>

    <div class="frame">
      <div class="chrome">
        <span class="dot" /><span class="dot" /><span class="dot" />
        floodwatch.local · prediction-first
      </div>

      <div class="layout">
        <aside class="sidebar">
          <div class="box dashed">
            <PanelHeading source="static">Search / location</PanelHeading>
            <p class="title" style="font-size: 0.95rem">{{ locationLabel }}</p>
            <p class="copy">Postcode / place · bookmarks (placeholder)</p>
          </div>
          <div class="box dashed">
            <PanelHeading source="static">History</PanelHeading>
            <p class="copy">Recent searches…</p>
          </div>
        </aside>

        <div class="main">
          <div class="grid-2">
            <div class="box" :class="{ 'is-waiting': loading }">
              <PanelHeading :source="mapPanelSource">Your risk</PanelHeading>
              <template v-if="loading">
                <p class="waiting-copy">Waiting for risk signals…</p>
              </template>
              <template v-else>
                <p class="title">{{ houseRisk }}</p>
                <p class="copy">{{ roadsRisk }}</p>
              </template>
            </div>
            <div class="box">
              <PanelHeading :source="roadsPanelSource">Route check</PanelHeading>
              <RouteCheckForm
                v-model:from="routeFrom"
                v-model:to="routeTo"
                :disabled="loading"
                :checking="routeChecking"
                @check="runRouteCheck"
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

          <CorridorRisk
            :floods="floods"
            :incidents="incidents"
            :elevated-count="elevatedCount"
            :headline="corridorHeadline"
            :guidance="corridorGuidance"
            :route-label="routeLabel"
            :loading="loading"
            :route-loading="routeChecking"
            :corridor-source="eitherPanelSource"
            :flood-source="mapPanelSource"
            :route-source="roadsPanelSource"
          />

          <PredictionPanel
            v-if="predictionDoc && !loading"
            :prediction-doc="predictionDoc"
            :gauges="gauges"
            :source="predictionPanelSource"
          />
          <div v-else-if="loading" class="box outlook is-waiting">
            <PanelHeading source="pending">Corridor prediction · historic EA analogues</PanelHeading>
            <p class="waiting-copy">Waiting for prediction…</p>
          </div>

          <div class="map-shell" :class="{ 'is-waiting': loading }">
            <LeanMap
              :center="mapCenter"
              :zoom="mapZoom"
              :floods="floods"
              :incidents="incidents"
              :gauges="gauges"
              :route-geometry="routeGeometry"
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
            :loading="loading"
            :source="mapPanelSource"
            :selected-id="selected?.id ?? null"
            @select="onSelect"
          />
        </div>
      </div>
    </div>
  </div>
</template>
