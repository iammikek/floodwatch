import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import SourceDot from './SourceDot.vue';
import PanelHeading from './PanelHeading.vue';

describe('SourceDot', () => {
  it('marks lake vs static', () => {
    const lake = mount(SourceDot, { props: { source: 'lake' } });
    expect(lake.classes()).toContain('lake');
    expect(lake.attributes('aria-label')).toMatch(/data lake/i);

    const staticDot = mount(SourceDot, { props: { source: 'static' } });
    expect(staticDot.classes()).toContain('static');
  });
});

describe('PanelHeading', () => {
  it('puts the label and SourceDot on one row', () => {
    const wrapper = mount(PanelHeading, {
      props: { source: 'lake' },
      slots: { default: 'River response' },
    });
    expect(wrapper.find('.panel-heading').exists()).toBe(true);
    expect(wrapper.text()).toContain('River response');
    const dot = wrapper.findComponent(SourceDot);
    expect(dot.exists()).toBe(true);
    expect(dot.props('source')).toBe('lake');
  });
});
