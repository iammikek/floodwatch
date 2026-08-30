<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import scenarioRisk from './data/scenario-risk.json';
import scenarioStable from './data/scenario-stable.json';
import { PRESETS } from './data/presets.js';
import { seriesForGauge } from './data/expandSeries.js';
import { fetchPrediction } from './lib/fetchPrediction.js';
import { CORRIDOR_CENTER, fetchLiveMapData } from './lib/fetchLiveMapData.js';
import CorridorRisk from './components/CorridorRisk.vue';
import RiverResponse from './components/RiverResponse.vue';
import LeanMap from './components/LeanMap.vue';
import InspectorPanel from './components/InspectorPanel.vue';
import PredictionPanel from './components/PredictionPanel.vue';

const CORRIDOR_ID = 'a361-muchelney';

const scenarios = {
  risk: scenarioRisk,
  stable: scenarioStable,
};

/** Demo fixtures only — default path is live lake via Laravel proxies. */
const useDemoFixtures = ref(false);
const scenarioId = ref('risk');
const presetId = ref('dispatch');
const selected = ref(null);
const predictionDoc = ref(null);
const predictionSource = ref('pending');
const mapSource = ref('pending');
const liveGauges = ref([]);
const liveFloods = ref([]);
const statusNotes = ref([]);
const loading = ref(true);
const inspectorRef = ref(null);

const scenario = computed(() => scenarios[scenarioId.value]);
const preset = computed(() => PRESETS[presetId.value]);

const floods = computed(() =>
  useDemoFixtures.value ? scenario.value.floods : liveFloods.value,
);
const gauges = computed(() =>
  useDemoFixtures.value ? scenario.value.riverLevels : liveGauges.value,
);
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
  if (useDemoFixtures.value) return scenario.value.houseRisk;
  if (floods.value.some((f) => (f.severityLevel ?? 4) <= 2)) {
    return 'House / area: flood warnings active nearby';
  }
  if (elevatedCount.value > 0) return 'House / area: elevated river levels nearby';
  return 'House / area: no elevated lake signals in view';
});

const roadsRisk = computed(() => {
  if (useDemoFixtures.value) return scenario.value.roadsRisk;
  return floods.value.length > 0
    ? `Roads: ${floods.value.length} lake warning(s) in corridor bbox`
    : 'Roads: no lake flood warnings in corridor bbox';
});

const corridorHeadline = computed(() => {
  if (useDemoFixtures.value) return scenario.value.corridor.headline;
  const verdict = predictionDoc.value?.prediction?.verdictLabel;
  if (verdict) return verdict;
  if (floods.value.some((f) => (f.severityLevel ?? 4) <= 2)) {
    return 'Flood warnings are active on the monitored corridor.';
  }
  if (elevatedCount.value > 0) return 'River levels are elevated near the corridor.';
  return 'No elevated lake signals for this corridor window.';
});

const corridorGuidance = computed(() => {
  if (useDemoFixtures.value) return scenario.value.corridor.guidance;
  return (
    predictionDoc.value?.prediction?.summary ||
    'Live gauges and warnings from the data lake via Laravel proxies.'
  );
});

const routeLabel = computed(() => {
  if (useDemoFixtures.value) return scenario.value.route.verdictLabel;
  const v = predictionDoc.value?.prediction?.verdict;
  if (v === 'likely_impassable' || v === 'at_risk') return 'At risk';
  if (v === 'watch') return 'Watch';
  return 'Clear';
});

const routeGeometry = computed(() =>
  useDemoFixtures.value ? scenario.value.route.geometry : [],
);

const selectedSeries = computed(() => {
  if (!selected.value || selected.value.type !== 'gauge' || !predictionDoc.value) return [];
  return seriesForGauge(predictionDoc.value, selected.value.id);
});

