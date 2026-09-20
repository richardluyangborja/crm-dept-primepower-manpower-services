# BUILD TRACKER — PrimePower CRM (kanban)

> One feature at a time, in the order below (see `specs/17-*.md`). Move rows
> left → right as work progresses. Each Done row links its merged PR.
> Legend: ✅ done · 🔄 in progress · ⬜ todo · 🚫 blocked

## v2 journey B (same branch `feature/crm-journey-a`)

| Item | Status | Notes |
|---|---|---|
| Onboarding tour (5 stops, persisted) | ✅ | `Tour.tsx` + Topbar replay; `tour_seen` in preferences (backend validated) |
| Empty-state CTAs + deep links | ✅ | Clients→leads CTA; `?client=` preselects Pipeline form + auto-opens; journey “New deal” link |
| Dashboard narrative strip | ✅ | Plain-language paragraph from live KPIs, above the cards |

## v2 journey A+B — MERGED to `main` (no tag yet; v1.1.0 vs v2.0.0 decision pending)

| Item | Status | Notes |
|---|---|---|
| Hotfix PR #13 merged → journey rebased clean | ✅ | Zero conflicts |
| Journey B merged (tour, deep links, narrative) | ✅ | Tour auto-starts, `?client=` preselect, narrative strip |
| Robustness (envelope show/update tests, storage guard) | ✅ | Suite 278+ assertions green |
| `docs/METHODOLOGY.md` (16 claims mapped) | ✅ | Includes 15-min panel demo script |
| Branches cleaned (local + remote) | ✅ | Only `main` + `develop` remain |

## v2 journey A (branch `feature/crm-journey-a`, stacked on the v1.0.1 fix)

| Item | Status | Notes |
|---|---|---|
| `job_orders` table + timeline API + advance flow | ✅ | Tests green (27 assertions), live smoke OK (persist on win, step advance, terminal 422) |
| Win narration + `?client=` deep link + 360 ops/journey UI | ✅ | Build green; Davao Prime seeded walking draft→deployed |
| Ops read-backs (deployment + AR, mock-labeled) | ✅ | Live-verified on client 2 |

## v1.0.1 hotfix + v2 planning (branch `fix/v1.0.1-envelope-shapes`, spec `specs/18-v2-roadmap.md`)

| Item | Status | Notes |
|---|---|---|
| Comms blank-screen root-caused + fixed | ✅ | Raw-model envelopes → `attachments: null` → throw on `.length`; trait now shapes via Resources; ErrorBoundary added |
| Reminders 500 root-caused + fixed | ✅ | `Followup::client()` relation was missing |
| `ListEnvelopeTest` regression cover | ✅ | 4 tests; full suite 278 assertions green; build green; live-verified |
| Neon removed everywhere | ✅ | Deployment-managed Postgres; mocks locked on incl. prod; specs/01,09,12,13,17 + DEPLOYMENT.md + Settings copy |
| v2 roadmap (`specs/18`) | 🔄 | Gap analysis + workstreams A–E + 4 decisions needed from you (order, version, demo client, branching) |

## Kanban

