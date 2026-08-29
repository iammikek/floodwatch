<script setup>
import { computed, ref, watch } from 'vue';
import scenarioRisk from './data/scenario-risk.json';
import scenarioStable from './data/scenario-stable.json';
import trendsRisk from './data/trends-risk.json';
import trendsStable from './data/trends-stable.json';
import { PRESETS } from './data/presets.js';
import { seriesForGauge } from './data/expandSeries.js';
import CorridorRisk from './components/CorridorRisk.vue';
import RiverResponse from './components/RiverResponse.vue';
import LeanMap from './components/LeanMap.vue';
import InspectorPanel from './components/InspectorPanel.vue';
import UpstreamOutlook from './components/UpstreamOutlook.vue';

const scenarios = {
  risk: scenarioRisk,
  stable: scenarioStable,
};

const trendsByScenario = {
  risk: trendsRisk,
  stable: trendsStable,
};

const scenarioId = ref('risk');
const presetId = ref('dispatch');
const selected = ref(null);

const scenario = computed(() => scenarios[scenarioId.value]);
const trends = computed(() => trendsByScenario[scenarioId.value]);
const preset = computed(() => PRESETS[presetId.value]);

const selectedSeries = computed(() => {
  if (!selected.value || selected.value.type !== 'gauge') return [];
  return seriesForGauge(trends.value, selected.value.id);
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

const floodSummary = computed(() => {
  const floods = scenario.value.floods;
  if (!floods.length) return 'No flood warnings in view.';
  const counts = {};
  for (const f of floods) {
    counts[f.severity] = (counts[f.severity] || 0) + 1;
  }
  return Object.entries(counts)
    .map(([label, n]) => `${label}: ${n}`)
    .join('; ');
});

const roadSummary = computed(() => {
  const incidents = scenario.value.incidents;
  if (!incidents.length) return 'Roads clear';
  return incidents.map((i) => `${i.road} ${i.statusLabel}`).join('; ');
});
</script>

<template>
  <div class="page">
    <header class="topbar">
      <div>
        <h1>Operator cockpit · Vue prototype</h1>
        <p>Lean map + inspector sparklines + upstream outlook (mock JSON).</p>
      </div>
    </header>

    <div class="note">
      <strong>Prototype only.</strong> Scenarios in
      <code>src/data/scenario-*.json</code>; trends in
      <code>src/data/trends-*.json</code>. Not wired to Laravel or the lake.
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
        floodwatch.local / cockpit · Vue island mock
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

          <UpstreamOutlook :trends="trends" :gauges="scenario.riverLevels" />

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

          <div class="grid-4">
            <div class="box">
              <p class="label">Flood exposure</p>
              <p class="copy">{{ floodSummary }}</p>
            </div>
            <div class="box">
              <p class="label">Road status</p>
              <p class="copy">{{ roadSummary }}</p>
            </div>
            <div class="box">
              <p class="label">Current route</p>
              <p class="copy">{{ scenario.route.summary }}</p>
            </div>
            <div class="box">
              <p class="label">Forecast outlook</p>
              <p class="copy">{{ scenario.forecastOutlook }}</p>
            </div>
          </div>
          <p class="annot">Summary cards may be redundant with corridor strip — review later</p>

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
