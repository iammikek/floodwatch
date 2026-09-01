import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import RecentRoutesPanel from './RecentRoutesPanel.vue';
import BookmarksPanel from './BookmarksPanel.vue';

describe('RecentRoutesPanel', () => {
  it('emits select when a route chip is clicked', async () => {
    const wrapper = mount(RecentRoutesPanel, {
      props: {
        routes: [{ from: 'Langport', to: 'Taunton' }],
      },
    });
    await wrapper.find('button.sidebar-chip').trigger('click');
    expect(wrapper.emitted('select')?.[0]?.[0]).toEqual({ from: 'Langport', to: 'Taunton' });
  });
});

describe('BookmarksPanel', () => {
  it('emits select with bookmark location', async () => {
    const bookmark = {
      id: 1,
      label: 'Home',
      location: 'TA10 0DP',
      lat: 51.04,
      lng: -2.83,
      is_default: true,
    };
    const wrapper = mount(BookmarksPanel, {
      props: { bookmarks: [bookmark] },
    });
    await wrapper.find('button.sidebar-chip').trigger('click');
    expect(wrapper.emitted('select')?.[0]?.[0]).toEqual(bookmark);
  });
});
