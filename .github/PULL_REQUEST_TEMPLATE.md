## Description

<!-- Describe your changes -->

## Review Checklist

### Test Coverage
- [ ] DashboardController authorization (admin-only access)
- [ ] OpenAiUsageService API integration
- [ ] LlmRequest logging accuracy

### Security
- [ ] `/admin` route has auth middleware and `accessAdmin` gate
- [ ] OpenAI API keys in `.env` only (never in code)

### Error Handling
- [ ] OpenAiUsageService handles API failures gracefully
- [ ] Fallbacks when OpenAI API is unavailable

### Performance
- [ ] OpenAiUsageService caching (5 min) prevents excessive API calls
- [ ] LlmRequest table has indexes for `created_at` and `(user_id, created_at)`

### Database
- [ ] LlmRequest migration has indexes and constraints
