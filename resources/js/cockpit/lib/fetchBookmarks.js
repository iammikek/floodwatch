/**
 * Load signed-in user location bookmarks for cockpit sidebar.
 */

/**
 * @param {{ fetchImpl?: typeof fetch }} [opts]
 * @returns {Promise<Array<{ id: number, label: string, location: string, is_default: boolean }>>}
 */
export async function fetchBookmarks({ fetchImpl = fetch } = {}) {
  const res = await fetchImpl('/flood-watch/bookmarks', {
    headers: { Accept: 'application/json' },
    credentials: 'same-origin',
  });
  if (!res.ok) {
    throw new Error(`Bookmarks request failed: ${res.status}`);
  }
  const body = await res.json();
  const items = Array.isArray(body?.items) ? body.items : [];
  return {
    authenticated: Boolean(body?.authenticated),
    items: items
    .map((row) => ({
      id: Number(row.id),
      label: String(row.label ?? ''),
      location: String(row.location ?? ''),
      lat: row.lat != null ? Number(row.lat) : null,
      lng: row.lng != null ? Number(row.lng) : null,
      is_default: Boolean(row.is_default ?? row.isDefault),
    }))
    .filter((row) => row.id && row.location),
  };
}
