# 17 — Parallel Build Streams (frozen base → 8 agents, zero-conflict)

Locked decisions: **1 agent per module, full base built first, CI + protected `develop`.**

## 1. What the base freezes (do NOT edit without RFC)
- `backend/database/migrations/2026_09_19_*` (schema), `backend/routes/api.php` (endpoint index),
  `backend/app/Traits/*`, `frontend/src/lib/apiClient.ts`, `frontend/src/components/ui/*` (extend, don't fork),
  `specs/` (behavioral source of truth).
- New columns = **new dated migrations**, never edits to merged ones.

## 2. Stream briefs (branch → own files only → PR to `develop`)
| Agent | Branch prefix | Spec | Backend owns | Frontend owns | Merge order |
|---|---|---|---|---|---|
| A | `feature/crm-lead-*` | 04 | Lead/Client/Contact ctrl+req+res+policy | `pages/Leads.tsx` (replaces shell) | 1 |
| B | `feature/crm-pipeline-*` | 05 | Opportunity ctrl+move/win/lose | `pages/Pipeline.tsx` + extend Kanban | 2 |
| E | `feature/crm-followup-*` | 08 | Followup/Notification + `reminders:dispatch` | `pages/Followups.tsx` + calendar | 3 |
| D | `feature/crm-comms-*` | 07 | Activity + templates | `pages/Comms.tsx` + Timeline | 4 |
| C | `feature/crm-survey-*` | 06 | SurveyTemplate/Survey + public `/s/{token}` | `pages/Surveys.tsx` + builder | 5 |
| F | `feature/crm-settings-*` | 09 | Users/Setting + sessions list | `pages/Settings.tsx/*` | 6 |
| G | `feature/crm-ai-*` | 15 | Insights services + reports/generate | `pages/Dashboard` extend + Reports | 7 |
| H | `feature/crm-auth-*` | 16 | OTP enforce + idle middleware | OtpModal + idle timer (flag on) | 8 (last) |

Each PR: rebase on `origin/develop` first, CI green, light+dark screenshot, `migrate --seed` proof.

## 3. Daily discipline (every agent, every feature)
```bash
git fetch origin && git rebase origin/develop
php artisan test            # backend/  (sqlite, fast)
npm run build               # frontend/ (typecheck + build)
git push --force-with-lease # own feature branch only — never main/develop
```

## 4. Seeded demo logins (all `PrimePower123!`, override via `SEED_PASSWORD`)
superadmin@primepower.ph · admin@primepower.ph · manager@primepower.ph ·
rep.juandelacruz@primepower.ph · rep.mariasantos@primepower.ph · otp.demo@primepower.ph (mock OTP `123456`)

## 5. Run locally
```bash
docker compose up -d db
backend:  cp .env.example .env && php artisan migrate --seed && php artisan serve   # :8000
frontend: cp .env.example .env && npm install && npm run dev                        # :5173
```
