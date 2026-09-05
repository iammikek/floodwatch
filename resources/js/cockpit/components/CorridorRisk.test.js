import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import CorridorRisk from '../components/CorridorRisk.vue';

describe('CorridorRisk', () => {
  it('counts flood severities', () => {
    const wrapper = mount(CorridorRisk, {
      props: {
        floods: [
          { severity: 'Flood Warning', severityLevel: 2 },
          { severity: 'Flood Alert', severityLevel: 3 },
          { severity: 'Severe Flood Warning', severityLevel: 1 },
        ],
        incidents: [{ id: 1 }],
        elevatedCount: 2,
        headline: 'Test headline',
        guidance: 'Guidance',
        routeLabel: 'At risk',
      },
    });
    expect(wrapper.text()).toContain('Test headline');
    expect(wrapper.text()).toContain('At risk');
    // severe / warning / alert counts appear as numbers
    const stats = wrapper.findAll('.stat b').map((n) => n.text());
    expect(stats).toEqual(expect.arrayContaining(['1', '1', '1']));
  });

  it('greys out and hides counts while loading', () => {
    const wrapper = mount(CorridorRisk, {
      props: {
        floods: [{ severity: 'Flood Warning', severityLevel: 2 }],
        incidents: [{ id: 1 }],
        elevatedCount: 2,
        headline: 'Test headline',
        guidance: 'Guidance',
        routeLabel: 'Clear',
        loading: true,
      },
    });
    expect(wrapper.classes()).toContain('is-waiting');
    expect(wrapper.text()).toContain('Waiting for corridor signals');
    expect(wrapper.text()).not.toContain('Test headline');
    expect(wrapper.text()).not.toContain('Clear');
    expect(wrapper.findAll('.stat')).toHaveLength(0);
  });

  it('greys only the Current route tile during routeLoading', () => {
    const wrapper = mount(CorridorRisk, {
      props: {
        floods: [{ severity: 'Flood Warning', severityLevel: 2 }],
        incidents: [{ id: 1 }],
        elevatedCount: 2,
        headline: 'Test headline',
        guidance: 'Guidance',
        routeLabel: 'Clear',
        routeLoading: true,
      },
    });
    expect(wrapper.classes()).not.toContain('is-waiting');
    expect(wrapper.text()).toContain('Test headline');
    expect(wrapper.text()).toContain('Waiting for route status');
    expect(wrapper.text()).not.toContain('Clear');
    expect(wrapper.findAll('.stat b').map((n) => n.text())).toEqual(
      expect.arrayContaining(['1']),
    );
  });

  it('hides Current route when showCurrentRoute is false', () => {
    const wrapper = mount(CorridorRisk, {
      props: {
        floods: [{ severity: 'Flood Warning', severityLevel: 2 }],
        incidents: [{ id: 1 }],
        elevatedCount: 2,
        headline: 'Live place',
        guidance: 'Guidance',
        routeLabel: 'unused',
        showCurrentRoute: false,
      },
    });
    expect(wrapper.text()).toContain('Flood exposure');
    expect(wrapper.text()).not.toContain('Current route');
  });
});
