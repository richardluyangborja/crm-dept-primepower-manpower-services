# 01 — Architecture & Tech Stack (OSS / free only)

## 1. Topology: two separately deployed apps, one monorepo
```
crm-dept-primepower-manpower-services/
  specs/            # this folder (source of truth)
  backend/          # Laravel API (stateless, JWT)
  frontend/         # React SPA (Vite)
  ai-service/       # OPTIONAL Python FastAPI (Phase 2; mocked in v1, see 15)
  docker-compose.yml
  ui-references/
```
- **Not monolithic:** Laravel serves JSON only (`/api/v1/*`); React is a static SPA calling it. No Blade, no cookies/sessions.
- **Why:** independent deploy/scale; CORS is explicit and JWT avoids cookie pain in prod.

## 2. Backend — Laravel API
- **Laravel 11 + PHP >= 8.2**, PostgreSQL (`pgsql` driver).
- Auth: `tymon/jwt-auth ^2.0` — access TTL 60 min, refresh TTL 7 days with rotation + denylist **baseline**. **Deferred requirement (`16`): 5-min idle session timeout + OTP (6-digit, 5-min expiry).** v1 ships JWT baseline + OTP/session code paths stubbed behind `OTP_MODE=mock` and `SESSION_IDLE_TIMEOUT=300`; enforcement lands post-MVP. See `14` + `16`.
- CORS: `fruitcake/laravel-cors` (or L11 built-in `HandleCors`); `FRONTEND_URL` allowlisted, `supports_credentials=false` (bearer, not cookies).
- Reuse pattern (mandatory): `FormRequest` (validate) → `Controller` (thin) → `Service/Action` (logic) → `Repository/Eloquent` → `Resource` (shape). Shared: `ApiResponse` trait, `HasAuditLog` trait, `BelongsToTeam` scope, `Filterable` trait.
- Queue: `database` driver in dev (reminders/notifications); Redis optional later. Scheduler runs `reminders:dispatch` every minute (cron in prod, `schedule:work` locally).
- Config: all secrets via `.env` (`JWT_SECRET`, `DB_*`, `INTEGRATIONS_MODE=mock`, `NEON_DATABASE_URL` for cloud).

## 3. Frontend — React SPA
Locked stack (all OSS):
- `vite ^5 + typescript ^5 + react ^18`, `react-router-dom ^6`, `tailwindcss ^3 + tailwind-merge + clsx`, `shadcn/ui` (copied components, not a paid lib), `@tanstack/react-query ^5`, `axios ^1`, `react-hook-form ^7 + zod ^3 + @hookform/resolvers`, `recharts ^2`, `date-fns ^3`, `lucide-react`, `zustand ^4` (session/theme only).
- API layer: single `apiClient` (axios) with JWT interceptors (attach, 401→refresh→retry once, queue concurrent 401s). All server state via React Query (keys `['leads']`, `['opportunities', stage]`, etc.); no ad-hoc fetch.
- Reusable components (see `10-ui-ux-design-system.md`): `AppSidebar, Topbar, KpiCard, DataTable, EmptyState, FormField, ConfirmDialog, Timeline, KanbanBoard, SurveyBuilder, ReminderCalendar, ThemeToggle, Toaster`.
- Env: `VITE_API_URL`, `VITE_APP_NAME=PrimePower CRM`.

## 3b. AI & reports (required by research title, OSS/free only — see `15`)
- **Phase 1 (v1, no new infra):** rule-based customer intelligence in Laravel (`App\Services\Insights\*`): lead score, at-risk client flags, weighted forecast, NPS/CSAT aggregates + narrative summaries via reusable `ReportService` (CSV + print-ready HTML → PDF via browser print, no paid lib).
- **Phase 2 (post-MVP):** optional `ai-service/` — Python FastAPI + `scikit-learn + pandas` (churn/win-probability, sentiment on survey comments) and/or self-hosted LLM via Ollama for insight narratives. Laravel calls it through `AiServiceInterface`; `MockAiService` returns deterministic fixtures in v1 (`AI_MODE=mock`). No OpenAI/paid keys required; everything runs on `docker compose` free tiers.
- All AI outputs labeled with confidence + "AI preview — verify" badge; every insight links to source records (no black-box numbers).

## 4. Data & environments
- **Local DB:** `docker compose up db` → Postgres 16, `crm_primepower` db, persistent volume. See root `docker-compose.yml`.
- **Cloud DB:** Neon Postgres (free tier). Same migrations; connection via `NEON_DATABASE_URL`; SSL `sslmode=require`. No code change — only env.
- Migrations are the schema source of truth; **no Faker** in factories/seeders (Faker breaks offline/prod builds). Use static PH arrays (`12-seeding-strategy.md`).

## 5. Cross-cutting
- Timezone `Asia/Manila`; money in centavos (`integer`) displayed as `₱`; phone E.164 (`+639…`).
- Audit: `audit_logs(user_id, action, entity, entity_id, meta, created_at)` on all writes.
- Notifications: DB table + in-app bell; mail/SMS via `Log`/mock driver in v1.
- File uploads (v1): local `storage/app` + DB record; S3-compatible later via env swap.
- Observability: Laravel `LOG_CHANNEL=daily`, health `GET /api/v1/health`, frontend error boundary + query retry (2x) + toast.

## 6. Definition of done (each feature)
Backend request passes FormRequest + policy; frontend shows loading → toast; empty/error states covered; seeder covers happy path; spec acceptance criteria checked.
