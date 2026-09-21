# 18 — v2 Roadmap (post-v1.0.0 audit → MVP)

Status: **PLAN — nothing here is built yet.** v1.0.0 shipped all specced modules;
v1.0.1 (this branch) fixes two blocking defects found in live testing. v2 turns the
working system into an understandable, demonstrable MVP.

## 1. What v1.0.1 fixed (live-reproduced 2026-09-20, `fix/v1.0.1-envelope-shapes`)

| # | Symptom (user report) | Root cause (proven) | Fix |
|---|---|---|---|
| 1 | Comms page blanks the whole app; persists across history nav | `ApiResponse::paginated()` serialized **raw models**, so `attachments` arrived as `null` (DB nullable) while `Comms.tsx` renders `a.attachments.length` → throw → root unmount. React Query cache kept the bad row, so every revisit re-crashed instantly. | Trait now shapes rows through `XxxResource::collection()` (flat `meta` envelope preserved); all 9 list endpoints wrapped; new `ReportResource` (slim list shape) |
| 2 | Reminders never load | `FollowupController@index` eager-loads `client`, but `Followup` model had **no `client()` relation** → every call 500 (`RelationNotFoundException`) | Added `Followup::client()` |
| 3 | Latent: any future render throw blanks app again | No error boundary anywhere | New `ErrorBoundary` around `<Routes>` with reset + dashboard recovery |

Regression cover: new `ListEnvelopeTest` (activities `attachments: []` + `client_name`;
followups 200 + `client_name` + `is_overdue`; opportunities `weighted_centavos`;
leads/clients shaped). Full suite 278 assertions green; `npm run build` green;
verified live against seeded Postgres (`attachments: []`, 5 followups, flat meta).

## 2. Gap analysis: do the specs lack, or did the build diverge?

**Both, in different places — but the vision from our early talks is intact.**

- **Implementation diverged from specs/14** (fixed in §1): resources existed but the
  envelope trait bypassed them. Process fix: `ListEnvelopeTest`-style contract tests
  for every list endpoint, and the per-PR checklist (tracker) now means it.
- **The specs lack the *main process*, not modules.** All 10 program subsystems for
  this dept exist (lead/client, comms, surveys, follow-ups, pipeline + platform, AI,
  OTP). What no spec describes is the **end-to-end manpower story a user can follow**:
  lead → client → job order → deployment → billing → collection. That story spans
  depts 1, 2, 5; the CRM's only bridge is `pushWonOpportunity()` on win, whose result
  lands invisibly in `audit_logs.meta` + a notification. So winning a deal *appears*
  to do nothing — the system reads as disconnected CRUD. This is a **spec gap**
  (specs/05 + specs/11 describe the handoff mechanically, never experientially),
  not a missing module.

### The main process (CRM-visible slice, mocks stay on everywhere incl. prod)
1. Capture lead → qualify/score → convert to client (+contacts).
2. Open opportunity → advance kanban → **win → visible mock Job Order** (number,
   headcount, status timeline on the client 360).
3. Deployment/billing read-backs **surfaced** (mock headcount, mock AR balance) —
   not buried in audit meta.
4. Serve → survey → NPS/CSAT → at-risk flags → follow-ups close the loop.
5. Manager sees the whole journey on one dashboard narrative strip.

## 3. v2 scope (proposed workstreams)

### A. Visible cross-dept process story (the MVP core)
- **Job Order timeline on client 360**: on win, persist the mock JO payload to a
  first-class `job_orders` table (mock-sourced, like everything else) with status
  transitions (`draft → staffed → deployed → billed`) advancable by button (rep) /
  auto-advanced by seeder variety; timeline UI per client.
- **Deployment & billing read-back cards**: mock headcount deployed + AR balance on
  the client header (data already exists in fixtures; surface it).
- **Win flow narration**: win modal shows "Job Order JO-2026-XXXX created → staffing
  starts" with a link to the client timeline (replaces the dead-end toast).
- Acceptance: a new user can narrate the 5-step journey above from the UI alone,
  with zero dead ends; seeded demo client walks all five stages out of the box.

### B. Process-guided UX (make the system self-explanatory)
- First-run **onboarding tour** (dismissible, persisted in preferences): 5 stops
  mirroring §2's journey.
- **Empty states with next-step CTAs** on every list (e.g. empty pipeline →
  "Create your first deal" deep-links with client preselected).