async function loadLive() {
  loading.value = true;
  statusNotes.value = [];
  selected.value = null;
  try {
    const [prediction, mapData] = await Promise.all([
      fetchPrediction(CORRIDOR_ID, {
        scenarioId: scenarioId.value,
        preferMock: useDemoFixtures.value,
      }),
      useDemoFixtures.value
        ? Promise.resolve({
            source: 'mock',
            gauges: scenario.value.riverLevels,
            floods: scenario.value.floods,
          })
        : fetchLiveMapData({
            center: CORRIDOR_CENTER.center,
            mockGauges: scenario.value.riverLevels,
            mockFloods: scenario.value.floods,
          }),
    ]);

    predictionSource.value = prediction.source;
    predictionDoc.value = prediction.doc;
    if (prediction.error && prediction.source === 'mock' && !useDemoFixtures.value) {
      statusNotes.value.push(`Prediction: ${prediction.error} — using mock prediction.`);
    }

    mapSource.value = mapData.source;
    liveGauges.value = mapData.gauges;
    liveFloods.value = mapData.floods;
    if (mapData.error && mapData.source === 'mock' && !useDemoFixtures.value) {
      statusNotes.value.push(`Map overlays: ${mapData.error} — using demo fixtures.`);
    }
  } catch (err) {
    statusNotes.value.push(err instanceof Error ? err.message : String(err));
  } finally {
    loading.value = false;
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
  return `prediction:${predictionSource.value} · map:${mapSource.value}`;
});
</script>

<template>
  <div class="page">
    <header class="topbar">
      <div>
        <h1>Operator cockpit · prediction prototype</h1>
        <p>
          Corridor <code>{{ CORRIDOR_ID }}</code> —
          source: <strong>{{ dataSourceLabel }}</strong>
          <span v-if="loading"> · loading…</span>
        </p>
      </div>
    </header>

    <div class="note">
      <strong>Product intent.</strong> Cockpit is fed by the data lake through Laravel:
      <code>GET /flood-watch/predictions</code>,
      <code>GET /flood-watch/river-levels</code>,
      <code>GET /api/lake/warnings</code>.
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
        floodwatch.local / cockpit · prediction-first
      </div>

      <div class="layout">
        <aside class="sidebar">
          <div class="box dashed">
            <p class="label">Search / location</p>
            <p class="title" style="font-size: 0.95rem">{{ locationLabel }}</p>
            <p class="copy">Postcode / place · bookmarks (placeholder)</p>
          </div>
          <div class="box dashed">
            <p class="label">History</p>
            <p class="copy">Recent searches…</p>
          </div>
        </aside>

        <div class="main">
          <div class="grid-2">
            <div class="box">
              <p class="label">Your risk</p>
              <p class="title">{{ houseRisk }}</p>
              <p class="copy">{{ roadsRisk }}</p>
            </div>
            <div class="box">
              <p class="label">Route check</p>
              <p class="title">{{ routeLabel }}</p>
              <p class="copy">
                {{
                  useDemoFixtures
                    ? scenario.route.summary
                    : 'Route geometry not wired yet — verdict from lake prediction.'
                }}
              </p>
            </div>
          </div>

          <CorridorRisk
            :floods="floods"
            :incidents="useDemoFixtures ? scenario.incidents : []"
            :elevated-count="elevatedCount"
            :headline="corridorHeadline"
            :guidance="corridorGuidance"
            :route-label="routeLabel"
          />

          <PredictionPanel
            v-if="predictionDoc"
            :prediction-doc="predictionDoc"
            :gauges="gauges"
          />

          <div class="map-shell">
            <LeanMap
              :center="mapCenter"
              :zoom="mapZoom"
              :floods="floods"
              :incidents="useDemoFixtures ? scenario.incidents : []"
              :gauges="gauges"
              :route-geometry="routeGeometry"
              :preset="preset"
              :selected-id="selected?.id ?? null"
              @select="onSelect"
              @update:preset="setPreset"
            />
            <div ref="inspectorRef" tabindex="-1" class="inspector-focus">
              <InspectorPanel :feature="selected" :series="selectedSeries" />
            </div>
          </div>

          <RiverResponse
            :gauges="gauges"
            :loading="loading"
            :selected-id="selected?.id ?? null"
            @select="onSelect"
          />
        </div>
      </div>
    </div>
  </div>
</template>
