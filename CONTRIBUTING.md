# Contributing to FloodWatch

Thank you for your interest in contributing to FloodWatch! This guide will help you get started with development.

---

## Table of Contents

- [Getting Started](#getting-started)
- [Development Workflow](#development-workflow)
- [Code Standards](#code-standards)
- [Testing](#testing)
- [Documentation](#documentation)
- [Pull Requests](#pull-requests)
- [AI-Assisted Development](#ai-assisted-development)

---

## Getting Started

### Prerequisites

- **Docker** & **Docker Compose** (for Laravel Sail)
- **PHP 8.4+** (if running locally without Sail)
- **Composer**
- **Node.js 18+** & **Yarn**
- **Redis** (included in Sail, or install locally)

### Initial Setup

```bash
# Clone the repository
git clone https://github.com/iammikek/floodwatch.git
cd floodwatch

# Install dependencies
composer install
yarn install

# Copy environment file
cp .env.example .env

# Start Sail (includes MySQL, Redis, Mailpit)
./vendor/bin/sail up -d

# Generate application key
./vendor/bin/sail artisan key:generate

# Run migrations
./vendor/bin/sail artisan migrate

# Build frontend assets
./vendor/bin/sail yarn build

# Optional: Create sail alias for convenience
alias sail='./vendor/bin/sail'
```

### Configuration

**Required**:
```env
OPENAI_API_KEY=sk-proj-...  # Get from https://platform.openai.com/api-keys
```

**Optional** (for full functionality):
```env
NATIONAL_HIGHWAYS_API_KEY=...  # Register at https://developer.data.nationalhighways.co.uk/
```

**Cache** (for production performance):
```env
FLOOD_WATCH_CACHE_STORE=flood-watch
FLOOD_WATCH_CACHE_TTL_MINUTES=15
REDIS_HOST=redis
```

---

## Development Workflow

### Running the Application

```bash
# Start all services (app, MySQL, Redis, Mailpit)
sail up -d

# View logs
sail artisan pail

# Watch for file changes (Vite hot reload)
sail yarn dev

# Access the app
open http://localhost
```

### Making Changes

1. **Create a feature branch**:
   ```bash
   git checkout -b feature/your-feature-name
   ```

2. **Make your changes** following [Code Standards](#code-standards)

3. **Run tests** to ensure nothing breaks:
   ```bash
   sail test
   ```

4. **Format code** with Laravel Pint:
   ```bash
   sail pint
   ```

5. **Commit your changes**:
   ```bash
   git add .
   git commit -m "feat: Add your feature description"
   ```

6. **Push to your fork** and create a pull request

### Commit Message Convention

We follow [Conventional Commits](https://www.conventionalcommits.org/):

- `feat:` New feature
- `fix:` Bug fix
- `docs:` Documentation changes
- `style:` Code style changes (formatting, no logic change)
- `refactor:` Code refactoring
- `perf:` Performance improvements
- `test:` Adding or updating tests
- `chore:` Build process, dependencies, tooling

**Examples**:
```
feat: Add early exit logic for LLM iterations
fix: Prevent null pointer in FloodWatchService
docs: Update LLM integration guide with caching strategies
perf: Cache tool definitions to avoid repeated array construction
```

---

## Code Standards

### PHP

**Style Guide**: [PSR-12](https://www.php-fig.org/psr/psr-12/) (enforced by Laravel Pint)

**Run Pint**:
```bash
sail pint
```

**Key Conventions**:
- Use **strict types**: `declare(strict_types=1);`
- Use **type hints** for all parameters and return types
- Add **PHPDoc** for complex methods
- Keep methods **focused** (single responsibility)
- Prefer **early returns** over nested conditionals

**Example**:
```php
<?php

declare(strict_types=1);

namespace App\Services;

class MyService
{
    /**
     * Do something useful.
     *
     * @param  string  $input  The input to process
     * @return array{result: string, success: bool}
     */
    public function process(string $input): array
    {
        if (empty($input)) {
            return ['result' => '', 'success' => false];
        }

        $result = $this->doWork($input);

        return ['result' => $result, 'success' => true];
    }

    private function doWork(string $input): string
    {
        // Implementation
    }
}
```

### JavaScript/TypeScript

**Style**: Prettier (configured in `package.json`)

**Run Prettier**:
```bash
sail yarn format
```

### Blade Templates

- Use **Livewire components** for interactive UI
- Keep logic **minimal** (move to Livewire component or controller)
- Use **Tailwind CSS** utility classes (no custom CSS)

---

## Testing

### Running Tests

```bash
# Run all tests
sail test

# Run specific test file
sail test --filter=FloodWatchServiceTest

# Run tests with coverage
sail test --coverage
```

### Writing Tests

We use **Pest** for testing. Tests are located in `tests/Feature/`.

**Structure**:
```php
<?php

use App\Services\FloodWatchService;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    // Setup runs before each test
    Cache::flush();
    config(['openai.api_key' => 'test-key']);
});

it('caches flood watch results', function () {
    $service = app(FloodWatchService::class);

    // First call
    $result1 = $service->chat('Check Bristol');
    
    // Second call (should hit cache)
    $result2 = $service->chat('Check Bristol');

    expect($result1)->toBe($result2);
});

it('handles OpenAI errors gracefully', function () {
    // Mock OpenAI to throw error
    OpenAI::fake([
        'chat/completions' => throw new \Exception('API Error'),
    ]);

    $service = app(FloodWatchService::class);
    $result = $service->chat('Check Bristol');

    expect($result['response'])->toContain('error');
});
```

**Guidelines**:
- **Test behavior**, not implementation
- Use **descriptive test names** (`it('does something specific')`)
- **Mock external APIs** (don't hit real OpenAI/Environment Agency)
- Test **edge cases** (null, empty, errors)
- Aim for **>80% coverage** for new code

---

## Documentation

### When to Update Documentation

- **Always** update docs when changing public APIs
- Update `README.md` when adding features visible to end-users
- Update `docs/` when changing architecture, LLM integration, or services
- Add **inline comments** for complex algorithms
- Add **PHPDoc** for all public methods

### Documentation Structure

```
docs/
├── agents-and-llm.md           # Tools, APIs, outputs, limitations (LLM)
├── API_OPTIMIZATION_GUIDE.md   # Performance optimization strategies
├── architecture.md              # System design, data flow
├── SCHEMA.md                    # Database schema, entity relationships
├── RISK_CORRELATION.md          # Flood/road correlation logic
├── DEPLOYMENT.md                # Deployment instructions
├── TROUBLESHOOTING.md           # Common issues and solutions
└── build/                       # Feature build docs
```

### Updating LLM Documentation

When modifying `FloodWatchService` or prompt builder:

1. Update `docs/agents-and-llm.md` if changing:
   - Tool definitions
   - Token management
   - Error handling
   - Caching

2. Update `docs/API_OPTIMIZATION_GUIDE.md` if improving:
   - Performance
   - Token usage
   - Caching strategies

3. Update `resources/prompts/v1/system.txt` if changing:
   - Assistant instructions
   - Output format
   - Prioritization rules

---

## Pull Requests

### Before Submitting

- [ ] All tests pass (`sail test`)
- [ ] Code is formatted (`sail pint`)
- [ ] Documentation is updated
- [ ] No sensitive data (API keys, credentials) in code
- [ ] Commit messages follow convention
- [ ] PR description explains the "why" (not just the "what")

### PR Template

```markdown
## What does this PR do?

Brief description of changes.

## Why is this needed?

Explain the problem being solved or feature being added.

## How was this tested?

- [ ] Unit tests added/updated
- [ ] Manual testing performed
- [ ] Tested in development environment

## Checklist

- [ ] Tests pass
- [ ] Code formatted
- [ ] Documentation updated
- [ ] No breaking changes (or documented)
```

### Review Process

1. **Submit PR** targeting `main` branch
2. **CI runs**: Tests, Pint checks, builds
3. **Code review**: Maintainers review code
4. **Revisions**: Address feedback
5. **Merge**: Squash and merge when approved

---

## AI-Assisted Development

FloodWatch is built with **AI-assisted development** tools. We encourage their use:

### Laravel Boost

**Installed**: `composer require laravel/boost --dev`

**Usage**:
- MCP server for Laravel docs
- AI agent access to framework documentation
- Guidelines for Livewire, Pest, Tailwind

**Configuration**: `.cursor/mcp.json`

### Cursor Skills
Located in `.cursor/skills/`:
- **livewire-development**: Component patterns, wire:model, etc.
- **pest-testing**: Testing patterns, Pest syntax
- **tailwindcss-development**: Utility-first CSS patterns

### Junie Guidelines
- Canonical agent guidelines live in `.junie/guidelines.md`.
- We mirror important `.cursor/rules` and `.cursor/skills` notes into `.junie` for agent portability.
- When updating agent rules or skills, update both:
  - `.cursor/rules/laravel-boost.mdc` ↔ `.junie/guidelines.md`
  - `.cursor/skills/*/SKILL.md` → add a brief summary/link in `.junie/guidelines.md` (or reference from docs).
- PR checklist: if you change agent rules/skills, confirm `.junie/guidelines.md` remains in sync.

### Best Practices with AI

✅ **DO**:
- Use AI to **scaffold** code (controllers, tests, migrations)
- Ask AI to **explain** complex code before modifying
- Have AI **review** your changes for bugs
- Use AI to **generate tests** based on your code

❌ **DON'T**:
- Blindly accept AI suggestions without understanding
- Skip manual testing (AI-generated code can have bugs)
- Let AI commit secrets or sensitive data
- Rely on AI for architectural decisions (ask humans)

---

## Project-Specific Guidelines

### Working with LLM Integration

**Read first**: `docs/agents-and-llm.md`

**Key principles**:
1. **Token efficiency**: Always consider token usage when adding tool features
2. **Error handling**: Wrap all LLM calls and tool execution in try-catch
3. **Caching**: Use appropriate TTLs for different data types
4. **Monitoring**: Log token usage and costs

**Example** (adding a new tool):
```php
// 1. Define tool in FloodWatchPromptBuilder::getToolDefinitions()
[
    'type' => 'function',
    'function' => [
        'name' => 'GetNewData',
        'description' => 'Brief, clear description',  // Keep concise!
        'parameters' => [
            'type' => 'object',
            'properties' => [
                'param1' => ['type' => 'string', 'description' => '...'],
            ],
        ],
    ],
]

// 2. Handle in FloodWatchService::executeTool()
return match ($name) {
    'GetNewData' => $this->newDataService->getData($args['param1']),
    // ...
};

// 3. Prepare for LLM in prepareToolResultForLlm()
if ($toolName === 'GetNewData') {
    return array_slice($result, 0, 10);  // Limit size
}

// 4. Add tests in tests/Feature/Services/FloodWatchServiceTest.php
```

### Working with External APIs

**Read first**: `docs/architecture.md` (External APIs section)

**Principles**:
1. **Circuit breakers**: Already configured, respect them
2. **Retries**: Use `retry(3, 100)` for transient failures
3. **Timeouts**: Set appropriate timeouts (default: 25s)
4. **Caching**: Cache aggressively (5-60 minutes)

### Working with Frontend (Livewire)

**Read first**: `.cursor/skills/livewire-development/SKILL.md`

**Patterns**:
- Use `wire:loading` for all async operations
- Dispatch events for cross-component communication
- Keep Livewire properties **public** and **type-hinted**
- Use Alpine.js for simple interactivity

---

## Getting Help

- **Documentation**: Start with `README.md` and `docs/`
- **GitHub Issues**: Search existing issues before creating new ones
- **GitHub Discussions**: Ask questions, share ideas
- **Code Comments**: Many files have detailed inline comments

---

## License

By contributing, you agree that your contributions will be licensed under the same license as the project (see `LICENSE` file).

---

Thank you for contributing to FloodWatch! 🌊🚗
