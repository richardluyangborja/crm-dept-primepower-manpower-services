# 14 — API Conventions (Laravel ↔ React)

## 1. Base
- Prefix `/api/v1`, JSON only, `Accept: application/json`. Health `GET /api/v1/health → {ok:true, time}`.
- Auth: `Authorization: Bearer <access_jwt>`; public: `POST /auth/login|refresh`, `GET /health`, `POST /auth/otp/*`, `GET /s/{token}`. All else `auth:api` + `role`/`can` middleware.
- Session (v2 enforced, v1 scaffolded — see `16`): idle 300s → `401 {code:"session_expired", message:"…"}`; frontend interceptor clears storage + redirects to login. OTP step-up: `428 {otp_required:true}` → `<OtpModal>` → retry with `X-StepUp-Token`.
- CORS: `FRONTEND_URL` exact allowlist (comma-separated in prod), no credentials, exposed `Authorization`.

## 2. Standard shapes
```json
// success collection
{"data":[…],"meta":{"current_page":1,"per_page":15,"total":42}}
// success single
{"data":{…},"message":"Lead created"}
// error
{"message":"Validation failed","errors":{"contact_phone":["Must be +639…"]}}
```
- Pagination: `?page&per_page(≤100)&sort&dir&q&filters…`. Sorting allowlisted per controller.
- Resources: `LeadResource, ClientResource…` — snake→camel? Keep **snake_case** JSON to match Laravel; frontend maps once in `apiClient` if needed (prefer snake throughout to avoid bugs).
- **Opaque IDs (specs/03):** customer-facing entities (leads, clients, opportunities, contracts, invoices, job orders, contacts, surveys, follow-ups, activities, notifications) expose Hashids strings for `id` and every nested `*_id`; integer PKs stay internal. Route-model binding decodes transparently; tampered values 404 (no oracle); `owner_id`/`sent_by`/`template_id` (internal entities) stay numeric. Frontend treats IDs as opaque strings — no `Number()`, no arithmetic. CSV exports keep raw keys for the BI team.

## 3. Reusable backend pieces (mandatory)
- `App\Traits\ApiResponse (ok/created/paginated/error)`, `HasAuditLog`, `Filterable`.
- `FormRequest` per write op (`StoreLeadRequest` with PH phone rule, `MoveStageRequest` requiring `lost_reason` when stage=lost).
- `Service` per module (`LeadService::convertToClient()`, `OpportunityService::moveStage()`) — controllers ≤ 40 lines.
- Policies: `LeadPolicy, OpportunityPolicy…` enforcing owner/team/admin scope.
- Error codes: 401 (refresh once), 403 (show "Ask your manager"), 422 (inline field errors), 409 (duplicate client by email/phone).

## 4. Endpoint index (full detail in 04–08, 15–16)
```
POST /auth/login|refresh|logout|me
POST /auth/otp/send|verify|resend        # 16 (v1 mock, v2 enforced; 5-min expiry)
CRUD /leads (company_id|company:{}, one-active-lead 409, converted is system-only), /clients, /contacts, /opportunities (company_id required unless client given; client adopted), /activities, /survey-templates, /surveys, /surveys/{id}/respond, /followups, /notifications
GET /companies /companies/lookup?name&phone /companies/{id}   # company root + duplicate prompt
POST /opportunities/{id}/move  POST /opportunities/{id}/win|lose
GET  /dashboard/summary  GET /reports/* (BI-compatible)
GET  /insights/clients/{id}  GET /insights/opportunities/{id}   # 15
GET  /reports/weekly|monthly  POST /reports/generate  POST /insights/feedback  # 15
```
- Idempotency: `POST` convert/win guarded (409 if already converted). Audit every write.
- Frontend: one `apiClient`, React Query keys mirror paths, mutations invalidate (`['opportunities']`, `['dashboard']`), toasts on success/error, 401→refresh→retry-once queue.
