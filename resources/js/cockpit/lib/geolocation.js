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
    return 'Location unavailable. On Mac, enable Location Services for your browser in System Settings, or enter a postcode.';
  }
  if (code === 3) {
    return 'Location request timed out. Try again or enter a postcode.';
  }
  return 'Could not get location. Try entering a postcode.';
}

/**
 * @param {PositionOptions} options
 * @returns {Promise<GeolocationPosition>}
 */
function requestPosition(options) {
  return new Promise((resolve, reject) => {
    navigator.geolocation.getCurrentPosition(resolve, reject, options);
  });
}

/**
 * High-accuracy first; falls back to network/cached fix (common on desktop).
 *
 * @param {PositionOptions} [options]
 * @returns {Promise<GeolocationPosition>}
 */
export async function getCurrentPosition(options = {}) {
  const blocked = geolocationBlockedReason();
  if (blocked) {
    throw new Error(blocked);
  }

  const accurate = {
    enableHighAccuracy: true,
    timeout: 10000,
    maximumAge: 0,
    ...options,
  };

  try {
    return await requestPosition(accurate);
  } catch (firstError) {
    const code =
      firstError && typeof firstError === 'object'
        ? /** @type {{ code?: number }} */ (firstError).code
        : null;

    // Permission denied — no point retrying.
    if (code === 1) {
      throw new Error(geolocationErrorMessage(firstError));
    }

    // Unavailable or slow GPS — retry with Wi‑Fi / cached position (desktop-friendly).
    if (code === 2 || code === 3) {
      try {
        return await requestPosition({
          enableHighAccuracy: false,
          timeout: 15000,
          maximumAge: 300000,
          ...options,
        });
      } catch (secondError) {
        throw new Error(geolocationErrorMessage(secondError));
      }
    }

    throw new Error(geolocationErrorMessage(firstError));
  }
}
