# 05 — Opportunity Pipeline Visualization

## 1. Submodules
1. **Kanban stages** — New (Inquiry), Contacted, Qualified, Quotation (Proposal), Approval (Negotiation), **Contract**, Won/Lost (labels via master data; admin only; keys stay fixed for logic).
2. **Drag-drop + value** — optimistic move, WIP counts, weighted value **plus expected monthly billing per column**.
3. **Win/Loss capture** — reason required on terminal move, comment, effective date.
4. **Contract signing** — moving into Contract requires agreed terms (headcount, monthly rate/head, months) + start date; persists a mock `contracts` row (Core-3 docs, Governance legal, Facilities contracts — all paper-backed mocks). Terms editable until win; re-signing updates, never duplicates. **Winning without an active contract is rejected (422) — the UI reroutes into signing.**
5. **Financing** — per-head monthly billing (`headcount × rate`), contract total (`monthly × months`), computed server-side; win issues the **first monthly invoice** (not the contract total). Deal value required at creation; manpower terms required from Qualified on. Won job orders carry headcount + value; Finance read-back sums real open invoices.
6. **Forecasting** — weighted pipeline (`value × probability`), expected-close month bar, aging alert (>30d no activity → amber, >60d → red).

## 2. User stories & acceptance
- As rep I drag a card between stages → card animates, column totals update instantly, toast "Moved to Proposal — undo?" (5s); on failure rollback + error toast; `lost_reason` modal when dropping to Lost.
- As manager I see forecast → header KPIs (Open value, Weighted, Win-rate 90d, Avg cycle days) + `TrendChart` closes by month + donut by stage; filter by team/owner/quarter.
- As admin I rename/reorder stages (no delete of Won/Lost); existing opps remap with confirm.

## 3. API
```
GET /opportunities?stage&owner_id&team_id&from&to  POST /opportunities
GET|PUT|DELETE /opportunities/{id}
POST /opportunities/{id}/move {stage, lost_reason?}  POST /opportunities/{id}/win|lose
GET /dashboard/summary → {open_value, weighted, win_rate, by_stage[], closes_by_month[]}
```
Rules: `move` validates transition (can't Won→New without manager note); probability defaults per stage (New 10 … Negotiation 80, **Contract 90**, Won 100); `value_centavos` integer ≥1 (required at create); manpower terms required when entering Qualified; active contract required when entering Won; financing fields optional on create/update otherwise, required (with `start_date`) when entering Contract.
- **Stage rituals:** every non-terminal move opens a `StageUpModal` (deal header + live money strip + optional touchpoint log + follow-up + stage block). Contacted logs first touch; Qualified locks terms; Quotation records value/date + auto follow-up; Approval adjusts probability + terms; backward moves confirm with a note (reopen note when leaving Won/Lost).

## 4. UI
- `KanbanBoard` columns with sum + count, cards (client, title, ₱ value, probability chip, days-in-stage, owner avatar, next-followup dot). Search + owner filter sticky. List-view toggle for mobile/a11y (same data, table).
- Detail drawer: stage stepper, activities mini-timeline, followups, win/loss banner. Confetti-lite on Won (respect reduced-motion).
- **Pipeline hub (sidebar, collapsible):** the Pipeline nav item expands to Visualization Board (default landing/summary) + Billing + Deployed Staff + Contracts. Auto-expands on the active child; collapsed state persists in `localStorage`. Board cards link per-client into each child (`?client=` deep links). Children are per-client detailed lists over the same scoped queries — no duplicated logic, no new mutations (Deployed Staff read-only; Billing/Contracts reuse existing actions). Child titles stay plain-language (no module/system jargon). BI drilldown was deprioritized and removed from the hub — the `GET /bi/client-breakdown` API stays available for Reports later.

## 5. Seeds
6–10 opps across stages with ₱ values (e.g. "120 janitors — SM Cebu — ₱4.8M — Negotiation 80%"), one Won, one Lost with reason "chose competitor pricing".
