# 14 — API Conventions (Laravel ↔ React)

## 1. Base
- Prefix `/api/v1`, JSON only, `Accept: application/json`. Health `GET /api/v1/health → {ok:true, time}`.
- Auth: `Authorization: Bearer <access_jwt>`; public: `POST /auth/login|refresh`, `GET /health`. All else `auth:api` + `role`/`can` middleware.
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

## 3. Reusable backend pieces (mandatory)
- `App\Traits\ApiResponse (ok/created/paginated/error)`, `HasAuditLog`, `Filterable`.
- `FormRequest` per write op (`StoreLeadRequest` with PH phone rule, `MoveStageRequest` requiring `lost_reason` when stage=lost).
- `Service` per module (`LeadService::convertToClient()`, `OpportunityService::moveStage()`) — controllers ≤ 40 lines.
- Policies: `LeadPolicy, OpportunityPolicy…` enforcing owner/team/admin scope.
- Error codes: 401 (refresh once), 403 (show "Ask your manager"), 422 (inline field errors), 409 (duplicate client by email/phone).

## 4. Endpoint index (full detail in 04–08)
```
POST /auth/login|refresh|logout|me
CRUD /leads, /clients, /contacts, /opportunities, /activities, /survey-templates, /surveys, /surveys/{id}/respond, /followups, /notifications
POST /leads/{id}/convert  POST /opportunities/{id}/move  POST /opportunities/{id}/win|lose
GET  /dashboard/summary  GET /reports/* (BI-compatible)
```
- Idempotency: `POST` convert/win guarded (409 if already converted). Audit every write.
- Frontend: one `apiClient`, React Query keys mirror paths, mutations invalidate (`['opportunities']`, `['dashboard']`), toasts on success/error, 401→refresh→retry-once queue.
