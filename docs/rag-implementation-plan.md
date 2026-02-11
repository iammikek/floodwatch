# RAG Implementation Plan

## Overview

This document outlines the plan to implement Retrieval-Augmented Generation (RAG) in Flood Watch to enhance LLM responses with relevant historical and contextual information.

## Goals

1. Improve LLM response quality with relevant historical context
2. Reduce hallucinations by grounding responses in verified data
3. Enable "similar searches" and personalized recommendations
4. Provide richer explanations of flood areas and road conditions

---

## Phase 1: Infrastructure Setup

### 1.1 Database Setup
- [ ] Install pgvector extension for PostgreSQL
- [ ] Create `knowledge_embeddings` table:
  ```sql
  CREATE TABLE knowledge_embeddings (
      id BIGSERIAL PRIMARY KEY,
      content TEXT NOT NULL,
      embedding VECTOR(1536),  -- OpenAI ada-002 dimension
      metadata JSONB,
      category VARCHAR(50),    -- 'flood_area', 'road', 'incident', 'documentation'
      region VARCHAR(50),
      created_at TIMESTAMP,
      updated_at TIMESTAMP
  );
  CREATE INDEX ON knowledge_embeddings USING ivfflat (embedding vector_cosine_ops);
  ```

### 1.2 Embedding Service
- [ ] Create `App\Services\EmbeddingService`:
  - `embed(string $text): array` - Generate embedding via OpenAI
  - `store(string $content, array $metadata, string $category): void`
  - `search(string $query, int $limit = 5, ?string $category = null): Collection`

### 1.3 Configuration
- [ ] Add to `config/flood-watch.php`:
  ```php
  'rag' => [
      'enabled' => env('FLOOD_WATCH_RAG_ENABLED', false),
      'embedding_model' => 'text-embedding-ada-002',
      'max_results' => 5,
      'similarity_threshold' => 0.7,
  ],
  ```

---

## Phase 2: Knowledge Base Population

### 2.1 Flood Area Knowledge
- [ ] Create seeder for flood area descriptions from `flood_area_road_pairs` config
- [ ] Include: area name, typical flood patterns, affected roads, historical severity
- [ ] Source: Environment Agency flood area metadata

### 2.2 Road/Route Knowledge
- [ ] Embed `key_routes` with descriptions of:
  - Typical flood points
  - Alternative routes
  - Historical closure patterns

### 2.3 Documentation Embedding
- [ ] Embed key sections from:
  - `docs/environment_agency_api.md` (severity levels, warning types)
  - `docs/risk_correlation.md` (correlation rules explanation)
  - Regional context from `config/flood-watch.regions`

### 2.4 Artisan Command
- [ ] Create `php artisan flood-watch:embed-knowledge`:
  - Processes all knowledge sources
  - Supports `--category=` filter
  - Supports `--refresh` to re-embed all

---

## Phase 3: RAG Integration

### 3.1 Query Augmentation
- [ ] Create `App\Services\RagContextService`:
  ```php
  public function getRelevantContext(
      string $query,
      ?string $region = null,
      ?array $categories = null
  ): string
  ```

### 3.2 Prompt Builder Integration
- [ ] Modify `FloodWatchPromptBuilder::buildSystemPrompt()`:
  - Accept optional RAG context
  - Inject retrieved context into system prompt
  - Add section: "Relevant historical context:"

### 3.3 FloodWatchService Integration
- [ ] In `chat()` method, before LLM call:
  ```php
  if (config('flood-watch.rag.enabled')) {
      $ragContext = $this->ragContextService->getRelevantContext(
          $userMessage,
          $region,
          ['flood_area', 'road', 'documentation']
      );
      // Pass to prompt builder
  }
  ```

---

## Phase 4: Historical Data RAG

### 4.1 Search History Embeddings
- [ ] Create job to embed `user_searches` periodically
- [ ] Store: query, location, outcome summary, timestamp

### 4.2 Incident History
- [ ] Store significant flood events with embeddings
- [ ] Include: date, severity, affected areas, duration, impact

### 4.3 "Similar Searches" Feature
- [ ] Add to dashboard: "Users also searched for..."
- [ ] Based on embedding similarity of current query to past searches

---

## Phase 5: Advanced Features

### 5.1 Dynamic Context Selection
- [ ] Use tool results to refine RAG queries mid-conversation
- [ ] After `GetFloodData` returns, retrieve context specific to those flood areas

### 5.2 Feedback Loop
- [ ] Track which RAG contexts led to helpful responses
- [ ] Use feedback to improve retrieval ranking

### 5.3 Cache RAG Results
- [ ] Cache embedding searches by query hash
- [ ] TTL: 15 minutes (match existing cache strategy)

---

## Implementation Priority

| Priority | Item | Effort | Impact |
|----------|------|--------|--------|
| 1 | Infrastructure (Phase 1) | Medium | Foundation |
| 2 | Documentation embedding (2.3) | Low | Quick win |
| 3 | Flood area knowledge (2.1) | Medium | High |
| 4 | Prompt integration (3.1-3.3) | Medium | High |
| 5 | Historical data (Phase 4) | High | Medium |
| 6 | Advanced features (Phase 5) | High | Medium |

---

## Success Metrics

- **Response quality**: User satisfaction ratings (if implemented)
- **Accuracy**: Reduction in incorrect flood area references
- **Relevance**: % of RAG context actually used in responses
- **Performance**: RAG retrieval latency < 200ms

---

## Technical Considerations

### Token Budget
- RAG context competes with tool results for token budget
- Allocate ~2000 tokens for RAG context
- Adjust `llm_max_*` configs if needed

### Embedding Costs
- OpenAI ada-002: $0.0001 per 1K tokens
- Estimate: ~$5/month for knowledge base + queries

### Fallback Behavior
- If RAG service fails, continue without augmentation
- Log failures for monitoring

---

## Related Documentation

- `docs/architecture.md` - System overview
- `docs/agents-and-llm.md` - LLM data flow
- `docs/risk_correlation.md` - Correlation rules
