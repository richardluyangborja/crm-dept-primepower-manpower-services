# 07 — Communication History Management

## 1. Submodules
1. **Unified timeline** — all touchpoints per client/opportunity, chronological, filterable.
2. **Loggers** — call, email (mock), meeting, site visit, note; duration + outcome + next-step hook.
3. **Templates & attachments** — canned intros/followup emails, attachment metadata (local disk v1).
4. **Search** — full-text-ish (`q` on subject/body) + type/date/owner filters.

## 2. User stories & acceptance
- As rep I log a call in < 30s → type picker with icons, prefilled "occurred now", outcome select (connected/no-answer/callback), "Create follow-up" checkbox auto-opens `08` form prefilled; toast "Logged — timeline updated".
- As manager I filter timeline → by type/owner/date, search "quotation", result highlights match; export CSV (scoped).
- As rep I reuse template → insert → edit → send-as-mock logs entry with `channel=email_mock` + appears in timeline with envelope icon.
- Attachments: ≤10MB, allowlist pdf/jpg/png/docx; stored `storage/app/comms`, listed with download; virus-scan stub note.

## 3. API
```
GET /activities?client_id&opportunity_id&type&from&to&q
POST /activities {client_id, opportunity_id?, type, subject, body, occurred_at, outcome, create_followup?}
GET|PUT|DELETE /activities/{id}
GET /message-templates  POST /activities/from-template {template_id,…}
```
Validation: `occurred_at` ≤ now, `subject` required for email/meeting, `client_id` required. Auto-touch: logging updates parent `last_contacted_at`.

## 4. UI
- `Timeline` (dot icons per type, relative time "2h ago", expand body, attachment chips, outcome badge); composer modal with type tabs; template gallery popover; filter bar sticky.
- Feedback: unsaved-changes guard, "No communications yet — log the first call" empty state, send-as-mock banner ("Mock mode — no real email sent").

## 5. Seeds
Per seeded client: 3–5 entries mixing calls/emails/site-visits with PH-flavored notes ("Site visit — Laguna plant, guard shifting discussed").
