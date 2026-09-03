import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import PredictionPanel from '../components/PredictionPanel.vue';
import predictionRisk from '../data/prediction-risk.json';
import predictionStable from '../data/prediction-stable.json';

describe('PredictionPanel', () => {
  it('renders at-risk verdict and affected areas', () => {
    const wrapper = mount(PredictionPanel, {
      props: { predictionDoc: predictionRisk, gauges: [] },
    });
    expect(wrapper.text()).toContain(predictionRisk.prediction.verdictLabel);
    expect(wrapper.text()).toContain('Muchelney low lanes');
    expect(wrapper.text()).toContain('historic_analogue_v1');
  });

  it('renders clear verdict without affected areas list items', () => {
    const wrapper = mount(PredictionPanel, {
      props: { predictionDoc: predictionStable, gauges: [] },
    });
    expect(wrapper.text()).toContain('No predicted impact');
    expect(wrapper.text()).toContain('None in prediction window');
  });

  it('shows timed impact when hours present', () => {
    const wrapper = mount(PredictionPanel, {
      props: { predictionDoc: predictionRisk, gauges: [] },
    });
    expect(wrapper.text()).toMatch(/~\d+(\.\d+)?h to impact/);
  });
});
