<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import scenarioRisk from './data/scenario-risk.json';
import scenarioStable from './data/scenario-stable.json';
import { PRESETS } from './data/presets.js';
import { seriesForGauge } from './data/expandSeries.js';
import { fetchPrediction } from './lib/fetchPrediction.js';
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

const scenarioId = ref('risk');
const presetId = ref('dispatch');
const selected = ref(null);
const predictionDoc = ref(null);
const predictionSource = ref('mock');
const predictionError = ref(null);
const loadingPrediction = ref(false);
const inspectorRef = ref(null);

const scenario = computed(() => scenarios[scenarioId.value]);
const preset = computed(() => PRESETS[presetId.value]);

const selectedSeries = computed(() => {
  if (!selected.value || selected.value.type !== 'gauge' || !predictionDoc.value) return [];
  return seriesForGauge(predictionDoc.value, selected.value.id);
});

async function loadPrediction() {
  loadingPrediction.value = true;
  predictionError.value = null;
  try {
    const { source, doc, error } = await fetchPrediction(CORRIDOR_ID, {
      scenarioId: scenarioId.value,
    });
    predictionSource.value = source;
    predictionDoc.value = doc;
    if (error && source === 'mock') {
      predictionError.value = `Live prediction unavailable (${error}); showing mock.`;
    }
  } catch (err) {
    predictionError.value = err instanceof Error ? err.message : String(err);
  } finally {
    loadingPrediction.value = false;
  }
}

watch(scenarioId, () => {
  selected.value = null;
  presetId.value = scenario.value.preset || 'dispatch';
  loadPrediction();
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
  loadPrediction();
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

const elevatedCount = computed(
  () => scenario.value.riverLevels.filter((g) => g.levelStatus === 'elevated').length,
);
</script>

<template>
  <div class="page">
    <header class="topbar">
      <div>
        <h1>Operator cockpit · prediction prototype</h1>
        <p>
          Corridor <code>{{ CORRIDOR_ID }}</code> —
          source: <strong>{{ predictionSource }}</strong>
          <span v-if="loadingPrediction"> · loading…</span>
        </p>
      </div>
    </header>

    <div class="note">
      <strong>Product intent.</strong> Predictions from mined EA stage history via
      Laravel <code>GET /flood-watch/predictions</code> (falls back to mock JSON if the lake is down).
    </div>

    <div v-if="predictionError" class="note" style="border-color: #7a1f1f; background: #fdecec">
      <strong>Prediction notice.</strong> {{ predictionError }}
    </div>

    <div class="toolbar" role="group" aria-label="Scenario">
      <button
        type="button"
        :aria-pressed="scenarioId === 'risk'"
        @click="setScenario('risk')"
      >
        Scenario: at risk (mock map chrome)
      </button>
      <button
        type="button"
        :aria-pressed="scenarioId === 'stable'"
        @click="setScenario('stable')"
      >
        Scenario: stable (mock map chrome)
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
            <p class="title" style="font-size: 0.95rem">{{ scenario.location.label }}</p>
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
              <p class="title">{{ scenario.houseRisk }}</p>
              <p class="copy">{{ scenario.roadsRisk }}</p>
            </div>
            <div class="box">
              <p class="label">Route check</p>
              <p class="title">{{ scenario.route.verdictLabel }}</p>
              <p class="copy">{{ scenario.route.summary }}</p>
            </div>
          </div>

          <CorridorRisk
            :floods="scenario.floods"
            :incidents="scenario.incidents"
            :elevated-count="elevatedCount"
            :headline="scenario.corridor.headline"
            :guidance="scenario.corridor.guidance"
            :route-label="scenario.route.verdictLabel"
          />

          <PredictionPanel
            v-if="predictionDoc"
            :prediction-doc="predictionDoc"
            :gauges="scenario.riverLevels"
          />

          <div class="map-shell">
            <LeanMap
              :center="scenario.location.center"
              :zoom="scenario.location.zoom"
              :floods="scenario.floods"
              :incidents="scenario.incidents"
              :gauges="scenario.riverLevels"
              :route-geometry="scenario.route.geometry"
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
            :gauges="scenario.riverLevels"
            :selected-id="selected?.id ?? null"
            @select="onSelect"
          />
        </div>
      </div>
    </div>
  </div>
</template>
