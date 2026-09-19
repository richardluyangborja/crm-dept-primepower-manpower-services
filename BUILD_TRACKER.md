# BUILD TRACKER — PrimePower CRM (kanban)

> One feature at a time, in the order below (see `specs/17-*.md`). Move rows
> left → right as work progresses. Each Done row links its merged PR.
> Legend: ✅ done · 🔄 in progress · ⬜ todo · 🚫 blocked

## Kanban

| Step | Feature (spec) | Status | Branch | PR | Notes |
|---|---|---|---|---|---|
| 0 | Base scaffold: Laravel 11 API + React shell + seeds + CI | ✅ | `feature/crm-base-scaffold` | #2 | CI green (6c56032) — ready to merge, then step 1 |
| 1 | Lead & Client Tracking (`04`) | ✅ | `feature/crm-lead-client` | to open (base `develop`) | Pushed (a6fd973), CI green — open PR to `develop`, then step 2 |
| 2 | Opportunity Pipeline (`05`) | ⬜ | — | — | Depends on step 1 (clients exist) |
| 3 | Follow-up Reminders (`08`) | ⬜ | — | — | Needs `reminders:dispatch` schedule |
| 4 | Communication History (`07`) | ⬜ | — | — | — |
| 5 | Satisfaction & Surveys (`06`) | ⬜ | — | — | Includes public `/s/{token}` page |
| 6 | Accounts & Settings (`09`) | ⬜ | — | — | — |
| 7 | AI Analytics + Reports (`15`) | ⬜ | — | — | Rules → mock-AI; real models later |
| 8 | OTP + 5-min Session (`16`) | ⬜ | — | — | LAST — touches auth globally |
| 9 | Release hardening + `crm-v1.0.0` | ⬜ | — | — | Neon guide, prod env docs |

## Per-step verification checklist (paste into each PR)

- [ ] `php artisan test` green (backend)
- [ ] `npm run build` green (frontend)
- [ ] `migrate:fresh --seed` works on Postgres 16
- [ ] Light + dark screenshots attached
- [ ] Spec acceptance criteria (`04`–`09`/`15`/`16`) checked
- [ ] Tracker row updated with PR link

## Log

| Date (UTC) | Event |
|---|---|
| 2026-09-19 | Spec suite (00–16) merged to `develop` |
| 2026-09-19 | Base scaffold PR #2 opened; CI backend failed — root cause: scaffold resolved Laravel 13 (needs PHP ^8.3) vs CI PHP 8.2 |
| 2026-09-19 | Backend rebuilt on Laravel 11.56 (per spec); plan switched to sequential builds + this tracker |
| 2026-09-19 | CI fixed after 4 failures: (1) scaffold had resolved Laravel 13 vs CI PHP 8.2 → rebuilt on L11; (2) symfony 8.1/pint in lock → composer `platform.php=8.2.0`; (3) tests needed sqlite ext + run on pgsql service; (4) `jwt:secret` silently skipped because `.env.example` contained the literal `JWT_SECRET` token — removed. CI green on 6c56032 |
| 2026-09-19 | CORS fixed: `config/cors.php` was missing → added FRONTEND_URL allowlist + `CorsTest` (preflight 204, allow/disallow). Local `artisan serve` on PHP 8.5 hides ACAO headers due to deprecation output flushing before headers — proven local-only; middleware verified in-process |
| 2026-09-19 | Step 1 built on `feature/crm-lead-client`: Lead/Client/Contact controllers+requests+resources+policies+LeadService, convert idempotency 409, duplicate warnings, `LeadClientTest` (5 tests), `Leads.tsx` page (tabs, score bars, status flow, convert dialog, client 360 drawer). Live smoke: lead→score 45→convert→client+opp→360→409 replay OK |
