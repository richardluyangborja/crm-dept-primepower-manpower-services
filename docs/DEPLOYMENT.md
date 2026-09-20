# DEPLOYMENT — PrimePower CRM v1.0.0

> Local dev runs on `docker compose` Postgres. Production uses the **deployment-managed
> Postgres** (same version family). Everything — integrations, AI, OTP transport —
> stays on **mocks in prod** (v2 decision, locked): mock services are hard-bound
> in `AppServiceProvider`, so the `*_MODE` env flags are informational only.
> Same migrations, same seeders — only connection values change.

## 1. Architecture recap

| Piece | Dev | Prod (suggested, all free tiers) |
|---|---|---|
| Laravel API | `php artisan serve :8000` | PHP 8.2 + Laravel Octane/Forge/VPS, or Render/Railway free tier |
| React SPA | `npm run dev :5173` | Static host (Netlify/Vercel/Cloudflare Pages) via `npm run build` |
| Database | `docker compose up db` (Postgres 16) | Deployment-managed Postgres (same migrations/seeds) |
| Scheduler | `php artisan schedule:work` | Cron: `* * * * * php artisan schedule:run >> /dev/null 2>&1` |
| Queue | `database` driver | Same (single instance) — run `php artisan queue:work` via supervisor/systemd |

## 2. Backend production env (copy `.env.example`, then set)

```bash
APP_ENV=production
APP_DEBUG=false                  # NEVER true in prod (hides error traces + stack leaks)
APP_URL=https://api.your-domain.ph
APP_TIMEZONE=Asia/Manila

DB_CONNECTION=pgsql
DB_HOST=<db-host>                # provided by the deployment (Postgres service)
DB_PORT=5432
DB_DATABASE=crm_primepower
DB_USERNAME=<db-user>
DB_PASSWORD=<db-password>        # from the deployment's secret manager, never in git

FRONTEND_URL=https://crm.your-domain.ph   # exact SPA origin(s), comma-separated — CORS allowlist
JWT_TTL=60
JWT_REFRESH_TTL=10080
SESSION_IDLE_TIMEOUT=300         # 5-min idle (specs/16)
SESSION_ABSOLUTE_TIMEOUT=43200   # 12h max

OTP_MODE=mock                    # mock = log line; SMTP later, never paid SMS
OTP_REQUIRED_ROLES=superadmin,admin
INTEGRATIONS_MODE=mock
AI_MODE=mock
SEED_PASSWORD=                   # leave EMPTY in prod — never seed demo passwords in production
```

Then on the server:

```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate --force
php artisan jwt:secret --force
php artisan migrate --force            # NEVER migrate:fresh in prod
php artisan config:cache && php artisan route:cache
```

First deploy only — create the superadmin via tinker (never via seeders):
`User::create([...])` with a strong password, then sign in and change it.

## 3. Frontend production env

```
VITE_API_URL=https://api.your-domain.ph/api/v1
VITE_APP_NAME=PrimePower CRM
VITE_SESSION_TIMEOUT_ENABLED=true
```

Build with `npm run build`, deploy `dist/` as static files. The SPA origin **must exactly match** `FRONTEND_URL` or browsers block every call (see CORS saga in `BUILD_TRACKER.md`).

## 4. Database setup (deployment-managed Postgres)

1. Provision the Postgres service in the deployment (region closest to users — Singapore for PH).
2. Create database `crm_primepower` and copy the connection values into §2 env vars.
3. Run migrations from the backend container/host (see §2).
4. Backups are handled by the deployment platform (snapshots + retention per its policy);
   additionally take weekly logical dumps (see §6).
5. Restrict: store the password in the deployment's secret manager, never in git.

## 5. Cron (scheduler = reminders + weekly reports)

```
* * * * * cd /srv/crm/backend && php artisan schedule:run >> /dev/null 2>&1
```

Covers `reminders:dispatch` (every minute) and `reports:generate --type=weekly --notify` (Mon 08:00 Asia/Manila). Verify with `php artisan schedule:list`.

## 6. Backup & restore runbook

- **Prod:** platform snapshots cover point restores; additionally take weekly logical dumps:
  `pg_dump "$DATABASE_URL" -Fc -f crm-$(date +%F).dump`
- **Local:** `docker compose exec db pg_dump -U crm crm_primepower > backup.sql`
- **Restore:** `pg_restore -d "$DATABASE_URL" crm-YYYY-MM-DD.dump` (test restores against a staging copy first).
- **Retention:** soft-deleted rows kept 90 days (`retention_days` org setting), then hard-purge by policy.

## 7. Go-live smoke checklist

- [ ] `GET /api/v1/health` → 200 (uptime monitor target)
- [ ] SPA login as superadmin → OTP challenge → verify → dashboard renders KPIs
- [ ] Idle 5:01 → next call 401 `session_expired` → redirected to login
- [ ] `php artisan test` green on the deploy commit; CI green on `main`
- [ ] `FRONTEND_URL` matches the deployed SPA origin exactly (no CORS errors)
- [ ] Scheduler cron installed; `schedule:list` shows both jobs
- [ ] `APP_DEBUG=false`, no `.env` in git, `SEED_PASSWORD` unset
- [ ] First backup taken and restore-tested against a staging copy

## 8. Rollback

Code: redeploy the previous tag (`git checkout crm-vX && deploy`).
Data: migrations are additive by convention — `php artisan migrate:rollback --step=N` only for the failed deploy's batch, then restore from backup if rows were corrupted. Never `migrate:fresh` outside local dev.
