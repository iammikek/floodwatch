# Flood Watch – Troubleshooting

Common issues and fixes.

---

## LLM times out

**Symptom**: Request stalls or times out during AI summary.

**Fix**: Reduce `FLOOD_WATCH_LLM_MAX_*` limits in `.env` (e.g. `llm_max_floods`, `llm_max_incidents`, `llm_max_forecast_chars`). Large tool results can exceed context or cause slow responses.

---

## Circuit breaker stuck

**Symptom**: One API (EA, NH, etc.) returns empty even when it’s back up.

**Fix**: Set `FLOOD_WATCH_CIRCUIT_BREAKER_ENABLED=false` to bypass. Or wait for `cooldown_seconds` (default: 60) to expire. Check `FLOOD_WATCH_CIRCUIT_FAILURE_THRESHOLD` and `FLOOD_WATCH_CIRCUIT_COOLDOWN`.

---

## Cache not working

**Symptom**: Every request hits APIs; no cache reuse.

**Fix**: If using Redis, check it’s running: `sail redis-cli PING` (or `sail exec redis redis-cli PING`). Ensure `FLOOD_WATCH_CACHE_STORE` points to the correct store. With `flood-watch-array`, cache is per-request and not shared – use Redis in production.
