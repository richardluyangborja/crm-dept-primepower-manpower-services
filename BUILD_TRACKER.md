# BUILD TRACKER — Primepower CRM (kanban)

> One feature at a time, in the order below (see `specs/17-*.md`). Move rows
> left → right as work progresses. Each Done row links its merged PR.
> Legend: ✅ done · 🔄 in progress · ⬜ todo · 🚫 blocked

## Gov Phase 3 — team simplification (branch `feature/gov-teams`, on develop)

| Item | Status | Notes |
|---|---|---|
| Single-team migration + seeder | ✅ | Admins teamless, sales converge |
| Invite/role forms lock + hide team | ✅ | Backend defaults + nulls |
| Teams test + suite green | ✅ | 106 tests, 633 assertions |

## Gov Phase 2 — deactivation handover (branch `feature/gov-deactivate`, on develop)

| Item | Status | Notes |
|---|---|---|
| OwnershipService + owned preview | ✅ | Shared move logic |
| Deactivate requires successor | ✅ | 422 with counts + suggestion |
| DeactivateDialog with picker | ✅ | Counts chips, suggested default |
| Handover test + suite green | ✅ | 105 tests, 629 assertions |

## Gov Phase 1 — transfer + owner display (branch `feature/gov-transfer`, on develop)

| Item | Status | Notes |
|---|---|---|
| POST companies/{id}/transfer + policy | ✅ | Open records move, audit trail |
| Owner name on lead/client headers | ✅ | Plain text, show endpoints |
| Client 360 transfer dialog | ✅ | Successor picker + reason |
| Directory team-visible for reps | ✅ | Picker fuel, HR detail unchanged |
| GovTransferTest + suite green | ✅ | 104 tests, 620 assertions |

## Misc batch — MERGED to `main`

| Item | Status | Notes |
|---|---|---|
| Workforce ranking guards (perf + dashboard) | ✅ | Shapeless rows skipped, typed |
| Notification panel + persist + clear all | ✅ | Read-all + per-item, live verified |
| Theme 3-state + persist across logout | ✅ | System mode follows OS |
| Specs 10/18 + tracker | ✅ | Suite 101 green |

## Workforce hotfix — MERGED to `main`

| Item | Status | Notes |
|---|---|---|
| Ranking crash guard (page + dashboard) | ✅ | Missing perf rows skipped |
| OTP demo out of directory | ✅ | Seeded demo email excluded |
| Admin leave 404 pinned by test | ✅ | Measured-roles rule holds |
| Attendance pager (15) | ✅ | Client-side over month rows |

## Phase H3 — Workforce hub — MERGED to `main` (HR integration released)

| Item | Status | Notes |
|---|---|---|
| Workforce hub + 4 pages + CSV/print | ✅ | Directory/Attendance/Leave/Performance |
| Dashboard team-pulse charts | ✅ | Bars for managers, cards for reps |
| Specs 00/11/15/18 + DEMO Act 6 | ✅ | Release to main |

## Phase H2 — HR endpoints (branch `feature/phase-h2-endpoints`, on develop)

| Item | Status | Notes |
|---|---|---|
| HrController (attendance/leave/performance/directory) | ✅ | GET-only, role-scoped |
| HrEndpointTest (4 tests) + suite green | ✅ | 101 tests, 604 assertions |
| Verified + merged to develop | ✅ | Main release after H3 |

## Phase H1 — HR contracts + mocks (branch `feature/phase-h1-contracts`, on develop)

| Item | Status | Notes |
|---|---|---|
| Logo transparent (login/sidebar/survey) | ✅ | Chips removed |
| Attendance + Performance contracts | ✅ | GET-only, read-only |
| hr.json config + deterministic mocks | ✅ | Blended composite |
| HrMockTest (2 tests) + suite green | ✅ | 97 tests, 572 assertions |

## Phase D2 — auth UI + RELEASE to main

| Item | Status | Notes |
|---|---|---|
| Modern login + logo (login/sidebar/survey) | ✅ | Split layout, bundled asset |
| OTP polish, zero emoji | ✅ | Timer states, src scan clean |
| Full regression + release | ✅ | Suite 95 green, CI both |

## Phase C2 — pagination (branch `feature/phase-c2-pager`, on develop)

| Item | Status | Notes |
|---|---|---|
| DataTable pager (15/page, total) | ✅ | Optional prop, all tables |
| Leads/clients/followups/comms paged | ✅ | Queue keeps full scope; live 0-overlap |
| Verified + merged to develop | ✅ | Main release after Phase D2 |

## Phase B2 — per-head money + lead rituals (branch `feature/phase-b2-money`, on develop)