| Step | Feature (spec) | Status | Branch | PR | Notes |
|---|---|---|---|---|---|
| 0 | Base scaffold: Laravel 11 API + React shell + seeds + CI | ✅ | `feature/crm-base-scaffold` | #2 | Merged — full suite + build green |
| 1 | Lead & Client Tracking (`04`) | ✅ | `feature/crm-lead-client` | #3 | Merged (CRUD, scoring, convert 409, 360 drawer) |
| 2 | Opportunity Pipeline (`05`) | ✅ | `feature/crm-pipeline` | #4 | Merged (kanban, guarded moves, win/loss mock docs, reopen rules) |
| 3 | Follow-up Reminders (`08`) | ✅ | `feature/crm-followup` | #5 | Merged (lifecycle, escalation, `reminders:dispatch`, queue+calendar+badge) |
| 4 | Communication History (`07`) | ✅ | `feature/crm-comms` | #6 | Merged (timeline, loggers, templates, follow-up hook) |
| 5 | Satisfaction & Surveys (`06`) | ✅ | `feature/crm-surveys` | #7 | Merged (inbox, builder+preview, analytics, public 3-step respond) |
| 6 | Accounts & Settings (`09`) | ✅ | `feature/crm-settings` | #9 | Merged (users/teams/prefs/sessions/integrations/CSV, 9-section Settings page). Stale #8 (→main) closed unmerged — code already in via #9 |
| 7 | AI Analytics + Reports (`15`) | ✅ | `feature/crm-ai-reports` | #10 | Merged to `develop` |
| 8 | OTP + 5-min Session (`16`) | ✅ | `feature/crm-otp-session` | to open (base `develop`) | Pushed (b8d03b6), CI green — 6 new tests, OTP gate + idle/absolute + step-up + modal + idle UX; open PR to `develop`, then step 9 |
| 8 | OTP + 5-min Session (`16`) | ⬜ | — | — | LAST — touches auth globally |
| 9 | Release hardening + `crm-v1.0.0` | ✅ | `feature/crm-release` | #12 | Merged — **released to `main` + tagged `crm-v1.0.0`**. Reports schedule, spec-volume seeds, DEPLOYMENT.md, trustProxies |

## Phase 2 — submodule expansion (target `crm-v1.2.0`)

| Phase | Scope | Status | Branch | Notes |
|---|---|---|---|---|
| 2A | Client/Lead detail pages (replace drawer) | ✅ | `feature/crm-client-pages` | to open (base `develop`) | Pushed (f33234b), CI green — 8-tab client page, lead journey page, drawer removed; open PR to `develop`, then 2B |
| 2B | Finance section (mock + light workflows) | ✅ | `feature/crm-finance` | to open (base `develop`) | Pushed (aa06020), CI green — invoices+aging+pay/collect, Finance section, client invoice list; open PR to `develop`, then 2C |
| 2C | Core-1 Operations (see-only) + submodule gaps | ✅ | `feature/crm-ops-gaps` | to open (base `develop`) | Pushed (0956b27), CI green — ops board, CSV import, configurable stages, survey→followup auto, week/day+drag, duration+filters, master-data UI, effective dates; open PR to `develop`, then 2D |
| 2D | BI expansion + `crm-v1.2.0` release | 🔄 | `feature/crm-bi-trends` | — | trends.monthly endpoint, shared Recharts TrendsCard on Dashboard+Reports, METHODOLOGY §2b; tests+build green, live smoke OK; pushing for CI |

Locked: pages replace drawer · light finance workflows · Core-1 see-only · OTP mock untouched.

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
| 2026-09-19 | CORS follow-up (browser still blocked): two real causes — (1) `FRONTEND_URL` only listed `localhost:5173`, so opening the app via `127.0.0.1:5173` failed origin match → allowlisted both hosts; (2) PHP 8.5 deprecation output during bootstrap flushed headers early under `artisan serve`, stripping ACAO live → `bootstrap/app.php` now mutes `E_DEPRECATED` on PHP ≥ 8.5 only (CI/prod on 8.2 untouched). Verified live: 204 + correct ACAO echo from both origins, clean JSON bodies |
| 2026-09-19 | Step 1 built on `feature/crm-lead-client`: Lead/Client/Contact controllers+requests+resources+policies+LeadService, convert idempotency 409, duplicate warnings, `LeadClientTest` (5 tests), `Leads.tsx` page (tabs, score bars, status flow, convert dialog, client 360 drawer). Live smoke: lead→score 45→convert→client+opp→360→409 replay OK |
| 2026-09-19 | Flow takeover: PRs #1–#3 merged to `main`; reconciled accidental PR #3 merge with `develop` flow (tracker conflict resolved, newer entry kept) — `main` ⇄ `develop` converged at a16ead0. New rule: features → PR to `develop`, releases `develop` → `main` |
| 2026-09-20 | Steps 2–6 landed on `develop` via #4 (→main, reconciled), #5, #6, #9, #7. Stale #8 (settings→main) closed unmerged — code already in via #9. Release: `develop` → `main` with steps 3–6 (first proper release under the new flow) |
