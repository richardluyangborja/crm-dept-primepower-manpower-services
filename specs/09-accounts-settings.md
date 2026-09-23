# 09 — Accounts & Settings

Mirrors reference Settings UX (`dark-mode.webp`): left submenu + content + top-right `Save Changes`.

## 1. Submenu (route-guarded)
| Key | Who | Contents |
|---|---|---|
| General | all | org name (Primepower Manpower Services), timezone (Asia/Manila), currency ₱, date format, language en-PH |
| Appearance | all (personal) | Light/Dark preview cards + check badge, `Sync with System` toggle; org default (superadmin only) |
| Organization | superadmin/admin | teams (Manila/Cebu/Davao), regions, master data: industries, sources, stages labels, lost reasons |
| Notifications | all | in-app toggles (reminder due, overdue, escalation, survey response, assignment), browser-notif opt-in, quiet hours (PHT) |
| Users & Access | admin+ | invite (email+role+team; role options constrained by actor), activate/deactivate, reset password, role change (audit; v2 step-up OTP per `16`); manager sees team read-only; Teams table Members opens a member-details modal; superadmin rows are seed-managed, hidden from lists, and reject every admin mutation (only self password change works) |
| Security | all/admin | change password, active JWT sessions (list + revoke), **OTP toggle + 5-min idle-timeout notice (v1 scaffolded, enforced v2 per `16`)**, login history (audit) |
| AI & Reports (`15`) | manager+ | insight visibility, report schedule (weekly/monthly), feedback review |
| Data & Backup | superadmin | export CSV per entity, DB backup note (deployment-managed snapshots + `pg_dump` runbook), retention (soft-delete 90d) |
| Integrations | superadmin/admin | `INTEGRATIONS_MODE=mock|live` toggle per dept, endpoint URLs, test-connection (mock returns fixture), webhook log stub |

## 2. Rules
- Personal prefs (`theme, sync_system, notifications`) stored `users.preferences jsonb`; org settings `settings(key,value)` cache 60s.
- Unsaved-changes guard + toast "Settings saved"; destructive (deactivate user, change mode to live) via `ConfirmDialog` + audit.
- Validation: email unique, role in allowlist, team required for manager/rep.

## 3. API
```
GET|PUT /settings (superadmin, keyed)  GET|PUT /me/preferences
GET|POST /users  GET|PUT|POST /users/{id}/deactivate|reset-password (admin+)
GET /users/sessions  DELETE /users/sessions/{id}
POST /auth/otp/send|verify (16; mock in v1)
GET /integrations/status  PUT /integrations/mode (superadmin)
GET /reports/weekly|monthly  POST /reports/generate (15)
```
## 4. Seeds
Teams + 5 users from `02`; preferences: reps dark+sync-on, manager light; 6 industries (BPO, Manufacturing, Hospitality, Retail, Healthcare, Logistics).
