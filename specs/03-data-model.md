# 03 — Data Model

## 1. ERD (Mermaid)
```mermaid
erDiagram
  users ||--o{ leads : owns
  users ||--o{ opportunities : owns
  users ||--o{ followups : assigned
  teams ||--o{ users : has
  leads ||--o| clients : converts_to
  clients ||--o{ contacts : has
  clients ||--o{ opportunities : has
  opportunities ||--o{ activities : logs
  clients ||--o{ activities : logs
  survey_templates ||--o{ surveys : instantiates
  clients ||--o{ surveys : targets
  surveys ||--o{ survey_responses : collects
  clients ||--o{ followups : needs
  opportunities ||--o{ followups : needs
  users ||--o{ audit_logs : performs
```

## 2. Tables (Postgres; `id` = bigint PKs — internal only; timestamps + `deleted_at` soft delete)
- `teams(id, name, region)` — e.g. Manila, Cebu, Davao.
- `users(id, team_id→teams, name, email unique, password, role enum[superadmin,admin,manager,sales_rep], is_active, last_login_at)`.
- `clients(id, owner_id→users, name, industry, size_band, address_city, address_province, contact_email, contact_phone, status enum[prospect,active,inactive,blacklisted], source, created_from_lead_id nullable)`.
- `contacts(id, client_id, full_name, position, email, phone, is_primary)`.
- `leads(id, owner_id, company_name, contact_name, contact_email, contact_phone, source enum[referral,walk_in,website,fb, холод…], status enum[new,contacted,qualified,unqualified,converted], score 0-100, notes)`.
- `opportunities(id, client_id, owner_id, title, stage enum[new,contacted,qualified,proposal,negotiation,won,lost], value_centavos, probability %, expected_close_date, lost_reason nullable, won_at/lost_at nullable)`.
- `activities(id, owner_id, client_id, opportunity_id nullable, type enum[call,email,meeting,site_visit,note], subject, body, occurred_at, attachments jsonb)`.
- `survey_templates(id, name, type enum[nps,csat,custom], questions jsonb, is_active)`.
- `surveys(id, template_id, client_id, sent_by, channel enum[link,email_mock,sms_mock], token unique, due_at, status enum[draft,sent,responded,expired])`.
- `survey_responses(id, survey_id, score, answers jsonb, comment, responded_at)`.
- `followups(id, owner_id, client_id, opportunity_id nullable, title, due_at, priority enum[low,medium,high], status enum[open,done,snoozed,overdue,escalated], snoozed_until nullable, escalated_to nullable)`.
- `notifications(id, user_id, type, title, body, read_at nullable, link nullable)`.
- `audit_logs(id, user_id, action, entity, entity_id, meta jsonb, created_at)`.

## 3. Conventions
- All money `integer` centavos; display `₱209,154` via `formatPHP()`.
- Phones stored E.164 (`+639171234567`); validate with regex `^\+63\d{10}$`.
- Indexes: `clients(owner_id,status)`, `leads(owner_id,status)`, `opportunities(stage,expected_close_date)`, `followups(owner_id,status,due_at)`, `activities(client_id,occurred_at)`, `surveys(token)`.
- Audit via `HasAuditLog` trait on write models; never hard-delete clients/opps (soft delete + `deleted_by` in meta).
