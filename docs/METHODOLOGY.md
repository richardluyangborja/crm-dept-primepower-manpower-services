# METHODOLOGY — claim → spec → implementation → evidence

Research title: *"Design and Development of an Enterprise Service Management System
with Data Analytics and Customer Intelligence for Enhanced Decision Support and Operations"
(CRM track, PrimePower Manpower Services).*

Each row is one verifiable claim for the paper. "Evidence" names the exact files
a panelist can open; "How to verify" is runnable in under 5 minutes.

## 1. System scope (what was built, and what was deliberately mocked)

| # | Claim | Spec | Implementation | Evidence | How to verify |
|---|---|---|---|---|---|
| 1 | Five CRM subsystems + platform ship as one system | `specs/00` §3, `specs/04`–`09` | 9 pages, 19 controllers, 11 resources | `frontend/src/pages/`, `backend/app/Http/Controllers/` | Click Dashboard → Leads → Pipeline → Comms → Surveys → Follow-ups → Reports → Settings |
| 2 | All cross-dept integrations are honest mocks, including prod | `specs/11`, `specs/18` §E | `Mock*` services hard-bound in `AppServiceProvider`; `Ⓜ`/`AI preview` badges in UI | `backend/app/Services/Mocks/`, `AppServiceProvider.php:25-30` | Settings → Integrations → Test connection on each service |
| 3 | No vendor database lock-in | `specs/01`, `docs/DEPLOYMENT.md` | Plain Eloquent + migrations; deployment-managed Postgres | `backend/database/migrations/`, `docker-compose.yml` | `migrate:fresh --seed` on a clean Postgres |
| 4 | PH-localized demo data, zero Faker | `specs/12` | Static-array seeders, idempotent via `firstOrCreate` | `backend/database/seeders/Crm*.php` | `grep -ri faker backend/database/seeders` → no hits |

## 2. Decision support (the research contribution)

| # | Claim | Spec | Implementation | Evidence | How to verify |
|---|---|---|---|---|---|
| 5 | Rule-based customer intelligence ranks at-risk clients with reasons | `specs/15` §2 | `ChurnRisk::assess()` (staleness + overdue + low NPS + inactive → high/med/low + drivers) | `Services/Insights/ChurnRisk.php`, `AiReportsTest` | Dashboard → At-risk table; seeded Makati Medical Center scores high |
| 6 | Win probability adjusts for recency/activity, not just stage | `specs/15` §2 | `WinProbability::forOpp()` (×0.85 stale 30d, ×0.7 60d, ×1.1 active 14d, ×0.6 past close date) | `Services/Insights/WinProbability.php`, `AiReportsTest` | `GET /insights/opportunities/{id}` shows probability + drivers |
| 7 | EN/TL survey sentiment without ML deps | `specs/15` §2 | `SentimentAnalyzer` keyword lists (`salamat/mabilis` vs `mabagal/delay`) | `Services/Insights/SentimentAnalyzer.php` | Reports → comment wall sentiment dots |
| 8 | Next-best-action recommender drives action, not display | `specs/15` §1 | `NextBestAction::{forUser,forClient}` with deep links; every row links to its record | `Services/Insights/NextBestAction.php`, Dashboard NBA list | Click any NBA item → lands on the exact record |
| 9 | One-click weekly/monthly management packs | `specs/15` §2 | `ReportService::pack()` (narrative + 7 tables) + `reports:generate` scheduled Mon 08:00 | `Services/ReportService.php`, `routes/console.php`, `Reports.tsx` (CSV + Print) | Reports → Generate → download CSV → Print/PDF |
| 10 | Insight feedback loop trains future models | `specs/15` §2 | `insight_feedback` table + 👍/👎 on every insight card | `InsightFeedbackController.php`, `InsightBits.tsx` | Vote on any at-risk row; row lands in `insight_feedback` |

## 2b. Phase 2 depth (working system, not demo)

