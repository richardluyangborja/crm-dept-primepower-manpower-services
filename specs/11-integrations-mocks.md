# 11 — Integrations & Mocks (10-dept ready, mocks now)

## 1. Principle
Every cross-dept call goes through a contract; v1 ships the **mock** behind the same interface. Switching to live = env flip + new implementation, no controller changes.

```php
// backend/app/Services/Contracts/JobOrderServiceInterface.php
pushWonOpportunity(Opportunity $opp): array; // -> ['job_order_ref'=>…]
// backend/app/Services/Mocks/MockJobOrderService.php (used when INTEGRATIONS_MODE=mock)
```

| Contract | Mock reads/writes | Fixture |
|---|---|---|
| `JobOrderService` (dept 1) | Won opp → `JO-2026-XXXX` ref; deployment status list | `fixtures/job_orders.json` |
| `BillingService` (dept 5 finance) | Won opp → **first monthly invoice** `INV-…` (one month's billing, not contract total); payment status | `fixtures/invoices.json` |
| `WorkforceService` (dept 2 HR) | headcount deployed per client | `fixtures/headcount.json` |
| `AnalyticsExport` (dept 9 BI) | aggregate endpoint consumed by BI (mock consumer script) | `fixtures/bi_pull.json` |
| `ContractSigning` (Core-3 docs + Governance legal + Facilities contracts — all paper-backed) | Signing persists a mock `contracts` row (`CTR-2026-XXXX`: terms snapshot + start date); surfaced read-only, no live system | — (row payload doubles as the record) |
| `NotifyService` | mail/SMS → `Log` + `notifications` row + `mock_outbox.json` | — |
| `OtpService` (`16`) | OTP send/verify → `Log` + `mock_outbox.json`, `OTP_MODE=mock` | `fixtures/otp_outbox.json` |
| `AiService` (`15`) | insights/predict → deterministic fixtures, `AI_MODE=mock` | `fixtures/ai/*.json` |

## 2. Conventions
- `INTEGRATIONS_MODE=mock|live` global + per-service override (`JOBORDER_MODE`). Mock latency 100–300ms + `X-Mock: true` header so UI can show "Mock mode" banner.
- Timeouts 5s, retry once, failure → toast "External system unavailable — saved locally, will sync" + `integration_jobs` row (status pending) for later replay.
- Webhooks (post-v1): `POST /webhooks/{dept}` HMAC stub, logged to `webhook_logs`.
- Frontend: `useIntegrationStatus()` hook shows per-page mock badge; no hardcoded URLs (only `VITE_API_URL`).

## 3. Acceptance
- With `mock`, winning an opp creates a timeline entry "Job Order JO-2026-… drafted (mock)" + draft invoice row; no network egress.
- `php artisan integrations:test --dept=finance` prints fixture round-trip OK.
- Docs in each fixture header: fields, owner dept, live endpoint placeholder.
