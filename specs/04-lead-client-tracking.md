# 04 — Lead & Client Tracking

## 1. Submodules (locked)
1. **Capture** — manual form, CSV import (mock template), duplicate guard. Sources are channel-flexible (`facebook` default/first, plus `gmail`, `phone`, referral, walk-in, website, cold_call, event) — Facebook is the common case, never the only one. Capture mirrors the Excel row: company + contact + phone + **headcount needed + positions**.
2. **Qualification & Scoring** — status flow + 0–100 score (rule-based v1): +20 PH corporate email, +15 complete address, +25 valid +63 phone, +10 buyer signal (`headcount_needed` present), +40 manager override note.
3. **360° Client Profile** — one scrolling page, no tabs: header + quick-jump links, then grouped sections (Profile incl. contacts → Deals & Orders → Conversations incl. comms/surveys/follow-ups → Billing).
4. **Conversion** — lead→client→(optional) opportunity wizard.

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
```
Validation: `contact_phone` regex `^\+63\d{10}$`, email RFC, company unique-ish (warn not block). Scope: rep=own, manager=team, admin=all (Policy).

## 4. UI
- **Leads list:** `DataTable` (company, contact, source badge, score bar, status, owner, updated) + filters + `New Lead` button; row click → drawer detail.
- **Client 360:** header + `StatusBadge` + grouped sections (Profile / Deals & Orders / Conversations / Billing) with anchor quick-jump; convert wizard uses stepper + review screen.
- Feedback: score tooltip explains points; duplicate modal; CSV import shows row errors with line numbers (mock parser, 500-row limit).

## 5. Seeds (static, `12`)
Leads: "BGC Tech Solutions Inc. — Juan Dela Cruz", "Laguna Auto Parts Corp. — Maria Santos", etc. Clients: active/inactive/prospect across Makati, Cebu, Davao. No Faker.
