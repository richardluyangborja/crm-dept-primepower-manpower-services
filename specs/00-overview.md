# 00 — Overview — CRM Dept, Primepower Manpower Services

> **Program:** all 10 departments build one **Service Management and Enterprise Resource System** for Primepower.
> **This dept's research title:** "Design and Development of an Enterprise Service Management System with Data Analytics and Customer Intelligence for Enhanced Decision Support and Operations" (CRM track).

## 1. Vision
Build a modern, intuitive **Client Relationship Management (CRM) system** for **Primepower Manpower Services**, a manpower company. The CRM manages **client companies** (current + prospective) that request manpower — NOT applicants/workers (owned by other depts).

**Positioning: the CRM is the front office.** It owns the relationship lifecycle — acquiring leads, managing opportunities, maintaining client context, tracking communication and follow-ups, monitoring satisfaction, and decision support. Client Management (job orders, fulfillment, deployment) and Finance (billing, invoices, payments, receivables) are back-office core operations: the CRM displays their summarized status read-only and never performs their transactions. The win handoff (contract + job-order draft + first invoice) is the single sanctioned write into core flow.

Goals:
- Centralize lead → client → opportunity lifecycle.
- Visualize pipeline, track satisfaction, log all communications, never miss a follow-up.
- **AI-assisted data analytics + customer intelligence + management reports for decision support** (required by research title; see `15`). Phase 1 = rule-based insights + mocked AI; Phase 2 = real models.
- Role-based access (superadmin, admin, manager, sales representative; extensible).
- API-first, integration-ready with the other 9 departments; mocks + PH-localized seeds for now. Core-department data (deployment, headcount, billing) is consumed read-only through contract interfaces and surfaced with "via …" source tags.
- Reusable frontend components + reusable backend code; lots of inline instructions + interaction feedback.
- **Security (required, deferred — not MVP priority due to complexity): OTP + 5-min session timeout** (see `16`). Spec'd now, built after core modules + AI reports.

## 2. Client context
- **Company:** Primepower Manpower Services (manpower supplier across NCR, Calabarzon, Cebu, Davao).
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
9. **AI analytics dashboards + customer intelligence + management reports (`15`) — REQUIRED for research title**
10. **OTP (5-min expiry) + 5-min session timeout (`16`) — REQUIRED but DEFERRED (post-MVP, high complexity)**

### Non-goals (v1 MVP)
- No applicant/employee/payroll logic (other depts own it).
- No real email/SMS gateway, no SSO, no mobile app.
- AI scoring/LLM live calls stubbed behind mock interface in v1 (rule-based insights ship first).
- OTP + session-timeout enforcement stubbed/spec'd in v1, enforced in v2 (see `16`).
- No multi-tenancy; single org (Primepower) with RBAC.

## 4. Ten-department integration map
Source: `list-of-departments.md`. CRM is dept #10.

| # | Dept / system | CRM relationship (v1 = mock) |
|---|---|---|
| 1 | Client acquisition, recruitment, deployment (**Client Management**) | **HIGH** — CRM hands over the won requirement (mock job order); fulfillment/deployed status reads back read-only (mock) |
| 2 | HR info & operations | MED — deployed headcount per client (mock read) |
| 3 | Training/compliance/benefits | LOW — none in v1 |
| 4 | Governance/safety/admin | LOW — audit export |
| 5 | Financial management (**Finance**) | **HIGH** — Opportunity Won → first-month invoice draft (mock); AR/payment status reads back read-only (mock) |
| 6 | Supply chain/inventory | LOW — none |
| 7 | Fleet & transportation | LOW — site-visit transport request stub (optional) |
| 8 | Facilities/admin | LOW — meeting room stub (optional) |
| 9 | BI & analytics | **HIGH** — CRM exposes aggregate endpoints; BI pulls (mock consumer) |
| 10 | CRM (this system) | front office: leads/clients/opps/comms/surveys/followups + read-only core summaries |

Contract rule: every cross-dept call goes through `App\Services\Contracts\*` with a `Mock*` implementation when `INTEGRATIONS_MODE=mock`. See `11-integrations-mocks.md`.

## 5. Success criteria (mapped to research title)
- Sales rep can capture → qualify → convert → propose → win a client in < 10 clicks with guidance at each step.
- Manager sees pipeline value, aging, win-rate, NPS in one dashboard.
- **Decision support: manager/superadmin gets AI-assisted insights (at-risk clients, forecast, next-best-action) + exportable management reports (weekly/monthly) — even in v1 as rule-based + mock-AI with "AI preview" badges.**
- **Security readiness: OTP flow + 5-min idle timeout paths spec'd and UI-scaffolded, even if enforcement lands in v2.**
- Zero silent failures: every action has loading → success/error toast + inline validation.
- 100% PH-localized demo data without Faker; `migrate --seed` works offline.
- Light/dark parity (see `ui-references/`), responsive ≥360px.

## 6. Iteration plan (priority order — OTP/session deferred last)
1. Specs (this folder) reviewed first — no code until approved.
2. Backend scaffold → auth/RBAC (JWT baseline, OTP stubbed) → 5 modules → settings → mocks/seeds.
3. Frontend scaffold → design system → 5 modules → settings → dashboard.
4. **AI analytics + management reports (`15`): rule-based insights first, then mock-AI, then real models.**
5. **OTP + 5-min session timeout enforcement (`16`) — after AI reports (complex, needs throttle/lockout/UX care).**
6. Integration hardening (real contracts replace mocks one dept at a time).