| Item | Status | Notes |
|---|---|---|
| Value derives everywhere (no value inputs) | ✅ | OppPrompt, New deal, qualified, proposal |
| Lead contacted comm-log prompt | ✅ | Company-scoped touch + follow-up |
| Won/move refresh leads + clients | ✅ | Converted renders immediately |
| Verified + merged to develop | ✅ | Suite 95 tests green |

## Phase A2 — shell fixes (branch `feature/phase-a2-shell`, on develop)

| Item | Status | Notes |
|---|---|---|
| Sidebar independent scroll | ✅ | Sticky + own overflow |
| Touch label → communications | ✅ | StageUpModal |
| Deal title inline edit | ✅ | Audited PUT |
| Parent-active rule verified | ✅ | Children only, in place |
| Verified + merged to develop | ✅ | Main release after all phases |

## Phase 5 — specs + release — MERGED to `main` (company rebuild released)

| Item | Status | Notes |
|---|---|---|
| Specs 03/04/12/14 overhaul | ✅ | Company root documented |
| docs/DEMO.md company flow | ✅ | 5-act script |
| Full rehearsal + release to main | 🔄 | Then CI green |

## Phase 4 — seeds + rebrand + batch (branch `feature/phase-4-seeds-brand`, on develop)

| Item | Status | Notes |
|---|---|---|
| Primepower rebrand (UI/seeds/docs/tests) | ✅ | Password Primepower123! |
| 20 companies + 2025-2026 dates | ✅ | 35 cos, 0 null FKs |
| Nullable touchpoint clients | ✅ | Pre-client rituals post cleanly |
| Dup settings / superadmin lock / team modal | ✅ | Guards tested |
| Specs 09 + tracker | ✅ | Suite green, merged |

## Phase 3 — won-creates-client (branch `feature/phase-3-won-client`, on develop)

| Item | Status | Notes |
|---|---|---|
| ensureClient helper (sign + won) | ✅ | Contact carryover, auto-active |
| Convert endpoint retired | ✅ | Route+method+request removed |
| Lead→qualify→deal→sign→win test | ✅ | Lost neutrality covered |
| Specs 04 + tracker | ✅ | Suite 94 tests green |

## Phase 2 — lead flow rewrite (branch `feature/phase-2-leads`, on develop)

| Item | Status | Notes |
|---|---|---|
| Opp client nullable + lead contact_position | ✅ | dbal, company FKs adopted |
| Lead capture: company/contact/requirement | ✅ | Lookup prompt, 409 guard |
| Manual convert removed; qualify→opp prompt | ✅ | Convert endpoint deprecated |
| Import resolves companies | ✅ | Open-lead rows reported |
| Specs 04 + tracker | ✅ | Suite 94 tests green |

## Phase 1 — company model (branch `feature/phase-1-companies`, on develop)

| Item | Status | Notes |
|---|---|---|
| companies table + FKs + backfill service | ✅ | 15 companies from seeds, all rows linked |
| Company CRUD/lookup/policy/resource | ✅ | Opaque IDs, owner scoping |
| company_id on 5 resources | ✅ | Old endpoints keep working |
| CompanyTest (3 tests) + suite green | ✅ | 93 tests, 538 assertions |

## Phase F — RBAC + fold + release — MERGED to `main` (all phases released)

| Item | Status | Notes |
|---|---|---|
| Superadmin hidden + creation constraints | ✅ | Backend 403-tested, forms filtered |
| Sidebar active on children only | ✅ | Parent never styled alongside |
| Finance/Operations folded into hub | ✅ | Redirects keep old URLs working |
| docs/DEMO.md + specs 02/05/10/18 | ✅ | 10-min demo script |

## Phase E — follow-ups polish (branch `feature/phase-e-followups`, on develop)

| Item | Status | Notes |
|---|---|---|
| Icon action hierarchy (Done/Snooze/Escalate) | ✅ | Shared FupActions, queue + overdue |
| Month nav (past/future) + Today | ✅ | Week/day already had nav |
| Day details as section below | ✅ | Full-width month grid |
| Specs 08 + tracker | ✅ | Verified + merged to develop |

## Phase D — dashboard charts (branch `feature/phase-d-dashboard`, on develop)

| Item | Status | Notes |
|---|---|---|
| Stage donut + clickable slices | ✅ | Recharts Pie, opens board |
| All KPI cards clickable | ✅ | Main + lifecycle strips |
| Forecast/at-risk links, trimmed copy | ✅ | Live by_stage verified |
| Verified + merged to develop | ✅ | Main release after all phases |

## Phase C — language + satisfaction (branch `feature/phase-c-satisfaction`, on develop)

