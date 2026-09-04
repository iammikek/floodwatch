import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import PredictionPanel from '../components/PredictionPanel.vue';
import SourceDot from '../components/SourceDot.vue';
import predictionRisk from '../data/prediction-risk.json';
import predictionStable from '../data/prediction-stable.json';

describe('PredictionPanel', () => {
  it('renders at-risk verdict and affected areas', () => {
    const wrapper = mount(PredictionPanel, {
      props: { predictionDoc: predictionRisk, gauges: [], source: 'static' },
    });
    expect(wrapper.text()).toContain(predictionRisk.prediction.verdictLabel);
    expect(wrapper.text()).toContain('Muchelney low lanes');
    expect(wrapper.text()).toContain('historic_analogue_v1');
  });

  it('renders a stage height key for the supporting gauge chart', () => {
    const liveDoc = structuredClone(predictionRisk);
    liveDoc.observables.keyGaugeId = 'gauge-gaw-bridge';
    liveDoc.observables.primaryMeasureId = '52119-level-stage-i-15_min-mASD';
    liveDoc.observables.primaryAnalysis = { level: 1.42, p95: 2.1 };
    liveDoc.drivers = [
      {
        type: 'gauge_trajectory',
        ref: '52119-level-stage-i-15_min-mASD',
        label: 'Gaw Bridge · River Parrett',
        signal: 'rising',
      },
      ...liveDoc.drivers,
    ];
    const wrapper = mount(PredictionPanel, {
      props: { predictionDoc: liveDoc, gauges: [], source: 'lake' },
    });
    expect(wrapper.text()).toContain('Supporting · Gaw Bridge (m)');
    expect(wrapper.text()).toContain('Height key');
    expect(wrapper.text()).toContain('Typical high');
    expect(wrapper.text()).toContain('2.10 m');
    expect(wrapper.text()).toContain('Stage metres');
  });

  it('renders clear verdict without affected areas list items', () => {
    const wrapper = mount(PredictionPanel, {
      props: { predictionDoc: predictionStable, gauges: [], source: 'static' },
    });
    expect(wrapper.text()).toContain('No predicted impact');
    expect(wrapper.text()).toContain('None in prediction window');
  });

  it('shows timed impact when hours present', () => {
    const wrapper = mount(PredictionPanel, {
      props: { predictionDoc: predictionRisk, gauges: [], source: 'static' },
    });
    expect(wrapper.text()).toMatch(/~\d+(\.\d+)?h to impact/);
  });

  it('marks supporting charts lake vs static from live series presence', () => {
    const liveDoc = structuredClone(predictionRisk);
    liveDoc.observables.rainfallUpstreamMm = [];
    liveDoc.observables.primaryAnalysis = { p95: 2.1 };
    const wrapper = mount(PredictionPanel, {
      props: { predictionDoc: liveDoc, gauges: [], source: 'lake' },
    });
    const sources = wrapper.findAllComponents(SourceDot).map((d) => d.props('source'));
    // rainfall empty → static; gauge series present → lake; panel heading → lake
    expect(sources).toContain('lake');
    expect(sources).toContain('static');
  });
});
