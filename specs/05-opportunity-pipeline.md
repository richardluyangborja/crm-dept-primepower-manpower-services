# 05 — Opportunity Pipeline Visualization

## 1. Submodules
1. **Kanban stages** — New, Contacted, Qualified, Proposal, Negotiation, Won/Lost (configurable labels via master data; admin only).
2. **Drag-drop + value** — optimistic move, WIP counts, weighted value.
3. **Win/Loss capture** — reason required on terminal move, comment, effective date.
4. **Forecasting** — weighted pipeline (`value × probability`), expected-close month bar, aging alert (>30d no activity → amber, >60d → red).

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
Rules: `move` validates transition (can't Won→New without manager note); probability defaults per stage (New 10 … Negotiation 80, Won 100); `value_centavos` integer ≥0.

## 4. UI
- `KanbanBoard` columns with sum + count, cards (client, title, ₱ value, probability chip, days-in-stage, owner avatar, next-followup dot). Search + owner filter sticky. List-view toggle for mobile/a11y (same data, table).
- Detail drawer: stage stepper, activities mini-timeline, followups, win/loss banner. Confetti-lite on Won (respect reduced-motion).

## 5. Seeds
6–10 opps across stages with ₱ values (e.g. "120 janitors — SM Cebu — ₱4.8M — Negotiation 80%"), one Won, one Lost with reason "chose competitor pricing".
