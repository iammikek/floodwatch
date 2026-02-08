# Flood Watch Deployment (Railway)

Deployment runbook for the pilot on Railway.app.

```mermaid
flowchart TD
    subgraph Setup["Initial Setup"]
        A1[Create Railway project]
        A2[Generate APP_KEY]
        A3[Configure variables]
        A4[Generate domain]
        A5[Volume / DB]
    end

    A1 --> A2 --> A3 --> A4 --> A5
    A5 --> Deploy[Deploy]
    Deploy --> Push[Push to main]
```

## Prerequisites

- Railway account (railway.app)
- GitHub repo connected
- OpenAI API key

## Pre-deployment Checklist

- [ ] All acceptance criteria met (`docs/ACCEPTANCE_CRITERIA.md`)
- [ ] QA: `sail test --coverage` >80%; see `docs/PLAN.md` Quality Assurance
- [ ] Verify `OPENAI_API_KEY` works: `php artisan flood-watch:test-openai`
- [ ] Verify `NATIONAL_HIGHWAYS_API_KEY` is valid
- [ ] Run `sail test --coverage` and confirm >80% coverage
- [ ] Load test: 100 concurrent requests, cache TTL 15 min
- [ ] Manual test: Disable NH key, verify EA data still shows
- [ ] Test all 4 regions: Somerset (BA), Bristol (BS), Devon (EX), Cornwall (TR)
- [ ] Run `flood-watch:warm-cache` and monitor Redis memory

## Initial Setup

### 1. Create Railway Project

