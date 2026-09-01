import { describe, expect, it, vi, afterEach } from 'vitest';
import { mount } from '@vue/test-utils';
import RouteCheckForm from './RouteCheckForm.vue';

vi.mock('../lib/reverseGeocode.js', () => ({
  reverseGeocodeFromCoords: vi.fn(async () => ({
    valid: true,
    inArea: true,
    location: 'Langport',
  })),
}));

describe('RouteCheckForm', () => {
  afterEach(() => {
    vi.unstubAllGlobals();
  });

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

  it('sets From from GPS via reverse geocode', async () => {
    vi.stubGlobal('navigator', {
      geolocation: {
        getCurrentPosition: (success) =>
          success({ coords: { latitude: 51.04, longitude: -2.83 } }),
      },
    });

    const wrapper = mount(RouteCheckForm, {
      props: {
        from: '',
        to: 'Bridgwater',
        'onUpdate:from': (v) => wrapper.setProps({ from: v }),
        'onUpdate:to': (v) => wrapper.setProps({ to: v }),
      },
    });

    await wrapper.find('button.route-gps').trigger('click');
    await vi.waitFor(() => expect(wrapper.props('from')).toBe('Langport'));
  });

  it('emits gps-error when geolocation is unavailable', async () => {
    vi.stubGlobal('navigator', {});

    const wrapper = mount(RouteCheckForm, {
      props: {
        from: 'A',
        to: 'B',
      },
    });

    await wrapper.find('button.route-gps').trigger('click');
    expect(wrapper.emitted('gps-error')?.[0]?.[0]).toMatch(/not available/i);
  });
});
