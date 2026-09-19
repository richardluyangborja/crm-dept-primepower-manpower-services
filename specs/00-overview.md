# 00 — Overview — CRM Dept, PrimePower Manpower Services

## 1. Vision
Build a modern, intuitive **Client Relationship Management (CRM) system** for **PrimePower Manpower Services**, a manpower company. The CRM manages **client companies** (current + prospective) that request manpower — NOT applicants/workers (owned by other depts).

Goals:
- Centralize lead → client → opportunity lifecycle.
- Visualize pipeline, track satisfaction, log all communications, never miss a follow-up.
- Role-based access (superadmin, admin, manager, sales representative; extensible).
- API-first, integration-ready with the other 9 departments; mocks + PH-localized seeds for now.
- Reusable frontend components + reusable backend code; lots of inline instructions + interaction feedback.

## 2. Client context
- **Company:** PrimePower Manpower Services (manpower supplier across NCR, Calabarzon, Cebu, Davao).
- **CRM users:** internal sales/account team. External client contacts are records, not logins (v1).
- **Client companies examples:** BPOs in Makati/BGC, factories in Laguna/Cavite, hotels in Cebu/Davao, retail chains in Quezon City, hospitals, logistics firms.
- **Currency/locale:** PHP (₱), `en-PH`, Asia/Manila, +63 mobiles, PH addresses/barangays.

## 3. Scope — MVP (v1)
### In scope (5 core modules + platform)
1. Lead & Client Tracking (`04`)
2. Opportunity Pipeline Visualization (`05`)
3. Client Satisfaction & Survey (`06`)
4. Communication History Management (`07`)
5. Follow-up Reminders (`08`)
6. Accounts & Settings incl. Appearance light/dark (`09`, `10`)
7. Auth (JWT), RBAC, audit logs, notifications (in-app; email/SMS mocked)
8. Integrations via mock-service layer (`11`), static PH seeds, no Faker (`12`)

### Non-goals (v1)
- No applicant/employee/payroll logic (other depts own it).
- No real email/SMS gateway, no SSO, no mobile app, no AI scoring (stubbed).
- No multi-tenancy; single org (PrimePower) with RBAC.

## 4. Ten-department integration map
Source: `list-of-departments.md`. CRM is dept #10.

| # | Dept / system | CRM relationship (v1 = mock) |
|---|---|---|
| 1 | Client acquisition, recruitment, deployment | **HIGH** — CRM client → Job Order (mock); deployment status read-back (mock) |
| 2 | HR info & operations | MED — headcount per client (mock read) |
| 3 | Training/compliance/benefits | LOW — none in v1 |
| 4 | Governance/safety/admin | LOW — audit export |
| 5 | Financial management | **HIGH** — Opportunity Won → AR/Collection draft (mock); payment status (mock) |
| 6 | Supply chain/inventory | LOW — none |
| 7 | Fleet & transportation | LOW — site-visit transport request stub (optional) |
| 8 | Facilities/admin | LOW — meeting room stub (optional) |
| 9 | BI & analytics | **HIGH** — CRM exposes aggregate endpoints; BI pulls (mock consumer) |
| 10 | CRM (this system) | owner of leads/clients/opps/comms/surveys/followups |

Contract rule: every cross-dept call goes through `App\Services\Contracts\*` with a `Mock*` implementation when `INTEGRATIONS_MODE=mock`. See `11-integrations-mocks.md`.

## 5. Success criteria
- Sales rep can capture → qualify → convert → propose → win a client in < 10 clicks with guidance at each step.
- Manager sees pipeline value, aging, win-rate, NPS in one dashboard.
- Zero silent failures: every action has loading → success/error toast + inline validation.
- 100% PH-localized demo data without Faker; `migrate --seed` works offline.
- Light/dark parity (see `ui-references/`), responsive ≥360px.

## 6. Iteration plan
1. Specs (this folder) reviewed first — no code until approved.
2. Backend scaffold → auth/RBAC → 5 modules → settings → mocks/seeds.
3. Frontend scaffold → design system → 5 modules → settings → dashboard.
4. Integration hardening (real contracts replace mocks one dept at a time).
