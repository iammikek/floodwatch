/**
 * Reverse geocode GPS coords via Laravel (Nominatim + in-area check).
 */

/**
 * @param {{ lat: number, lng: number, fetchImpl?: typeof fetch }} opts
 * @returns {Promise<{ valid: boolean, inArea: boolean, location: string, error?: string }>}
 */
export async function reverseGeocodeFromCoords({
  lat,
  lng,
  fetchImpl = fetch,
}) {
  const url =
    `/flood-watch/reverse-geocode?lat=${encodeURIComponent(lat)}` +
    `&lng=${encodeURIComponent(lng)}`;
  const res = await fetchImpl(url, {
    headers: { Accept: 'application/json' },
    credentials: 'same-origin',
  });
  const body = await res.json().catch(() => ({}));
  if (!res.ok) {
    return {
      valid: false,
      inArea: false,
      location: '',
      error: String(body?.error ?? body?.summary ?? `Reverse geocode failed: ${res.status}`),
    };
  }
  return {
    valid: Boolean(body?.valid),
    inArea: Boolean(body?.in_area ?? body?.inArea),
    location: String(body?.location ?? ''),
    error: body?.error ? String(body.error) : undefined,
  };
}
