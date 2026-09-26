# 04 — Lead & Client Tracking

## 1. Submodules (locked)
1. **Capture** — 2-step form (Company → Contact & need) over the company root: company search-or-create with duplicate-company prompt (`GET /companies/lookup`), then single primary contact + requirement. Sources are channel-flexible (`facebook` default/first, plus `gmail`, `phone`, referral, walk-in, website, cold_call, event). Capture collects company identity (name*, industry, city*, province, email, phone) + contact (name*, position, email, mobile) + **headcount needed + positions**. One active lead per company (409 points at the open one). CSV import resolves-or-creates companies per row.
2. **Qualification & Scoring** — manual statuses `new→contacted→qualified|unqualified` (`converted` is system-only, set on won); 0–100 score (rule-based v1): +20 PH corporate email, +15 complete address, +25 valid +63 phone, +10 buyer signal (`headcount_needed` present), +40 manager override note. Qualifying opens the opportunity prompt (skippable once); unqualified requires reason.
3. **360° Client Profile** — one scrolling page: header KPI strip (active contracts, open deals, fulfillment %, monthly value, satisfaction) + quick-jump links, then grouped sections (Profile incl. contacts → Deals → Contracts → Operations via Client Management → Conversations incl. comms/surveys/follow-ups → Billing via Finance → Insights).
4. **Conversion (retired as a manual action)** — clients are born from won deals only (Phase 3): won → find-or-create the company's client → link opp → convert open lead(s) → auto-active. `POST /leads/{id}/convert` deprecated, frontend no longer calls it. Lost deals leave the lead qualified.

## 1b. Hub model (no overlaps)
Sidebar parent **Lead & Client Tracking** (collapsible, same pattern as the Pipeline hub) with two filter-first pages — no per-operation sub-routes, no stacked sections:
- **Leads** (`/leads`): KPI strip (*Waiting on you* = new + contacted · *Hot leads* = open & score 70+ · *Won over* = converted) + one table with search, status filter, and a *Needs a response* quick-chip (queue = hottest first). Capture form + CSV import stay header actions, not pages.
- **Clients** (`/clients`): KPI strip (*Active clients* · *Prospects* · *Collectible now* = open balances) + one table with three views (*All clients* with per-row Deployed-staff deep link · *People* via `GET /contacts` · *Recently Won Over* from `status=converted`).
- Overlap contract: record views live here; cross-record management stays in Comms/Surveys/Follow-ups/Finance/Operations; Client Management execution stays read-only in Pipeline/Operations and is only *linked* (never copied) from hub pages.

## 2. User stories & acceptance
- As rep I capture a lead in < 60s with guidance → company search-or-create (duplicate prompt), then contact + requirement; required: company, contact name, phone/email, source; inline PH validation; duplicate warning (same phone/email) with "View existing" link; one-active-lead guard (409 links the open lead); toast "Lead created — qualify it next".
- As rep I qualify → status `new→contacted→qualified|unqualified` (never `converted` by hand); score auto: +20 PH corporate email, +15 complete address, +25 valid +63 phone, +40 manager override note; unqualified requires reason; qualifying opens the first-deal prompt.
- As rep/manager I open client 360 → header (status badge, owner avatar, industry, city), quick-jump anchors to grouped sections; every section has EmptyState + CTA.
- **Company-owner assignment:** admin/manager pick a sales rep on capture (reps auto-own); new leads/deals default to the company owner, and an explicit assignment moves the company too (audited `owner_assigned`). Reps see rows they own **or** rows under companies they own; `owner_id` accepts active reps/managers only.

## 3. API (see `14` for envelopes)
```
GET    /leads?q&status&owner_id&company_id&sort  POST /leads {company_id?|company:{}, contact_*, requirement_*}
GET|PUT|DELETE /leads/{id}  (PUT rejects status=converted: system-only)
POST   /leads/{id}/convert — REMOVED (Phase 3; clients are born from won deals)
GET    /companies?q&industry&owner_id  POST /companies  GET|PUT /companies/{id}
GET    /companies/lookup?name&phone (duplicate-company prompt + open-lead counts)
GET    /clients?q&status&industry&owner_id  POST /clients  GET|PUT|DELETE /clients/{id}
GET|POST /clients/{id}/contacts  PUT|DELETE /contacts/{id}
GET    /contacts?q&client_id (cross-client directory; client-scoped visibility, client_name included)
```
Validation: `contact_phone` regex `^\+63\d{10}$`, email RFC, company unique-ish (warn not block). Scope: rep=own, manager=team, admin=all (Policy).

## 4. UI
- **Leads page:** 3 KPI cards + quick-chip + *All Inquiries* table (search, status filter); capture/import as header buttons; row click → lead detail.
- **Clients page:** 3 KPI cards + view switch (directory / People / Recently Won Over); per-row staffing deep link (`/pipeline/staffing?client=`).
- **Client 360:** header KPI strip + owner name + Transfer ownership button (owner, same-team manager, admin+) + grouped sections (Profile / Deals / Contracts / Operations / Conversations / Billing / Insights) with anchor quick-jump; "New deal" deep-links the pipeline form with the client preselected.
- Feedback: score tooltip explains points; duplicate modal; CSV import shows row errors with line numbers (mock parser, 500-row limit).

## 5. Seeds (static, `12`)
Leads: "BGC Tech Solutions Inc. — Juan Dela Cruz", "Laguna Auto Parts Corp. — Maria Santos", etc. Clients: active/inactive/prospect across Makati, Cebu, Davao. No Faker.
