# API Optimization Guide

**Purpose**: Performance optimization strategies for FloodWatch's LLM and external API integrations.

**Last Updated**: 2026-02-11

---

## Table of Contents

- [Quick Wins](#quick-wins)
- [Performance Metrics](#performance-metrics)
- [Optimization Strategies](#optimization-strategies)
- [Caching Deep Dive](#caching-deep-dive)
- [Token Optimization](#token-optimization)
- [Rate Limiting](#rate-limiting)
- [Monitoring](#monitoring)
- [Scaling Considerations](#scaling-considerations)

---

## Quick Wins

Start here for immediate performance improvements:

### 1. Enable Aggressive Caching (5 minutes)

```env
# .env
FLOOD_WATCH_CACHE_TTL_MINUTES=15
# Dedicated store (config/cache.php: flood-watch) avoids key collisions with other app cache usage
FLOOD_WATCH_CACHE_STORE=flood-watch
REDIS_HOST=redis
```

**Note**: Use `flood-watch` (the app’s dedicated Redis-backed store). Use `redis` only if you prefer the default Redis store. The dedicated store is documented in `config/flood-watch.php` and `config/cache.php`.

**Impact**: Reduces OpenAI API calls by ~80% for repeated postcodes

---

### 2. Reduce Token Limits (2 minutes)

```env
# .env - Conservative limits for cost control
FLOOD_WATCH_LLM_MAX_FLOODS=8              # Down from 12
FLOOD_WATCH_LLM_MAX_INCIDENTS=8           # Down from 12
FLOOD_WATCH_LLM_MAX_RIVER_LEVELS=5        # Down from 8
FLOOD_WATCH_LLM_MAX_FORECAST_CHARS=800    # Down from 1200
FLOOD_WATCH_LLM_MAX_FLOOD_MESSAGE_CHARS=100  # Down from 150
```

**Impact**: ~30% reduction in token usage, ~30% cost savings

---

### 3. Enable Cache Pre-warming (10 minutes)

Add to `routes/console.php`:

```php
Schedule::command('flood-watch:warm-cache')
    ->everyFifteenMinutes();
```

**Impact**: Common locations (Bristol, Somerset, Devon) served from cache instantly

---

### 4. Use Process-based Concurrency (1 minute)

```env
# .env - For production only
CONCURRENCY_DRIVER=process
```

**Impact**: Pre-fetch time drops from ~15s to ~5s (parallel execution)

**Note**: Keep `sync` for local/testing environments

---

### 5. Enable Circuit Breakers (Already enabled by default)

```env
# .env - Default values (already optimal)
FLOOD_WATCH_CIRCUIT_BREAKER_ENABLED=true
FLOOD_WATCH_CIRCUIT_FAILURE_THRESHOLD=5
FLOOD_WATCH_CIRCUIT_COOLDOWN=60
```

**Impact**: Prevents cascading failures when external APIs are down

---

## Performance Metrics

### Baseline Performance (No Optimizations)

| Metric | Value | Breakdown |
|--------|-------|-----------|
| **Total Response Time** | ~25-30s | Cold start, no cache |
| Pre-fetch | ~15s | Sequential: forecast (5s) + weather (5s) + river (5s) |
| LLM iterations | ~10-15s | Average 3-4 tool calls |
| **Token Usage** | ~8,000 tokens | Input: ~6,000, Output: ~2,000 |
| **Cost per Request** | ~$0.002 | Based on gpt-4o-mini pricing |

### Optimized Performance (All Strategies Applied)

| Metric | Value | Improvement |
|--------|-------|-------------|
| **Total Response Time** | ~0.1s | **250x faster** (cache hit) |
| **Total Response Time** | ~8-12s | **~60% faster** (cache miss) |
| Pre-fetch | ~5s | Parallel execution |
| LLM iterations | ~3-7s | Reduced token limits |
| **Token Usage** | ~5,000 tokens | **~38% reduction** |
| **Cost per Request** | ~$0.0013 | **~35% savings** |

---

## Optimization Strategies

### Strategy 1: Smart Caching

**Goal**: Minimize duplicate OpenAI API calls

#### Cache Key Strategy

**Current** (message-based):
```php
$key = "flood-watch:chat:" . md5($userMessage);
```

**Problem**: "Check Bristol" vs "Check status for Bristol" = different keys

**Better** (postcode/location-based):
```php
$key = "flood-watch:chat:" . md5($postcode ?? $userMessage);
```

**Implementation**:
```php
// In Livewire component or controller:
$service->chat(
    userMessage: $userMessage,
    cacheKey: $postcode ?? $placeName,  // Normalize cache key
    // ...
);
```

**Impact**: +20% cache hit rate

---

#### TTL Tuning

| Data Type | Recommended TTL | Reasoning |
|-----------|-----------------|-----------|
| **Flood warnings** | 15 minutes | Environment Agency updates every 15 min |
| **Road incidents** | 15 minutes | National Highways updates frequently |
| **Forecast** | 60 minutes | FGS updates 2x daily |
| **Weather** | 60 minutes | Open-Meteo updates hourly |
| **River levels** | 5 minutes | Real-time monitoring |

**Current**: Single 15-minute TTL for all (chat result cache)

**Optimization**: Use per-service caching with different TTLs

```php
// In FloodWatchService::chat()
$forecast = Cache::remember('flood-forecast', 3600, function () {
    return $this->forecastService->getForecast();
});
```

---

### Strategy 2: Token Optimization

**Goal**: Reduce token usage without losing quality

#### Technique 1: Polygon Stripping (Already Implemented)

**What**: Remove GeoJSON polygons from flood warnings sent to LLM

**Implementation**: `FloodWarning::withoutPolygon()`

**Savings**: ~2,000-5,000 tokens per flood warning with large polygon

---

#### Technique 2: Message Compression

**What**: Truncate verbose Environment Agency messages

**Current**:
```php
'llm_max_flood_message_chars' => 150
```

**Aggressive**:
```php
'llm_max_flood_message_chars' => 80  // Just enough for context
```

**Example**:
```
Before (300 chars): "The Environment Agency has issued a flood warning for River Parrett at North Moor and Moorland. Water levels are rising and flooding of properties is expected. Flooding is expected to affect low-lying land and roads around North Moor and Moorland. Further rainfall is forecast over the next 24 hours..."

After (80 chars): "Flood warning for River Parrett at North Moor. Properties at risk. Levels rising."
```

**Savings**: ~500-1,000 tokens per request

---

#### Technique 3: Correlation Summary Limits

**Current**:
```php
'llm_max_correlation_chars' => 8000
```

**Optimized**:
```php
'llm_max_correlation_chars' => 5000
'llm_max_cross_references' => 10  // Add new config
'llm_max_predictive_warnings' => 5  // Add new config
```

**Implementation**:
```php
// In prepareToolResultForLlm():
$result['cross_references'] = array_slice($result['cross_references'], 0, 10);
$result['predictive_warnings'] = array_slice($result['predictive_warnings'], 0, 5);
```

**Savings**: ~1,000-2,000 tokens per request

---

#### Technique 4: Early Exit Logic

**Current**: LLM loops until it decides to stop (max 8 iterations)

**Optimization**: Exit early when no new data

```php
// In FloodWatchService::chat() loop
$emptyToolCalls = 0;

foreach ($message->toolCalls as $toolCall) {
    $result = $this->executeTool(...);
    
    if (empty($result) || (isset($result['getError']))) {
        $emptyToolCalls++;
    }
}

// Exit early if 2+ consecutive tool calls return empty
if ($emptyToolCalls >= 2) {
    break;
}
```

**Savings**: Prevents unnecessary iterations, ~20-30% faster

---

### Strategy 3: Parallel Execution

**Goal**: Minimize sequential wait time

#### Current Parallel Pre-fetch

```php
[$forecast, $weather, $riverLevels] = Concurrency::run([
    fn () => app(FloodForecastService::class)->getForecast(),
    fn () => app(WeatherService::class)->getForecast($lat, $lng),
    fn () => app(RiverLevelService::class)->getLevels($lat, $lng),
]);
```

**Timing**:
- Sequential: 5s + 5s + 5s = **15 seconds**
- Parallel: max(5s, 5s, 5s) = **~5 seconds**

#### Additional Optimization: Parallel Tool Execution

**Idea**: When LLM requests multiple tools simultaneously, execute in parallel

**Implementation** (future enhancement):
```php
// Group tool calls that don't depend on each other
$floodData = null;
$incidents = null;

if (hasToolCall('GetFloodData') && hasToolCall('GetHighwaysIncidents')) {
    [$floodData, $incidents] = Concurrency::run([
        fn () => $this->executeTool('GetFloodData', ...),
        fn () => $this->executeTool('GetHighwaysIncidents', ...),
    ]);
}
```

**Savings**: ~3-5 seconds per iteration with multiple tools

---

### Strategy 4: Request Deduplication

**Goal**: Prevent duplicate requests hitting the API

#### Problem

User searches "Bristol" → API call
User searches "Bristol" again 10 seconds later → Another API call (cache not yet populated)

#### Solution: In-flight Request Tracking

```php
// In FloodWatchService
private static array $inFlightRequests = [];

public function chat(string $userMessage, ...): array
{
    $key = $this->cacheKey($userMessage, $cacheKey);
    
    // Wait for in-flight request to complete
    if (isset(self::$inFlightRequests[$key])) {
        usleep(100000);  // 100ms
        return Cache::get($key) ?? $emptyResult('Processing...');
    }
    
    self::$inFlightRequests[$key] = true;
    
    try {
        $result = /* ... existing logic ... */;
        return $result;
    } finally {
        unset(self::$inFlightRequests[$key]);
    }
}
```

**Savings**: Prevents concurrent duplicate requests

---

## Caching Deep Dive

### Multi-layer Cache Architecture

```
┌─────────────────────────────────────────┐
│  User Request                           │
└─────────────────────────────────────────┘
              ↓
┌─────────────────────────────────────────┐
│  Layer 1: Result Cache (flood-watch)    │
│  Store: flood-watch (Redis-backed)      │
│  TTL: 15 minutes                        │
│  Key: flood-watch:chat:{hash}          │
└─────────────────────────────────────────┘
              ↓ (miss)
┌─────────────────────────────────────────┐
│  Layer 2: In-Memory Cache               │
│  - System prompt (request scope)        │
│  - Tool definitions (request scope)     │
└─────────────────────────────────────────┘
              ↓
┌─────────────────────────────────────────┐
│  Layer 3: External API Cache            │
│  - Forecast: 60 min                     │
│  - Weather: 60 min                      │
│  - Floods: 5 min                        │
│  - Roads: 5 min                         │
└─────────────────────────────────────────┘
```

### Cache Warming Strategy

**Locations to Warm**:
1. **High-traffic postcodes**: BS1, BS2, BA1, TA1, EX1, PL1, TR1
2. **Risk areas**: Somerset Levels (TA7, TA10), North Devon (EX31-EX39)
3. **Major towns**: Bristol, Bath, Taunton, Exeter, Plymouth, Truro

**Schedule**:
```php
// Every 15 minutes (before cache expires)
Schedule::command('flood-watch:warm-cache')->everyFifteenMinutes();
```

**Implementation**:
```php
// config/flood-watch.php
'warm_cache_locations' => [
    'bristol' => ['postcode' => 'BS1 1AA', 'lat' => 51.4545, 'lng' => -2.5879],
    'somerset' => ['postcode' => 'TA10 0AA', 'lat' => 51.0358, 'lng' => -2.8318],
    // ...
],
```

---

## Token Optimization

### Token Budget Breakdown

**Target**: Keep total payload < 20,000 tokens per request

| Component | Current | Optimized | Savings |
|-----------|---------|-----------|---------|
| System prompt | ~300 | ~300 | - |
| User message | ~50 | ~50 | - |
| Tool definitions | ~800 | ~800 | - |
| **Tool results** | **~6,000** | **~3,000** | **50%** |
| Assistant response | ~2,000 | ~1,500 | 25% |
| **TOTAL** | **~9,150** | **~5,650** | **38%** |

### Token Estimation Accuracy

**Current** (crude):
```php
$estimatedTokens = (int) ceil(strlen(json_encode($payload)) / 4);
```

**Problem**: Inaccurate for non-ASCII, code, special chars

**Better** (use tiktoken):
```bash
composer require yethee/tiktoken
```

```php
use Yethee\Tiktoken\Encoder;

$encoder = Encoder::get('cl100k_base');  // gpt-4, gpt-4o, gpt-4o-mini
$tokenCount = count($encoder->encode($text));
```

**Benefits**:
- Accurate token counting
- Better budget management
- Precise cost estimation

---

## Rate Limiting

### OpenAI Rate Limits

| Tier | RPM | TPM | Max Tokens/Request |
|------|-----|-----|--------------------|
| Free | 3 | 40,000 | 4,096 |
| Tier 1 | 500 | 200,000 | 4,096 |
| Tier 2 | 5,000 | 450,000 | 16,384 |

**FloodWatch Tier**: Tier 1 (recommended)

### Application-level Rate Limiting

**Middleware**: `ThrottleFloodWatch`

```php
// app/Http/Middleware/ThrottleFloodWatch.php
RateLimiter::for('flood-watch', function (Request $request) {
    return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
});
```

**Benefits**:
- Prevents abuse
- Protects OpenAI budget
- Fair usage across users

---

## Monitoring

### Key Metrics to Track

| Metric | Target | Alert Threshold |
|--------|--------|-----------------|
| **Response time** | < 10s | > 20s |
| **Cache hit rate** | > 70% | < 50% |
| **Token usage/request** | < 6,000 | > 10,000 |
| **Cost/day** | < $1 | > $5 |
| **OpenAI errors** | < 1% | > 5% |
| **Circuit breaker trips** | < 5/hour | > 20/hour |

### Monitoring Tools

**1. Laravel Pulse** (Included)
- Requests/second
- Slow requests
- Failed jobs

**2. Custom Metrics** (LlmRequest model)
```php
// Query token usage
LlmRequest::query()
    ->whereBetween('created_at', [now()->subDay(), now()])
    ->sum('input_tokens');
```

**3. OpenAI Dashboard**
- https://platform.openai.com/usage
- Daily usage
- Cost breakdown

---

## Scaling Considerations

### Current Limits

| Resource | Current | Bottleneck |
|----------|---------|------------|
| **Concurrent users** | ~10-20 | OpenAI rate limits (500 RPM) |
| **Requests/day** | ~10,000 | Cost (~$20/day @ $0.002/request) |
| **Cache size** | ~100 MB | Redis memory |

### Scaling Strategy

#### Phase 1: Optimize (0-100 users/day)
- ✅ Enable caching
- ✅ Reduce token limits
- ✅ Cache pre-warming

#### Phase 2: Scale Vertically (100-1,000 users/day)
- Upgrade OpenAI tier (Tier 2: 5,000 RPM)
- Increase Redis memory (512 MB → 2 GB)
- Use faster model (gpt-4o-mini → gpt-4o for premium users)

#### Phase 3: Scale Horizontally (1,000+ users/day)
- Multiple application servers (load balanced)
- Redis cluster (separate cache servers)
- Queue-based LLM processing (async)
- CDN for static assets

#### Phase 4: Cost Optimization (High Volume)
- **Self-hosted LLM** (Llama 3, Mistral) for non-critical requests
- **Hybrid approach**: OpenAI for complex queries, self-hosted for simple ones
- **Streaming responses**: Improve perceived performance
- **Regional caching**: Edge locations (CloudFlare Workers)

---

## Advanced Optimizations

### 1. Streaming Responses

**Current**: Wait for full LLM response before returning

**Streaming**: Send partial response as it's generated

```php
OpenAI::chat()->createStreamed($payload, function ($chunk) {
    $this->dispatch('llm-chunk', chunk: $chunk);
});
```

**Benefits**: Perceived performance improvement (user sees progress)

### 2. Batch Requests

**Idea**: Combine multiple user requests into one OpenAI call

**Use Case**: Admin dashboard generating reports for multiple locations

```php
$locations = ['Bristol', 'Bath', 'Exeter'];
$results = $this->batchChat($locations);
```

**Savings**: Reduce overhead, lower cost per request

### 3. Prompt Compression

**Idea**: Use shorter, more efficient prompts

**Example**:
```
Before: "You are the South West Emergency Assistant for Bristol, Somerset, Devon and Cornwall..."
After: "SW flood/road assistant. Prioritize: 1) Danger to Life, 2) road closures, 3) alerts."
```

**Savings**: ~200-300 tokens

---

## Summary: ROI by Optimization

| Optimization | Effort | Savings (Time) | Savings (Cost) | Priority |
|--------------|--------|----------------|----------------|----------|
| **Enable caching** | 5 min | 90% (cache hit) | 80% | 🔴 HIGH |
| **Reduce token limits** | 2 min | 0% | 30% | 🔴 HIGH |
| **Cache pre-warming** | 10 min | +20% hit rate | 15% | 🟡 MEDIUM |
| **Parallel concurrency** | 1 min | 60% | 0% | 🔴 HIGH |
| **Early exit logic** | 30 min | 20% | 20% | 🟡 MEDIUM |
| **Polygon stripping** | ✅ Done | - | - | - |
| **Message compression** | 15 min | 0% | 10% | 🟢 LOW |
| **Streaming responses** | 2 hrs | 0% (perceived) | 0% | 🟢 LOW |
| **Tiktoken integration** | 1 hr | 0% | 5% (accuracy) | 🟢 LOW |

**Quick Win Combo** (30 minutes total):
1. Enable caching (5 min)
2. Reduce token limits (2 min)
3. Cache pre-warming (10 min)
4. Parallel concurrency (1 min)

**Expected Results**:
- ⚡ 70-80% faster responses (cache hits)
- 💰 40-50% cost reduction
- 📊 Better user experience

---

## Next Steps

1. **Implement quick wins** (this week)
2. **Monitor metrics** for 1 week (establish baseline)
3. **Analyze bottlenecks** (which optimizations had most impact?)
4. **Iterate** (implement Phase 2 optimizations if needed)

---

**Questions or Suggestions?**
- Update this guide as you discover new optimization techniques
- Share performance wins with the team
- Track cost savings vs. OpenAI usage dashboard
