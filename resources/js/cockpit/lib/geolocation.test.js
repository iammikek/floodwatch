import { describe, expect, it, vi, afterEach } from 'vitest';
import {
  geolocationBlockedReason,
  geolocationErrorMessage,
  getCurrentPosition,
} from './geolocation.js';

describe('geolocation', () => {
  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it('blocks when not a secure context', () => {
    Object.defineProperty(window, 'isSecureContext', { value: false, configurable: true });
    vi.stubGlobal('navigator', { geolocation: {} });
    expect(geolocationBlockedReason()).toMatch(/HTTPS or localhost/i);
  });

  it('maps permission denied', () => {
    expect(geolocationErrorMessage({ code: 1 })).toMatch(/permission denied/i);
  });

  it('maps timeout', () => {
    expect(geolocationErrorMessage({ code: 3 })).toMatch(/timed out/i);
  });

  it('resolves with coords when geolocation succeeds', async () => {
    Object.defineProperty(window, 'isSecureContext', { value: true, configurable: true });
    vi.stubGlobal('navigator', {
      geolocation: {
        getCurrentPosition: (success) =>
          success({ coords: { latitude: 51.04, longitude: -2.83 } }),
      },
    });

    const pos = await getCurrentPosition();
    expect(pos.coords.latitude).toBe(51.04);
  });

  it('rejects with mapped message on geolocation error', async () => {
    Object.defineProperty(window, 'isSecureContext', { value: true, configurable: true });
    vi.stubGlobal('navigator', {
      geolocation: {
        getCurrentPosition: (_success, reject) => reject({ code: 1 }),
      },
    });

    await expect(getCurrentPosition()).rejects.toThrow(/permission denied/i);
  });
});
