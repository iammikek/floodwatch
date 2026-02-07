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
