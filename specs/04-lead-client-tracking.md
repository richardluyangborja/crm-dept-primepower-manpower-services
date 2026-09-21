# 04 — Lead & Client Tracking

## 1. Submodules (locked)
1. **Capture** — manual form, CSV import (mock template), duplicate guard. Sources are channel-flexible (`facebook` default/first, plus `gmail`, `phone`, referral, walk-in, website, cold_call, event) — Facebook is the common case, never the only one. Capture mirrors the Excel row: company + contact + phone + **headcount needed + positions**.
2. **Qualification & Scoring** — status flow + 0–100 score (rule-based v1): +20 PH corporate email, +15 complete address, +25 valid +63 phone, +10 buyer signal (`headcount_needed` present), +40 manager override note.
3. **360° Client Profile** — one scrolling page: header KPI strip (active contracts, open deals, fulfillment %, monthly value, satisfaction) + quick-jump links, then grouped sections (Profile incl. contacts → Deals → Contracts → Operations via Client Management → Conversations incl. comms/surveys/follow-ups → Billing via Finance → Insights).
4. **Conversion** — lead→client→(optional) opportunity wizard, the single bridge between the two pages (action lives on lead rows, lands on the 360 page).

## 1b. Hub model (no overlaps)
Sidebar parent **Lead & Client Tracking** (collapsible, same pattern as the Pipeline hub) with two filter-first pages — no per-operation sub-routes, no stacked sections:
- **Leads** (`/leads`): KPI strip (*Waiting on you* = new + contacted · *Hot leads* = open & score 70+ · *Won over* = converted) + one table with search, status filter, and a *Needs a response* quick-chip (queue = hottest first). Capture form + CSV import stay header actions, not pages.
- **Clients** (`/clients`): KPI strip (*Active clients* · *Prospects* · *Collectible now* = open balances) + one table with three views (*All clients* with per-row Deployed-staff deep link · *People* via `GET /contacts` · *Recently Won Over* from `status=converted`).
- Overlap contract: record views live here; cross-record management stays in Comms/Surveys/Follow-ups/Finance/Operations; Client Management execution stays read-only in Pipeline/Operations and is only *linked* (never copied) from hub pages.

## 2. User stories & acceptance
- As rep I capture a lead in < 60s with guidance → required: company, contact name, phone/email, source; inline PH validation; duplicate warning (same phone/email) with "View existing" link; toast "Lead created — qualify it next".
- As rep I qualify → status `new→contacted→qualified|unqualified|converted`; score auto: +20 PH corporate email, +15 complete address, +25 valid +63 phone, +40 manager override note; unqualified requires reason.
- As rep/manager I open client 360 → header (status badge, owner avatar, industry, city), quick-jump anchors to grouped sections; every section has EmptyState + CTA.
- As rep I convert → 3-step wizard (1 Confirm company → 2 Primary contact → 3 Create opening opportunity?); idempotent (409 if already converted); audit logged.

## 3. API (see `14` for envelopes)
```
GET    /leads?q&status&owner_id&sort  POST /leads  GET|PUT|DELETE /leads/{id}
POST   /leads/{id}/convert {client_payload, create_opportunity?:bool}
GET    /clients?q&status&industry&owner_id  POST /clients  GET|PUT|DELETE /clients/{id}
GET|POST /clients/{id}/contacts  PUT|DELETE /contacts/{id}
GET    /contacts?q&client_id (cross-client directory; client-scoped visibility, client_name included)
```
Validation: `contact_phone` regex `^\+63\d{10}$`, email RFC, company unique-ish (warn not block). Scope: rep=own, manager=team, admin=all (Policy).

## 4. UI
- **Leads page:** 3 KPI cards + quick-chip + *All Inquiries* table (search, status filter); capture/import as header buttons; row click → lead detail.
- **Clients page:** 3 KPI cards + view switch (directory / People / Recently Won Over); per-row staffing deep link (`/pipeline/staffing?client=`).
- **Client 360:** header KPI strip + grouped sections (Profile / Deals / Contracts / Operations / Conversations / Billing / Insights) with anchor quick-jump; convert wizard uses stepper + review screen.
- Feedback: score tooltip explains points; duplicate modal; CSV import shows row errors with line numbers (mock parser, 500-row limit).

## 5. Seeds (static, `12`)
Leads: "BGC Tech Solutions Inc. — Juan Dela Cruz", "Laguna Auto Parts Corp. — Maria Santos", etc. Clients: active/inactive/prospect across Makati, Cebu, Davao. No Faker.
