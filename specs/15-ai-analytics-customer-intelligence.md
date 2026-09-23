# 15 — AI Data Analytics, Customer Intelligence & Management Reports

> Required by research title: "…with Data Analytics and Customer Intelligence for Enhanced Decision Support and Operations". Ships incrementally: **rule-based insights (v1) → mock-AI (v1.1) → real models (v2)** without controller rewrites.

## 1. Objectives (decision support, not vanity charts)
- Answer: which clients are at risk? which opps will close? what should each rep do next? how did we perform this week/month?
- Every insight = number + why (top drivers) + link to source + suggested action (creates follow-up in one click).

## 2. Submodules
1. **Analytics dashboards (AI-assisted)** — Dashboard page extends `05/06` KPIs: revenue forecast (weighted + model-adjusted), win-rate trend, cycle length, NPS/CSAT trend, at-risk client list, rep leaderboard. All widgets show `AI preview` badge when from mock/heuristic.
2. **Customer intelligence** —
   - Lead score 0–100 (v1 rule-based: +PH corporate email, +complete address, +valid +63, +recent activity; v2 logistic regression via ai-service).
   - Churn/at-risk flag per client (rules v1: no activity >30d, overdue followups ≥2, NPS ≤6, lost opp ≤90d → risk high/med/low + reasons array).
   - Win probability per opp (v1 = stage default × recency/activity multiplier; v2 = model).
   - Survey sentiment (v1 keyword list EN/TL e.g. "salamat/mabilis/mabagal/delay"; v2 sklearn TF-IDF).
   - Next-best-action recommender (v1 decision tree → "Call X, send NPS to Y, escalate Z").
3. **Management reports** — one-click weekly/monthly packs for manager/superadmin: Executive summary (narrative), Pipeline & forecast table, Satisfaction (NPS/CSAT + comments), Activity & followup compliance, Risks & recommendations. Export CSV per table + print-ready HTML (browser → PDF, no paid lib). Scheduler emails mock in v1 (`reports:generate` weekly).
4. **Insight feedback loop** — thumbs up/down per insight (`insight_feedback` table) to train/evaluate v2 models; audit logged.

## 3. Architecture (OSS/free)
- v1: `App\Services\Insights\{LeadScorer,ChurnRisk,ForecastService,SentimentAnalyzer,NextBestAction,ReportService}` — pure PHP, deterministic, unit-tested, cached 15 min (`CACHE_DRIVER=file` dev).
- v1.1: `AiServiceInterface` + `MockAiService` (`AI_MODE=mock`) returning fixtures in `database/fixtures/ai/*.json` with identical shape to v2.
- v2 (optional service): `ai-service/` FastAPI + scikit-learn/pandas; Ollama (e.g. llama3.1:8b) only if hardware allows, else template narratives. Laravel `Http::timeout(5)->post(config('ai.url').'/predict',…)`, fallback to rules on failure + `X-AI-Fallback: rules` header.
- Tables: `insights_cache(client_id|opportunity_id, kind, payload jsonb, confidence, generated_at)`, `insight_feedback(id, insight_key, rating, note)`, `reports(id, type, period, payload jsonb, file_path nullable, generated_by)`.

## 4. API
```
GET /dashboard/summary (extended: {forecast, at_risk[], nba[]})
GET /insights/clients/{id} → {risk, drivers[], nba[]}
GET /insights/opportunities/{id} → {win_probability, drivers[]}
GET /hr/performance?user_id&period → {crm{…}, hr{…}, parts{…}, composite} (blended people score)
GET /reports/weekly|monthly?from&to&team_id → {narrative, tables{…}}
POST /reports/generate {type, period} (manager+) → queues job (mock mail in v1)
POST /insights/feedback {insight_key, rating, note}
```
- Errors: AI down → 200 with `meta.ai_fallback=true` (never block dashboard). Frontend shows "Rules-based — AI service unavailable".

## 5. UI
- Dashboard: `InsightCard` (score + drivers tooltip + "Create follow-up" CTA), forecast bar (weighted vs AI-adjusted), at-risk table (risk pill + reasons + owner), report preview modal with Print/CSV. Empty: "Not enough data — log 5+ activities to unlock insights".
- Feedback: every AI number has ⓘ explainer; toasts on report generation ("Weekly pack ready — download CSV").

## 6. Seeds
Deterministic: 2 high-risk clients (inactive 45d + NPS 5), 1 low-risk (weekly calls + NPS 9), opps with staged probabilities; `insights_cache` prefilled so dashboard renders offline.
