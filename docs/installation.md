# Installation

This guide covers prerequisites, initial setup, and first-run steps for Flood Watch.

---

## Prerequisites

- **Docker** & **Docker Compose** (for [Laravel Sail](https://laravel.com/docs/12.x/sail))
- **PHP 8.4+** (if running locally without Sail)
- **Composer**
- **Node.js 18+** & **Yarn**
- **Redis** (included in Sail)

## Initial Setup

1. **Clone the repository**:
   ```bash
   git clone https://github.com/iammikek/floodwatch.git
   cd floodwatch
   ```

2. **Install dependencies**:
   ```bash
   composer install
   yarn install
   ```

3. **Configure environment**:
   ```bash
   cp .env.example .env
   ```
   Add your `OPENAI_API_KEY` to `.env`. See [Configuration](#configuration) below.

4. **Start development environment**:
   ```bash
   ./vendor/bin/sail up -d
   ```

5. **Initialize application**:
   ```bash
   ./vendor/bin/sail artisan key:generate
   ./vendor/bin/sail artisan migrate
   ./vendor/bin/sail yarn build
   ```

## Configuration

See [Architecture](architecture.md) for detailed configuration options.

- **OpenAI**: `OPENAI_API_KEY` is required.
- **National Highways**: `NATIONAL_HIGHWAYS_API_KEY` is optional but recommended.
- **Cache**: Redis is used by default in Sail.

## First Run

Once started, the application is available at [http://localhost](http://localhost).

---

## See also

- [Architecture](architecture.md)
- [Testing](tests.md)
- [Contributing](../contributing.md)
