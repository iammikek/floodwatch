# Testing

Flood Watch uses [Pest](https://pestphp.com/) for its test suite.

---

## Running Tests

Run all tests:
```bash
sail test
```

Run tests with coverage:
```bash
sail test --coverage
```

Filter tests:
```bash
sail test --filter=FloodWatchServiceTest
```

## Test Structure

- **Feature Tests**: Located in `tests/Feature/`. These cover service orchestration, API integrations (mocked), and Livewire components.
- **Unit Tests**: Located in `tests/Unit/`. These cover standalone logic, DTOs, and helpers.

## Mocking

We use Pest and Laravel's built-in mocking to avoid hitting external APIs (OpenAI, Environment Agency, etc.) during tests. See `contributing.md` for mocking patterns.

## Snapshot Testing

Prompt structures are guarded by snapshot tests. If you change a prompt, update snapshots with:
```bash
PEST_UPDATE_SNAPSHOTS=1 sail test tests/Feature/Services/FloodWatchPromptBuilderTest.php
```

---

## See also

- [Contributing](../contributing.md)
- [Architecture](architecture.md)
