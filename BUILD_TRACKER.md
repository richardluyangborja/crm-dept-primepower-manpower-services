# BUILD TRACKER — PrimePower CRM (kanban)

> One feature at a time, in the order below (see `specs/17-*.md`). Move rows
> left → right as work progresses. Each Done row links its merged PR.
> Legend: ✅ done · 🔄 in progress · ⬜ todo · 🚫 blocked

## Kanban

| Step | Feature (spec) | Status | Branch | PR | Notes |
|---|---|---|---|---|---|
| 0 | Base scaffold: Laravel 11 API + React shell + seeds + CI | 🔄 | `feature/crm-base-scaffold` | #2 | CI fix: downgraded L13→L11 for PHP 8.2; awaiting green + merge |
| 1 | Lead & Client Tracking (`04`) | ⬜ | — | — | Next after base merges |
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
