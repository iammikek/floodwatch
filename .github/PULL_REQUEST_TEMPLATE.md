## What does this PR do?

<!-- Brief description of changes -->

## Why is this needed?

<!-- Explain the problem being solved or feature being added -->

## How was this tested?

<!-- Describe how you verified these changes (manual/automated) -->

## Documentation

- [ ] If this PR changes user-facing behaviour or APIs, I updated the relevant docs (README and/or `docs/`).
- [ ] If this PR only touches docs, I checked links and followed the [docs style guide](docs/DOCS_STYLE.md).

## Review Checklist

### Code Quality
- [ ] Tests pass (`sail test`)
- [ ] Code formatted (`sail pint`)
- [ ] No sensitive data in code

### Test Coverage
- [ ] Unit/Feature tests added/updated
- [ ] Edge cases handled (null, empty, errors)

### Security
- [ ] OpenAI API keys in `.env` only (never in code)
- [ ] Auth/Gate protection for sensitive routes

### Performance
- [ ] Caching considered for expensive operations
- [ ] N+1 queries avoided

### Database
- [ ] Migrations include indexes and constraints where applicable
