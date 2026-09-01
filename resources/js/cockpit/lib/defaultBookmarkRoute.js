/**
 * Resolve cockpit route From on load: stored route beats profile default bookmark.
 *
 * @param {{ storedRoute?: { from: string, to: string } | null, bookmarks: Array<{ location?: string, is_default?: boolean, lat?: number|null, lng?: number|null }>, fallbackFrom: string }} opts
 * @returns {{ from: string, bookmark: object|null }}
 */
export function resolveRouteFromOnLoad({ storedRoute, bookmarks, fallbackFrom }) {
  const stored = storedRoute?.from?.trim?.() ?? '';
  if (stored) {
    return { from: stored, bookmark: null };
  }

  const defaultBookmark = bookmarks.find(
    (row) => row.is_default && String(row.location ?? '').trim(),
  );
  if (defaultBookmark) {
    return { from: String(defaultBookmark.location).trim(), bookmark: defaultBookmark };
  }

  return { from: fallbackFrom, bookmark: null };
}
