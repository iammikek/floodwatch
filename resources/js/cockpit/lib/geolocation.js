/**
 * Browser geolocation helpers for cockpit route From.
 */

/** @returns {string | null} */
export function geolocationBlockedReason() {
  if (typeof navigator === 'undefined' || !navigator.geolocation) {
    return 'Geolocation is not available in this browser.';
  }
  if (typeof window !== 'undefined' && !window.isSecureContext) {
    return 'Location requires HTTPS or localhost. Enter a postcode instead.';
  }
  return null;
}

/**
 * @param {GeolocationPositionError | unknown} error
 * @returns {string}
 */
export function geolocationErrorMessage(error) {
  const code = error && typeof error === 'object' ? /** @type {{ code?: number }} */ (error).code : null;
  if (code === 1) {
    return 'Location permission denied. Allow access in your browser or enter a postcode.';
  }
  if (code === 2) {
    return 'Location unavailable. Try entering a postcode.';
  }
  if (code === 3) {
    return 'Location request timed out. Try again or enter a postcode.';
  }
  return 'Could not get location. Try entering a postcode.';
}

/**
 * @param {PositionOptions} [options]
 * @returns {Promise<GeolocationPosition>}
 */
export function getCurrentPosition(options = {}) {
  const blocked = geolocationBlockedReason();
  if (blocked) {
    return Promise.reject(new Error(blocked));
  }

  return new Promise((resolve, reject) => {
    navigator.geolocation.getCurrentPosition(
      resolve,
      (error) => reject(new Error(geolocationErrorMessage(error))),
      {
        enableHighAccuracy: true,
        timeout: 10000,
        maximumAge: 0,
        ...options,
      },
    );
  });
}
