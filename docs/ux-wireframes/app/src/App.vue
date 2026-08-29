<script setup>
import { computed, ref, watch } from 'vue';
import scenarioRisk from './data/scenario-risk.json';
import scenarioStable from './data/scenario-stable.json';
import predictionRisk from './data/prediction-risk.json';
import predictionStable from './data/prediction-stable.json';
import { PRESETS } from './data/presets.js';
import { seriesForGauge } from './data/expandSeries.js';
import CorridorRisk from './components/CorridorRisk.vue';
import RiverResponse from './components/RiverResponse.vue';
import LeanMap from './components/LeanMap.vue';
import InspectorPanel from './components/InspectorPanel.vue';
import PredictionPanel from './components/PredictionPanel.vue';

const scenarios = {
  risk: scenarioRisk,
  stable: scenarioStable,
};

const predictionsByScenario = {
  risk: predictionRisk,
  stable: predictionStable,
};

const scenarioId = ref('risk');
const presetId = ref('dispatch');
const selected = ref(null);

const scenario = computed(() => scenarios[scenarioId.value]);
const predictionDoc = computed(() => predictionsByScenario[scenarioId.value]);
const preset = computed(() => PRESETS[presetId.value]);

const selectedSeries = computed(() => {
  if (!selected.value || selected.value.type !== 'gauge') return [];
  return seriesForGauge(predictionDoc.value, selected.value.id);
});

watch(scenarioId, () => {
  selected.value = null;
  presetId.value = scenario.value.preset || 'dispatch';
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
          Historic-analogue prediction panel (mock lake contract) + lean map.
          Live pins are context — prediction is the product.
        </p>
      </div>
    </header>

    <div class="note">
      <strong>Product intent.</strong> Predictions from mined EA history (mock
      <code>floodwatch.prediction.v0</code>). See
      <code>docs/ux-wireframes/prediction-contract.md</code>. Not a live model yet.
    </div>

    <div class="toolbar" role="group" aria-label="Scenario">
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
    </div>

    <div class="frame">
      <div class="chrome">
        <span class="dot" /><span class="dot" /><span class="dot" />
        floodwatch.local / cockpit · prediction-first mock
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

          <PredictionPanel :prediction-doc="predictionDoc" :gauges="scenario.riverLevels" />

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
            <InspectorPanel :feature="selected" :series="selectedSeries" />
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
