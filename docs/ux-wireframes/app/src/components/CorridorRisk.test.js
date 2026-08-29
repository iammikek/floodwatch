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
});
