# 06 — Client Satisfaction & Survey System

## 1. Submodules
1. **Templates** — Net Promoter Score (0–10), Customer Satisfaction (1–5), custom builders with preview (3-step flow: Details → Questions → Preview).
2. **Send & collect** — create survey per client (link token), mock email/SMS send log, due date, reminder (ties to followups).
3. **Responses** — tokenized public respond page (no login, rate-limited), one response per token, edit window 24h.
4. **Analytics** — Net Promoter Score, Customer Satisfaction avg, response rate, trend line, per-client table, comment feed. UI uses full words with a "how scores work" explainer, never bare abbreviations.

## 2. User stories & acceptance
- As manager I create template → drag questions, live phone/desktop preview, Save shows "Template ready — send to a client".
- As rep I send survey → pick client + template + channel (mock), system shows share link + "copied!" + log entry in comms timeline; due auto +14d; overdue → followup auto-created (see `08`).
- As client contact (mock) I open link → 3-step form (score → reason → submit) with progress; success screen "Salamat! Response recorded."
- As manager I view analytics → NPS gauge (−100..+100 with promoter/passive/detractor split), CSAT avg, trend `TrendChart`, low-score (<7 NPS / <4 CSAT) flagged + "Create follow-up" shortcut.
- Satisfaction page is sectionized (Inbox / Templates / Performance) with anchor quick-jump, no tab gating.

## 3. API
```
GET|POST /survey-templates  GET|PUT /survey-templates/{id}
GET|POST /surveys?client_id&status  GET /surveys/{id}
POST /surveys {template_id, client_id, channel, due_at} → {survey + share_token}
GET /s/{token} (public)  POST /s/{token}/respond {score, answers, comment}
GET /surveys/analytics?from&to&team_id → {nps, csat_avg, response_rate, trend[], low_scores[]}
```
Rules: token `Str::random(32)` unique; status auto `sent→responded|expired` (scheduler); public route throttled 30/min.

## 4. UI
- Templates list + `SurveyBuilder`; Surveys inbox (client, template, status badge, due, response link copy); Analytics dashboard reusing `KpiCard/TrendChart/DonutChart`; comment wall with sentiment dot.
- Feedback: send confirm dialog ("Send NPS to BDO Unibank contact?"), copy-link toast, expired banner with Resend button.

## 5. Seeds
Templates: "Quarterly NPS", "Deployment CSAT", "Post-visit check". 8 surveys (5 responded with PH comments like "Mabilis ang deployment, salamat!"), 3 pending.
