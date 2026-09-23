# 10 — UI/UX Design System (from `ui-references/` Fleet screens)

Reference: `light-mode.png` (light) + `dark-mode.webp` (Settings → Appearance). CRM reuses this visual language with CRM nav.

## 1. Layout shell (both modes)
- **Left sidebar (grouped, collapsible):** logo `Primepower Manpower / CRM`, sections:
  - `Dashboard` (KPIs + **AI insights preview + at-risk list**, see `15`)
  - `SALES`: Lead & Client Tracking (collapsible → Leads, Clients), Opportunity Pipeline (collapsible → Visualization Board, Billing, Deployed Staff, Contracts), Follow-ups
  - Hub pattern: parent auto-expands on the active child, chevron toggle with `aria-expanded`, collapsed state in `localStorage`, plain-language child titles, active style on children only (never the parent). Hub list pages are filter-first (KPI strip + one table + search/filter/chips) rather than tabbed or section-stacked; cross-module moves are links, never copies. Billing and Deployed Staff each carry a per-client section plus a full cross-client section (ledger/board folded in; standalone Finance/Operations pages removed, old routes redirect).
  - `ENGAGEMENT`: Communications, Satisfaction & Surveys
  - `AI & ANALYTICS`: Reports (weekly/monthly packs, `15`; BI stub)
  - `SYSTEM`: Settings, (Superadmin) Users & Access
  - Bottom: user card (avatar initial, name, email, logout icon).
- **Topbar:** hamburger (mobile), breadcrumb/title, `Quick search…` (⌘K, searches clients/leads/opps), bell with badge (notifications), avatar. Sticky, blur bg.
- **Content:** max-w 1400, 24px padding, KPI row (4 cards) → charts row (line + donut) → tables/kanban.

## 2. Theme tokens (Tailwind `darkMode:'class'`)
| Token | Light | Dark |
|---|---|---|
| bg app / card | `#F6F9FC` / `#FFFFFF` | `#0B1526` / `#111E32` |
| border | `#E2E8F0` | `#1E2E4A` |
| text primary/secondary | `#0F172A` / `#64748B` | `#F1F5F9` / `#94A3B8` |
| primary | `#0EA5E9` → hover `#0284C7` | same (contrast-checked) |
| success/warn/danger | `#16A34A` / `#D97706` / `#DC2626` | softened +10% lightness |
| radius/shadow | `rounded-xl (12px)`, `shadow-sm` cards | same, border instead of heavy shadow |

- **Appearance settings (mirror dark-mode.webp):** two preview cards (Light/Dark thumbnails) + radio check badge + `Sync with System` toggle (uses `prefers-color-scheme`; persisted per user, default system). `Save Changes` top-right; unsaved dot + confirm on leave.
- Fonts: Inter (system fallback); numbers tabular-nums; peso `₱` always prefix.

## 3. Interaction feedback (mandatory on every screen)
- Buttons: loading spinner + disabled; forms: RHF+Zod inline errors under field + red ring; toasts: success (green check), error (red, with Retry), info. Confirm destructive (delete/convert/mark-lost) via `ConfirmDialog`. Sign-out always confirms too — a stray click never kills a session.
- Empty states: illustration (Lucide) + 1-line instruction + primary CTA (e.g. "No follow-ups due — Create reminder").
- Skeletons for KPI/chart/table while `isLoading`; error card with Retry on query failure; optimistic update for kanban drag + followup done with rollback toast.
- Instructions: page subtitle ("Configure system preferences…"), field hints, stepper captions on lead-convert wizard, coach marks on first pipeline visit (dismissible).

## 4. Reusable components (frontend, all in `components/ui` + `components/crm`)
`AppSidebar, Topbar, QuickSearch, KpiCard(delta chip via TrendingUp/Down icons), TrendChart(Recharts line), DonutChart, DataTable(server pagination/sort/filter + CSV export), StatusBadge, FormField, EmptyState, ConfirmDialog(danger/info tones, custom confirm label, detail lines, required-input slot; all destructive/important actions confirm through it — zero native dialogs, sign-out included), StageUpModal(deal header + money strip + touchpoint/follow-up blocks + stage slot for pipeline rituals), Timeline, KanbanBoard(drag-drop, WIP count), SurveyBuilder, ReminderCalendar, ThemeToggle, Toaster(kind icon + bold title + muted body + optional action link; "Title — body" strings auto-split), InfoCallout(icon + lead + body + optional link for explainer strips), SectionHead(title + source tag + hint), ClientPicker(?client= synced dropdown)`. Props documented with Storybook-style examples in code comments; no duplicated table/kanban logic per module.
- **Collapsible nav groups:** a nav item may declare `children`; the group auto-expands when a child route is active, toggles manually via chevron (`ChevronDown`, rotating), and persists collapsed state in `localStorage` (`crm.nav.collapsed`). Child links indent with a guide border; `aria-expanded` set on toggles.
- AI/report additions (`15`): `InsightCard(score + drivers + CTA), RiskPill, ForecastBar, ReportPreviewModal, AiBadge("AI preview — verify")`.
- Auth additions (`16`, scaffolded v1): `OtpModal` (6 boxes, 05:00 countdown, resend), idle-warning modal ("Stay signed in?", 60s countdown).

## 5. Iconography — no emoji, ever (locked UI constraint)
- No emoji or pictographic symbols anywhere in the UI (buttons, toasts, badges, empty states, success screens). Rationale: inconsistent rendering across devices, unprofessional in B2B, inaccessible to screen readers.
- Use `lucide-react` (already a dependency, tree-shaken, free): `ThumbsUp/ThumbsDown` (votes), `Paperclip` (attachments), `Printer` (print/PDF), `Save` (save buttons), `Check`/`X` (affirm/close), `Upload`/`Download` (import/export/template), `Star` (scores, sentiments), `PenLine`/`FileText`/`Handshake` (contract actions).
- Text symbols `★✓✕▲▼→` are also out — same replacements apply. Arrows in copy (`→` inside link labels) are acceptable as typographic punctuation, not iconography.

## 6. Responsive & a11y
- ≥1280: full sidebar; 768–1279: icons; <768: drawer + bottom nav for Follow-ups/Comms. Charts stack; kanban → horizontal scroll with sticky stage headers.
- Contrast AA, focus rings, keyboard: `/` focuses search, `n` new lead (when not in input), Esc closes dialogs. `aria-label` on icon buttons; `prefers-reduced-motion` disables chart animation.
