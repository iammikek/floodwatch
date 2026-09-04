import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import PlaceHistoryPanel from './PlaceHistoryPanel.vue';

describe('PlaceHistoryPanel', () => {
  it('renders kind, impact summary, and formatted date', async () => {
    const wrapper = mount(PlaceHistoryPanel, {
      props: {
        source: 'lake',
        incidents: [
          {
            id: 'eval-2020-02',
            label: 'Storm Dennis (Feb 2020)',
            asOf: '2020-02-16T12:00:00Z',
            kind: 'named_storm',
            severity: 'high',
            impactSummary: 'Named-storm peak; corridor hindcast golden eval.',
          },
        ],
      },
    });
    expect(wrapper.text()).toContain('Named storm');
    expect(wrapper.text()).toContain('Storm Dennis');
    expect(wrapper.text()).toContain('Named-storm peak');
    expect(wrapper.text()).toMatch(/16 Feb 2020/);
    await wrapper.find('.history-link').trigger('click');
    expect(wrapper.emitted('select')?.[0]).toEqual(['eval-2020-02']);
  });
});
