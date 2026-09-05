import { describe, expect, it } from 'vitest';
import {
  USE_CASE_HISTORY,
  USE_CASE_LIVE,
  USE_CASE_OPTIONS,
  USE_CASE_TRANSPORT,
  presetForUseCase,
  resolveUseCase,
} from './cockpitUseCases.js';

describe('cockpitUseCases', () => {
  it('resolves live, history, and transport compositions', () => {
    const live = resolveUseCase(USE_CASE_LIVE);
    expect(live.panels.floodExposure).toBe(true);
    expect(live.panels.currentRoute).toBe(false);
    expect(live.panels.routeCheck).toBe(false);
    expect(live.panels.riverResponse).toBe(true);
    expect(live.panels.stormReplay).toBe(false);
    expect(live.panels.placeHistory).toBe(false);
    expect(live.showDispatch).toBe(true);
    expect(live.layers.gauges).toBe(true);

    const hist = resolveUseCase(USE_CASE_HISTORY);
    expect(hist.panels.floodExposure).toBe(false);
    expect(hist.panels.currentRoute).toBe(false);
    expect(hist.panels.riverResponse).toBe(false);
    expect(hist.panels.yourRisk).toBe(false);
    expect(hist.panels.stormReplay).toBe(false);
    expect(hist.panels.placeHistory).toBe(true);
    expect(hist.showDispatch).toBe(false);
    expect(hist.layers.gauges).toBe(false);
    expect(hist.layers.warnings).toBe(false);

    const transport = resolveUseCase(USE_CASE_TRANSPORT);
    expect(transport.panels.routeCheck).toBe(true);
    expect(transport.panels.currentRoute).toBe(true);
    expect(transport.panels.recentRoutes).toBe(true);
    expect(transport.panels.placeHistory).toBe(false);
    expect(transport.panels.riverResponse).toBe(false);
    expect(transport.layers.route).toBe(true);
    expect(transport.layers.incidents).toBe(true);
    expect(transport.layers.gauges).toBe(false);
    expect(transport.showDispatch).toBe(true);
  });

  it('exposes three top-bar options including Transport', () => {
    expect(USE_CASE_OPTIONS.map((uc) => uc.label)).toEqual(['Live', 'History', 'Transport']);
  });

  it('builds a LeanMap-shaped preset without Dispatch/Hydrology options', () => {
    const preset = presetForUseCase(USE_CASE_HISTORY);
    expect(preset.id).toBe(USE_CASE_HISTORY);
    expect(preset.layers.floodZones).toBe(true);
    expect(preset.layers.gauges).toBe(false);
    expect(presetForUseCase(USE_CASE_TRANSPORT).layers.route).toBe(true);
    expect(presetForUseCase('nope').id).toBe(USE_CASE_LIVE);
  });
});
