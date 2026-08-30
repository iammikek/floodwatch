import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import RouteCheckForm from './RouteCheckForm.vue';

describe('RouteCheckForm', () => {
  it('emits check with current From/To', async () => {
    const wrapper = mount(RouteCheckForm, {
      props: {
        from: 'Muchelney, Somerset',
        to: 'Bridgwater, Somerset',
        'onUpdate:from': (v) => wrapper.setProps({ from: v }),
        'onUpdate:to': (v) => wrapper.setProps({ to: v }),
      },
    });

    await wrapper.find('form').trigger('submit');
    expect(wrapper.emitted('check')).toHaveLength(1);
  });

  it('swaps From and To', async () => {
    const wrapper = mount(RouteCheckForm, {
      props: {
        from: 'Muchelney',
        to: 'Bridgwater',
        'onUpdate:from': (v) => wrapper.setProps({ from: v }),
        'onUpdate:to': (v) => wrapper.setProps({ to: v }),
      },
    });

    await wrapper.find('button.route-swap').trigger('click');
    expect(wrapper.props('from')).toBe('Bridgwater');
    expect(wrapper.props('to')).toBe('Muchelney');
  });

  it('disables submit when From or To is empty', async () => {
    const wrapper = mount(RouteCheckForm, {
      props: {
        from: '',
        to: 'Bridgwater',
        'onUpdate:from': (v) => wrapper.setProps({ from: v }),
        'onUpdate:to': (v) => wrapper.setProps({ to: v }),
      },
    });
    expect(wrapper.find('button.route-submit').attributes('disabled')).toBeDefined();
  });

  it('shows Checking… while checking', () => {
    const wrapper = mount(RouteCheckForm, {
      props: {
        from: 'A',
        to: 'B',
        checking: true,
      },
    });
    expect(wrapper.find('button.route-submit').text()).toContain('Checking');
    expect(wrapper.find('input[name="from"]').attributes('disabled')).toBeDefined();
  });
});
