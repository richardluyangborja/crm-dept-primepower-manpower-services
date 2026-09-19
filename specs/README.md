# CRM — Spec Index

Source of truth for PrimePower Manpower Services CRM (Laravel API + React SPA, JWT, Postgres/Neon).

| # | File | Covers |
|---|---|---|
| 00 | `00-overview.md` | vision, PH context, 10-dept map, MVP scope |
| 01 | `01-architecture-techstack.md` | monorepo, Laravel 11 + React Vite stack, JWT/CORS, DB |
| 02 | `02-roles-permissions.md` | superadmin/admin/manager/sales_rep matrix |
| 03 | `03-data-model.md` | ERD + tables + indexes |
| 04 | `04-lead-client-tracking.md` | capture, scoring, 360 profile, conversion |
| 05 | `05-opportunity-pipeline.md` | kanban, win/loss, forecasting |
| 06 | `06-satisfaction-survey.md` | NPS/CSAT templates, send, analytics |
| 07 | `07-communication-history.md` | timeline, loggers, templates |
| 08 | `08-followup-reminders.md` | reminders, snooze, escalation, calendar |
| 09 | `09-accounts-settings.md` | users, appearance (light/dark), notifications, security |
| 10 | `10-ui-ux-design-system.md` | tokens/layout/feedback from `ui-references/` |
| 11 | `11-integrations-mocks.md` | mock-service contracts for 9 depts |
| 12 | `12-seeding-strategy.md` | static PH seeds, no Faker |
| 13 | `13-git-workflow.md` | branches, PRs, conflicts |
| 14 | `14-api-conventions.md` | REST/JWT envelopes, reuse rules |
| 15 | `15-ai-analytics-customer-intelligence.md` | REQUIRED: AI dashboards, customer intelligence, management reports (rules → mock-AI → models) |
| 16 | `16-auth-otp-session-timeout.md` | REQUIRED but DEFERRED: OTP 5-min expiry + 5-min idle timeout (spec now, enforce v2) |

UI refs: `../ui-references/light-mode.png`, `../ui-references/dark-mode.webp`.
Depts: `../list-of-departments.md`.