| # | Claim | Spec | Implementation | Evidence | How to verify |
|---|---|---|---|---|---|
| 17 | Full-page client/lead profiles replace the drawer | `specs/18` §6-2A | `/clients/:id` (8 tabs), `/leads/:id` (journey + wizard); shared `ClientWidgets.tsx` | `ClientPage.tsx`, `LeadPage.tsx` | Open any client → all tabs load; convert inline |
| 18 | Finance is a working mock ledger, not a card | `specs/18` §6-2B | `invoices` table; AR aging; partial/full pay; collect→followup; win persists invoice | `InvoiceController.php`, `InvoiceFinanceTest.php`, `Finance.tsx` | Finance section → pay partial → collect → buckets reconcile |
| 19 | Operations is visible but read-only | `specs/18` §6-2C | `Operations.tsx` board; no mutations; advancing stays on client timeline | `Operations.tsx` | Board shows all JOs; no advance buttons present |
| 20 | Trailing trends charted, not just KPI'd | `specs/18` §6-2D | `trends.monthly` in dashboard summary; shared Recharts `TrendsCard` on Dashboard + Reports | `DashboardController.php` (trends), `TrendCharts.tsx`, `AiReportsTest::test_dashboard_trends_bucket_by_month` | Dashboard → 6-month won/lost + win-rate + NPS charts |
| 21 | Master data is admin-editable, not hardcoded | `specs/18` §6-2C | `pipeline_stages`/`lost_reasons`/`industries`/`lead_sources` settings keys + editor; kanban + dialogs read them | `SettingController.php` (EDITABLE), `Settings.tsx` `MasterDataSection`, `useSettings.ts` | Rename a stage → kanban header updates |
| 22 | Every import row reports success or the exact error | `specs/18` §6-2C | `LeadImportService` (template, 500-row cap, per-row report) + modal UI | `LeadImportService.php`, `Phase2CGapsTest.php`, Leads import modal | Import mixed CSV → 1 in, row 3 error shown |

## 3. Main process (v2 journey — the demonstrable story)

| # | Claim | Spec | Implementation | Evidence | How to verify |
|---|---|---|---|---|---|
| 11 | A won deal visibly becomes a staffed, deployed, billed job order | `specs/18` §2–3A | `job_orders` table; win persists the mock payload; `advance` walks draft→staffed→deployed→billed | `JobOrderController.php`, `JobOrderJourneyTest.php` | Pipeline → Mark won → banner → View client timeline → Advance ×3 |
| 12 | Deployment headcount + AR balance surface on the client, not in logs | `specs/18` §3A | `GET clients/{id}/operations` (mock-labeled read-backs) + 360° ops cards | `ClientController@operations`, `Leads.tsx` `ClientOpsCards` | Open Davao Prime Hotel → 60 deployed, ₱0 AR |
| 13 | A new user can self-guide the 5-step journey | `specs/18` §3B | Onboarding tour (persisted `tour_seen`), deep links (`?client=`, `?client` preselect), narrative strip | `Tour.tsx`, `useSearchParams` in Leads/Pipeline, `NarrativeStrip` | Fresh login → tour auto-starts; follow it end to end |

## 4. Security & robustness (verified, not asserted)

| # | Claim | Spec | Implementation | Evidence | How to verify |
|---|---|---|---|---|---|
| 14 | OTP second factor + 5-min idle + step-up grants enforced | `specs/16` | Login challenge for gated roles, `SessionTimeout` middleware, 428 grants, named rate limiters | `AuthController.php`, `SessionTimeout.php`, `OtpSessionTest.php` (6 tests incl. 6th-attempt 429, 5:01 idle 401) | Login as admin → enter `123456`; idle 6 min → 401 `session_expired` |
| 15 | Every list/show/update response matches the specced envelope | `specs/14` | `paginated()` shapes via Resources; `ListEnvelopeTest` guards the 2026-09-20 blank-screen regression | `Traits/ApiResponse.php`, `ListEnvelopeTest.php` | `php artisan test --filter=ListEnvelopeTest` |
| 16 | One bad page can never blank the app again | `specs/18` §3C | Root `ErrorBoundary` + null-safe rendering + validated storage parse | `ErrorBoundary.tsx`, `session.ts:initialUser()` | Break a fixture row locally → fallback UI, app survives |

## 5. How to demo (15-minute panel script)
1. Fresh login → tour auto-starts (5 stops, ~2 min).
2. Leads → Davao Prime Hotel 360°: contacts, ops cards, staffing journey.
3. Pipeline → move a deal → Mark won → follow the banner to the client timeline.
4. Follow-ups → clear an overdue item; Comms → log a call with follow-up hook.
5. Surveys → send → open `/s/{token}` in an incognito window → answer → watch analytics move.
6. Reports → Generate weekly pack → CSV + Print. Dashboard → narrative + at-risk + feedback vote.
7. Settings → Integrations → Test connection each mock; Security → sessions + login history.
