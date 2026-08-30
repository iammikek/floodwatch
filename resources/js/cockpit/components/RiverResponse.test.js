import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import RiverResponse from './RiverResponse.vue';

const gauges = [
  { id: 'g1', station: 'High', river: 'Parrett', value: 2.4, unit: 'm', levelStatus: 'elevated', dateTime: '2026-08-30T12:00:00Z' },
  { id: 'g2', station: 'Mid', river: 'Parrett', value: 1.8, unit: 'm', levelStatus: 'expected', dateTime: '2026-08-30T12:00:00Z' },
  { id: 'g3', station: 'Top expected', river: 'Yeo', value: 3.1, unit: 'm', levelStatus: 'expected', dateTime: '2026-08-30T12:00:00Z' },
  { id: 'g4', station: 'Low one', river: 'Brue', value: 0.2, unit: 'm', levelStatus: 'low', dateTime: '2026-08-30T12:00:00Z' },
];

describe('RiverResponse status filters', () => {
  it('defaults to elevated-first priority list', () => {
    const wrapper = mount(RiverResponse, { props: { gauges } });
    const cards = wrapper.findAll('.gauge-card');
    expect(cards[0].text()).toContain('High');
    expect(wrapper.text()).toContain('Priority gauges');
  });

  it('filters priority gauges when a status tile is pressed', async () => {
    const wrapper = mount(RiverResponse, { props: { gauges } });
    const filters = wrapper.findAll('button.status-filter');
    // Elevated, Expected, Low, Monitored
    await filters[1].trigger('click'); // expected
    expect(wrapper.text()).toContain('Priority gauges · expected');
    const cards = wrapper.findAll('.gauge-card');
    expect(cards).toHaveLength(2);
    expect(cards[0].text()).toContain('Top expected');
    expect(cards.every((c) => c.text().includes('expected'))).toBe(true);
  });

  it('shows empty copy when filter has no matches after data change', async () => {
    const wrapper = mount(RiverResponse, { props: { gauges } });
    const filters = wrapper.findAll('button.status-filter');
    await filters[0].trigger('click'); // elevated
    expect(wrapper.findAll('.gauge-card')).toHaveLength(1);
    await wrapper.setProps({
      gauges: gauges.filter((g) => g.levelStatus !== 'elevated'),
    });
    expect(wrapper.text()).toContain('Priority gauges');
    expect(wrapper.text()).not.toContain('Priority gauges · elevated');
  });

  it('Monitored resets to the unfiltered priority list', async () => {
    const wrapper = mount(RiverResponse, { props: { gauges } });
    const filters = wrapper.findAll('button.status-filter');
    await filters[2].trigger('click'); // low
    expect(wrapper.findAll('.gauge-card')).toHaveLength(1);
    await filters[3].trigger('click'); // monitored / all
    expect(wrapper.findAll('.gauge-card')[0].text()).toContain('High');
  });

  it('greys out and hides gauge counts while loading', () => {
    const wrapper = mount(RiverResponse, { props: { gauges, loading: true } });
    expect(wrapper.classes()).toContain('is-waiting');
    expect(wrapper.text()).toContain('Waiting for gauges');
    expect(wrapper.text()).not.toContain('monitored gauges');
    expect(wrapper.findAll('.gauge-card')).toHaveLength(0);
    expect(wrapper.findAll('button.status-filter')).toHaveLength(0);
  });
});