| Item | Status | Notes |
|---|---|---|
| System-spec copy purged (UI only) | ✅ | Comments + specs keep refs |
| Full words + how-scores explainer | ✅ | NPS→Net Promoter Score etc |
| Surveys page sectionized + anchors | ✅ | Inbox/Templates/Performance |
| Template builder stepper | ✅ | Details→Questions→Preview |
| Specs 06 + tracker | ✅ | Verified + merged to develop |

## Phase B — ritual polish (branch `feature/phase-b-rituals`, on develop)

| Item | Status | Notes |
|---|---|---|
| Fresh-detail prefill + MoneyStrip in shell | ✅ | No more stale-row editors |
| Approval auto probability (slider gone) | ✅ | Stage default rules |
| Suggestion chips above discussion notes | ✅ | 5 one-tap phrases |
| Verified + merged to develop | ✅ | Main release after all phases |

## Phase A — crash + data fixes (branch `feature/phase-a-crash-data`, on develop)

| Item | Status | Notes |
|---|---|---|
| Sign-out confirm modal + spec rule | ✅ | Info tone, in Stream-3 family |
| Blank-page fix (Router outside Toaster) | ✅ | Action Links crashed outside Router |
| days_in_stage integer | ✅ | Carbon3 float cast |
| Hash sweep (staffing/risks/analytics) | ✅ | OpaqueIdTest expanded, 43 asserts |
| Verified + merged to develop | ✅ | Main release after all phases |

## Stage rituals + realistic money — MERGED to `main` (fast release, no tag)

| Item | Status | Notes |
|---|---|---|
| P0 shell + money strip + toast actions | ✅ | Drag/detail paths routed |
| Backend gates (value/terms/contract/JO/AR) | ✅ | 89 tests green |
| P1–P4 stage rituals | ✅ | Touch/followup/terms/probability |
| P5–P7 contract/won/lost/backward | ✅ | Reroute, survey link, value-loss |
| Specs 05/10/18 + rehearsal + release | ✅ | Lifecycle all non-zero, merged |

## Confirm destructive actions — Stream 3 of 3 — MERGED to `main` (fast release, no tag)

| Item | Status | Notes |
|---|---|---|
| ConfirmDialog tones + input + details | ✅ | Backward compatible |
| 4 natives replaced (send/unqualify×2/snooze) | ✅ | Zero window dialogs |
| Collect + revoke confirmed | ✅ | Pay modal kept, convert/deactivate kept |
| Specs 10/18 + this tracker | ✅ | All 3 streams done |

## Toast + info hierarchy — Stream 2 of 3 — MERGED to `main` (fast release, no tag)

| Item | Status | Notes |
|---|---|---|
| Toaster icons/title/body/action (compatible) | ✅ | Em-dash auto-split, convert links client |
| InfoCallout on scope headers | ✅ | Finance/Operations/Billing/Staffing |
| SectionHead on all 360 sections | ✅ | Title + tag + hint |
| Specs 10/18 + this tracker | ✅ | Stream 3 queued separately |

## Opaque IDs — Stream 1 of 3 — MERGED to `main` (fast release, no tag)

| Item | Status | Notes |
|---|---|---|
| Hashids boundary (trait + 11 models + 11 resources) | ✅ | Per-model salt, tamper → 404 |
| Filters/requests/links decode + encode | ✅ | Invalid filter hash → empty, never 500 |
| Frontend opaque strings end-to-end | ✅ | No Number(), string compares |
| Tests updated + OpaqueIdTest | ✅ | No bare ints, 403 preserved |
| Specs 03/14/18 + this tracker | ✅ | Streams 2–3 queued separately |

## Front-office reframe — MERGED to `main` (fast release, no tag)

| Item | Status | Notes |
|---|---|---|
| Client Management / Finance naming + source tags | ✅ | No more Core-1/Dept-5/mock badges in UI |
| Client 360: header KPIs + Contracts/Operations/Insights | ✅ | Fulfillment %, renewal/expansion/gap/drop rules |
| Advance removed (UI) + guarded 403 (API) | ✅ | `FrontOfficeTest` + journey test updated |
| Dashboard lifecycle strip | ✅ | Contracts, MRR, deployed, AR, renewals |
| Specs 00/04/10/11/18 + this tracker, breakdown.txt removed | ✅ | Front-office positioning recorded |

## Filter-first hub pages — MERGED to `main` (fast release, no tag)

| Item | Status | Notes |
|---|---|---|
| Leads: KPIs + one table + needs-response chip | ✅ | Queue inline, no sections |
| Clients: KPIs + directory/people/won-over views | ✅ | No backend changes |
| Finance/Operations scope subtitles sharpened | ✅ | Kept; "every" vs "one" client |
| Specs 04/10/18 + this tracker | ✅ | Filter-first pattern recorded |

## Lead & Client hub iteration — MERGED to `main` (fast release, no tag)

