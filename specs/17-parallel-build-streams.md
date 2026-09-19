# 17 — Sequential Build Plan (one feature at a time)

> Supersedes the earlier parallel multi-agent plan. We build **step by step, one
> feature branch at a time**, merged to `develop` before the next starts. Why:
> avoids API rate limits, keeps context small per session, and makes every PR
> reviewable. Progress is tracked in [`BUILD_TRACKER.md`](../BUILD_TRACKER.md) (kanban).

## 1. Frozen base (do NOT edit without RFC)
- `backend/database/migrations/2026_09_19_*` (schema), `backend/routes/api.php` (endpoint index),
  `backend/app/Traits/*`, `frontend/src/lib/apiClient.ts`, `frontend/src/components/ui/*` (extend, don't fork),
  `specs/` (behavioral source of truth).
- New columns = **new dated migrations**, never edits to merged ones.
- Locked stack: **Laravel 11 + PHP ^8.2** (CI runs 8.2 — keep `composer.lock` compatible; never scaffold on a newer major), React Vite+TS stack per `01`.

## 2. Build sequence (strict order — each step is one branch → one PR → merge)
| Step | Branch | Spec | Delivers (backend + frontend) |
|---|---|---|---|
| 0 | `feature/crm-base-scaffold` | 00–03, 10–14 | ✅ done — scaffold, auth baseline, seeds, CI (this PR) |
| 1 | `feature/crm-lead-client` | 04 | Lead/Client/Contact CRUD + scoring + convert wizard + 360 profile |
| 2 | `feature/crm-pipeline` | 05 | Opportunity CRUD + kanban + move/win/lose + forecast |
| 3 | `feature/crm-followup` | 08 | Followups CRUD + snooze/done + escalation + `reminders:dispatch` + calendar |
| 4 | `feature/crm-comms` | 07 | Activity loggers + timeline + templates + search |
| 5 | `feature/crm-survey` | 06 | Templates builder + send/collect + public `/s/{token}` + analytics |
| 6 | `feature/crm-settings` | 09 | Users & access + org settings + appearance + sessions list |
| 7 | `feature/crm-ai-reports` | 15 | Insights wiring + weekly/monthly packs + feedback loop |
| 8 | `feature/crm-otp-session` | 16 | OTP enforcement + 5-min idle timeout (LAST — touches auth globally) |
| 9 | `release/crm-v1` | — | Hardening: audit pass, prod env docs, Neon guide, tag `crm-v1.0.0` |

## 3. Per-step loop (same every time)
```bash
git checkout develop && git pull origin develop
git checkout -b feature/crm-<name>
# …build backend (controller+request+resource+policy+test) then frontend (page+query+feedback)…
php artisan test            # backend/ — must stay green
npm run build               # frontend/ — typecheck + build
docker compose up -d db && php artisan migrate:fresh --seed  # prove seeds
git add -A && git commit -m "feat(<scope>): <what>" && git push -u origin feature/crm-<name>
# open PR to develop, update BUILD_TRACKER.md, merge, delete branch
```

## 4. Step acceptance (all required before merge)
- [ ] Endpoints match `14` envelopes; FormRequest validation with PH rules; Policy scoping (own/team/admin).
- [ ] Page shows loading → success/error toast; empty + error + retry states; light + dark screenshots in PR.
- [ ] Seeder covers the happy path (no Faker); `insight` links where spec'd.
- [ ] `BUILD_TRACKER.md` row moved to Done with PR link.

## 5. Seeded demo logins (all `PrimePower123!`, override via `SEED_PASSWORD`)
superadmin@primepower.ph · admin@primepower.ph · manager@primepower.ph ·
rep.juandelacruz@primepower.ph · rep.mariasantos@primepower.ph · otp.demo@primepower.ph (mock OTP `123456`)

## 6. Run locally
```bash
docker compose up -d db
backend:  cp .env.example .env && php artisan migrate --seed && php artisan serve   # :8000
frontend: cp .env.example .env && npm install && npm run dev                        # :5173
```