- **Dashboard narrative strip**: one plain-language paragraph ("2 at-risk clients
  need calls; ₱X weighted closes this month") above the KPI cards.
- **Deep-link everything**: notifications, NBA actions, and report rows link to the
  exact record (no dead `link` fields).
- Acceptance: the paper's evaluator completes the main process unassisted.

### C. Robustness (finish what 1.0.1 started)
- Envelope contract tests for show/update responses (only lists covered).
- Per-route error boundaries already global — audit remaining crash risks
  (`new Date(invalid)`, `JSON.parse` on storage, large CSV exports).
- Loading/empty/error-state audit across all pages; mobile (360px) pass.
- `SESSION_DRIVER`/queue notes already in DEPLOYMENT.md — no code change.

### D. Research alignment (paper-ready)
- `docs/METHODOLOGY.md`: maps each research-title claim → spec section →
  implemented feature → test/file evidence (needed for the write-up).
- Mock-transparency appendix: every "AI preview"/mock badge photographed as
  evidence of honest mocking.

### E. Explicit v2 non-goals
- No live integrations (mock-everywhere stays locked, per v2 decision).
- No Neon or vendor DB (deployment-managed Postgres only).
- No real email/SMS, no SSO, no mobile app, no real ML models (rules + fixtures stand).

## 4. Decisions needed from you
1. Workstream order: proposed **A → B → C → D** (MVP story first). OK?
2. Release vehicle: **v1.1.0** (incremental) vs **v2.0.0** (rebrand)? Proposed v1.1.0 —
   no breaking API changes planned.
3. Demo script: which single client should walk all five stages in seeds?
   Proposed: Davao Prime Hotel (has call, opp, survey, followup already).
4. Branching: keep `feature/crm-*` → `develop` → `main` flow? (Proposed: yes.)

## 5. Proposed sequencing
1. `fix/v1.0.1-envelope-shapes` → PR → `develop` (this branch: §1 fixes + Neon removal).
2. Patch release `v1.0.1` tag on `main` after merge (regression fixes only).
3. v2 workstreams as `feature/crm-journey-*` branches in §4 order, each with
   spec acceptance + live smoke + screenshots, same per-PR checklist.

## 6. Phase 2 — submodule expansion toward a working system (locked 2026-09-20)

Locked decisions: detail pages + Finance first; Finance gets light workflows;
Core-1 section is see-only; drawer replaced by full pages; OTP mock untouched —
business flow has absolute priority. Target release vehicle: **`crm-v1.2.0`**.

### Phase 2A — Client/Lead detail pages (replace drawer)
- New routes `/clients/:id` and `/leads/:id` (drawer removed everywhere, deep links
  updated: win banner, NBA actions, notifications, tour copy).
- Tabs per profile, all backed by existing endpoints: Overview (header + ops cards
  + journey summary), Contacts, Opportunities, Communications, Surveys, Follow-ups,
  Job Orders, Finance (summary card linking to §2B section).
- Lead page: 360° progress checklist (capture → qualify → convert), score
  explainer, inline convert wizard (replaces modal), duplicate-warning panel.
- Acceptance: every tab loads with loading/empty/error states; no tab dead-ends;
  full suite + build green; light + dark screenshots.

### Phase 2B — Finance section (mock Dept 5, light workflows)
- New `invoices` table (mock-sourced, first-class like `job_orders`): ref, client,
  job order link, amount/balance, status `draft → sent → paid | overdue`, due date.
- Seeded from won deals + fixtures (deterministic refs, no Faker).
- Section UI: AR dashboard (aging buckets 0-30/31-60/61-90/90+, outstanding per
  client), invoice list with filters, **record mock payment** (applies amount,
  audit-logged), **mark paid**, overdue → **collection follow-up** (ties into 08).
- Client Finance tab reads the same endpoints.
- Acceptance: peso totals reconcile across dashboard/invoice/client views;
  every money action has confirm + toast + audit entry.

### Phase 2C — Core-1 Operations section (see-only) + submodule gaps
- Read-only Operations board: all job orders across clients (status columns),
  deployment read-backs per client, links into client pages. No advance buttons
  here (advancing stays on the client timeline from journey A).
- Submodule gaps, in order: leads **CSV import** (mock template download +
  row-error report); **configurable pipeline stages/labels** (admin master data);
  survey overdue → **auto follow-up**; follow-up **week/day views + drag
  reschedule**; comms **duration field + owner/date filters**; admin
  **master-data UI** (industries, sources, lost reasons); win/loss
  **effective date**.
- Acceptance per gap: seeded demo covers it; validation + toasts per spec.

### Phase 2D — BI expansion + release
- KPI monitoring strip (win rate trend, cycle length, NPS trend — mostly exists;
  surface consistently), predictive hints stay rule-based + badged.
- `docs/METHODOLOGY.md` gains Phase 2 rows; release `develop` → `main`, tag
  **`crm-v1.2.0`**.

### Explicit Phase 2 non-goals (unchanged from §E)
Mock-everywhere locked (incl. prod); no Neon/vendor DB; no real email/SMS/SSO/
mobile app/ML; **OTP stays exactly as mocked** — zero auth changes in Phase 2.

## 7. Pipeline↔finance refinement (locked 2026-09-21, branch `feature/crm-pipeline-finance`)
Business truth: monthly per-head billing (heads × rate × months); FB inquiry →
Quotation → Approval → **Contract** → Won/Lost; sources channel-flexible
(Facebook default, Gmail/Phone included); no-emoji UI; stable UI (additive only).

- **Stages**: keys frozen (`proposal`→“Quotation”, `negotiation`→“Approval” labels;
  existing rows untouched); new `contract` key @ 90% between Approval and Won.
- **Financing**: `headcount`, `rate_per_head_centavos`, `contract_months` on deals;
  server-computed `monthly_billing`/`contract_total`; cards + columns show ₱/mo.
- **Contract step**: entering Contract requires terms + start date → mock
  `contracts` row (`CTR-2026-XXXX`, Core-3/Governance/Facilities paper-backed);
  idempotent re-sign; shown on timeline + Finance tab.
- **Win invoice**: first monthly invoice (one month), not contract total; fallback
  to lump value when terms absent.
- **Capture**: `headcount_needed` + `positions`; +10 score for stated need.
- Merge path for this cycle: feature → `develop` → fast release to `main`.