| Item | Status | Notes |
|---|---|---|
| Sidebar parent Lead & Client Tracking → Leads + Clients | ✅ | Collapsible, Pipeline-hub pattern |
| Leads page sectionized (queue + all inquiries) | ✅ | Score-sorted queue, capture/import as actions |
| Clients page `/clients` (directory + People + won-over) | ✅ | New route; old tab toggle deleted |
| Backend `GET /contacts` + scoping/search test | ✅ | `LeadClientHubTest`, client_name included |
| Core-1 link audit (no new surface) | ✅ | Clients↔staffing, 360 cards→boards, tour copy |
| Specs 04/10/18 + this tracker | ✅ | Hub model + overlap contract |

## v2 journey B (same branch `feature/crm-journey-a`)

| Item | Status | Notes |
|---|---|---|
| Onboarding tour (5 stops, persisted) | ✅ | `Tour.tsx` + Topbar replay; `tour_seen` in preferences (backend validated) |
| Empty-state CTAs + deep links | ✅ | Clients→leads CTA; `?client=` preselects Pipeline form + auto-opens; journey “New deal” link |
| Dashboard narrative strip | ✅ | Plain-language paragraph from live KPIs, above the cards |

## Pipeline hub iteration — MERGED to `main` (fast release, no tag)

| Item | Status | Notes |
|---|---|---|
| Collapsible hub, card links, 4 children, staffing/BI endpoints | ✅ | Merged via develop; Core-2 gap + BI drilldown closed |
| Branches cleaned | ✅ | `feature/crm-pipeline-hub` local + remote deleted |

## Hub polish iteration — MERGED to `main` (fast release, no tag)

| Item | Status | Notes |
|---|---|---|
| Friendly hub titles (Visualization Board / Billing / Deployed Staff / Contracts) | ✅ | No module/system jargon |
| BI child removed (page + route deleted; API kept) | ✅ | Non-priority |
| Client 360 tabs → 4 grouped sections + anchor jump | ✅ | Profile / Deals & Orders / Conversations / Billing |

## Pipeline hub iteration (branch `feature/crm-pipeline-hub`)

| Item | Status | Notes |
|---|---|---|
| Collapsible Pipeline hub (auto-expand, persisted) | ✅ | Chevron toggle, `aria-expanded`, localStorage, light/dark + mobile parity |
| Kanban card deep links per client | ✅ | Finance/Staffing/Contracts via `?client=`, click-safe (stopPropagation) |
| Children: Finance, Staffing, Contracts, BI drilldown | ✅ | Per-client lists, shared `ClientPicker`; BI manager+ with 403 message |
| Backend: `/staffing`, `/bi/client-breakdown` | ✅ | Scoped, mock-labeled, tested |
| Specs 05/10/11/18 + this tracker | ✅ | Hub model + contracts documented |

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

## Pipeline↔finance refinement (branch `feature/crm-pipeline-finance` → `develop` → fast `main` release for testing)

| Item | Status | Notes |
|---|---|---|
| Contract stage + signing terms flow | ✅ | Keys frozen, labels via master data; terms-gated signing; mock contracts row |
| Per-head financing on deals + cards + columns | ✅ | Server-computed monthly/total; win issues first monthly invoice |
| Flexible sources + capture headcount/positions | ✅ | Facebook default; +10 score for stated need |
| No-emoji pass + specs/10 constraint | ✅ | lucide-only, zero emoji-range chars, KpiCard deltas fixed |
| Suite + build + smoke green, CI green | ✅ | Pushed (c2d4b85) — open PR to `develop`, then fast release to `main` |

## Phase 2 — submodule expansion (target `crm-v1.2.0`)

| Phase | Scope | Status | Branch | Notes |
|---|---|---|---|---|
| 2A | Client/Lead detail pages (replace drawer) | ✅ | `feature/crm-client-pages` | to open (base `develop`) | Pushed (f33234b), CI green — 8-tab client page, lead journey page, drawer removed; open PR to `develop`, then 2B |
| 2B | Finance section (mock + light workflows) | ✅ | `feature/crm-finance` | to open (base `develop`) | Pushed (aa06020), CI green — invoices+aging+pay/collect, Finance section, client invoice list; open PR to `develop`, then 2C |
| 2C | Core-1 Operations (see-only) + submodule gaps | ✅ | `feature/crm-ops-gaps` | to open (base `develop`) | Pushed (0956b27), CI green — ops board, CSV import, configurable stages, survey→followup auto, week/day+drag, duration+filters, master-data UI, effective dates; open PR to `develop`, then 2D |
| 2D | BI expansion + `crm-v1.2.0` release | ✅ | `feature/crm-bi-trends` | merged to `develop` directly (push-run CI flake, PR check green) | trends.monthly, shared Recharts card, METHODOLOGY §2b; releasing + tagging below |

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
