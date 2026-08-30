import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import Sparkline from '../components/Sparkline.vue';

describe('Sparkline', () => {
  it('renders a path for points', () => {
    const wrapper = mount(Sparkline, {
      props: {
        points: [
          { t: '2026-01-01T00:00:00Z', v: 1 },
          { t: '2026-01-01T01:00:00Z', v: 2 },
          { t: '2026-01-01T02:00:00Z', v: 1.5 },
        ],
      },
    });
    const path = wrapper.find('path');
    expect(path.exists()).toBe(true);
    expect(path.attributes('d')).toMatch(/^M/);
  });

  it('renders guide line when guide provided', () => {
    const wrapper = mount(Sparkline, {
      props: {
        points: [
          { t: 'a', v: 1 },
          { t: 'b', v: 2 },
        ],
        guide: 1.5,
      },
    });
    expect(wrapper.find('line').exists()).toBe(true);
  });

  it('handles empty points', () => {
    const wrapper = mount(Sparkline, { props: { points: [] } });
    expect(wrapper.find('path').exists()).toBe(false);
  });
});