1. Go to [railway.app](https://railway.app) and sign in
2. New Project → Deploy from GitHub repo
3. Select `flood-watch` (or your fork), branch `main`
4. Railway detects the `Dockerfile` and builds automatically

### 2. Generate APP_KEY

```bash
php artisan key:generate --show
```

Copy the output (e.g. `base64:...`).

### 3. Configure Variables

In Railway → Your Service → Variables, add:

| Variable | Value |
|----------|-------|
| `APP_KEY` | Output from `php artisan key:generate --show` |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_URL` | `https://your-app.up.railway.app` (or custom domain) |
| `OPENAI_API_KEY` | Your OpenAI API key |
| `DB_CONNECTION` | `sqlite` |
| `DB_DATABASE` | `/app/database/database.sqlite` |
| `SESSION_DRIVER` | `file` |
| `CACHE_STORE` | `file` |
| `FLOOD_WATCH_CACHE_STORE` | `flood-watch-array` |
| `FLOOD_WATCH_CACHE_TTL_MINUTES` | `15` |
| `CONCURRENCY_DRIVER` | `process` (production). Use `sync` for testing. |
| `QUEUE_CONNECTION` | `database` (default). Queue worker runs on deploy via `scripts/start.sh`. |

Optional (for road closure data):

| Variable | Value |
|----------|-------|
| `NATIONAL_HIGHWAYS_API_KEY` | From [developer.data.nationalhighways.co.uk](https://developer.data.nationalhighways.co.uk/) |

**Deployment checklist**: If road status is required, verify `NATIONAL_HIGHWAYS_API_KEY` is set. Without it, incidents return empty; `/health` reports National Highways as "skipped".

### 4. Generate Domain

Railway → Settings → Networking → Generate Domain. Use the provided `*.up.railway.app` URL and set `APP_URL` to match (with `https://`).

### 5. Persistent Storage (SQLite)

For the pilot, SQLite runs in the container. Data is lost on redeploy. To persist:

1. Railway → Your Service → Volumes → Add Volume
2. Mount path: `/app/database`
3. Set `DB_DATABASE=/app/database/database.sqlite`

### 6. Persistent Users (Neon PostgreSQL, free tier)

To persist users without Railway volumes (free tier):

1. Create a free account at [neon.tech](https://neon.tech)
2. Create a project and copy the connection string (e.g. `postgresql://user:pass@host/dbname?sslmode=require`)
3. In Railway Variables, add:
   - `DB_CONNECTION` = `pgsql`
   - `DATABASE_URL` = your Neon connection string (Laravel uses this when `DB_URL` is unset)
   - `SESSION_DRIVER` = `database` (sessions persist in PostgreSQL)
4. Leave `DB_HOST` unset (the URL contains the host; `127.0.0.1` would fail in containers)

Users can then register at `/register` and log in at `/login`; accounts persist across deploys.

## Redeploy

Push to `main`:

```bash
git push origin main
```

Railway automatically builds and deploys. No manual steps.

## Queue Worker

The deploy starts a queue worker (`php artisan queue:work`) in the background via `scripts/start.sh`. This processes:

- **LLM request recording** – Each Flood Watch search records token usage to `llm_requests` for the admin dashboard.

If `QUEUE_CONNECTION=database` (default), jobs are stored in the `jobs` table. Ensure migrations have run so the table exists.

## Rollback

1. Railway → Deployments
2. Find the previous successful deployment
3. Click ⋮ → Redeploy

## Troubleshooting

### Build fails

- Check build logs in Railway
- Ensure `yarn.lock` and `composer.lock` are committed
- Verify Dockerfile paths match project structure

### 500 error at runtime

- Check deployment logs in Railway
- Ensure `APP_KEY` is set
- Ensure `OPENAI_API_KEY` is set (app shows a message if missing)
- Run `php artisan config:clear` locally to verify config

### Vite manifest missing / CSS not loading

- Frontend build runs in Dockerfile; if assets are missing, check the frontend build stage logs
- Ensure `public/build` is created by `yarn build`
- If assets 404, set `ASSET_URL` to match `APP_URL` (e.g. `https://your-app.up.railway.app`)

### Database errors

- For SQLite: ensure `database/` is writable (Dockerfile creates it)
- For volume: ensure mount path matches `DB_DATABASE`

### Redis cache cannot be found

The app uses Redis for the `flood-watch` cache store (search results, health checks) and for trends. If Redis is not provisioned, you'll see connection errors.

**Option A – Use database cache (no Redis)**

Avoid Redis by using the database cache store:

| Variable | Value |
|----------|-------|
| `FLOOD_WATCH_CACHE_STORE` | `database` |
| `CACHE_STORE` | `database` |
| `FLOOD_WATCH_TRENDS_ENABLED` | `false` |

Ensure migrations have run (`php artisan migrate`) so the `cache` table exists.

**Option B – Add Redis on Railway**

1. Railway → Your Project → **New** → **Database** → **Redis**
2. After Redis is created, select your **app service** → **Variables** → **Add variable reference**
3. Choose the Redis service and add `REDIS_URL` (Railway usually provides this)
4. Set (or leave as default):

| Variable | Value |
|----------|-------|
| `FLOOD_WATCH_CACHE_STORE` | `flood-watch` |
| `CACHE_STORE` | `redis` |

Railway injects `REDIS_URL` when the Redis service is linked. Laravel uses it for the Redis connection.

## Custom Domain

1. Railway → Settings → Domains → Add Custom Domain
2. Enter e.g. `floodwatch.yourdomain.com`
3. In your DNS (e.g. Krystal), add CNAME: `floodwatch` → `your-app.up.railway.app`
4. Update `APP_URL` to the custom domain

## Cost (Pilot)

- **Railway free tier:** $5 credits for 30 days, then $1/month
- **OpenAI:** Pay-per-use (gpt-4o-mini ~$0.01–0.10 per request). Monitor via admin dashboard when implemented. Many unique postcodes = more cache misses = higher cost.
- **Real-time (planned):** Laravel Reverb adds minimal compute; push notifications (FCM) are free. See `docs/PLAN.md`.

See `docs/CONSIDERATIONS.md` for API dependency, regional scope, and cost risks.
