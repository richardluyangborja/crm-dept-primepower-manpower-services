# 16 — OTP & 5-Min Session Timeout (REQUIRED, DEFERRED — post-MVP)

> Required feature, **explicitly not MVP priority** due to complexity (throttling, lockout, idle-tracking UX, mail/SMS plumbing). Spec it now so v1 code stays forward-compatible; enforce in v2.

## 1. Requirements
- **OTP:** 6-digit numeric, **expiry 5 minutes**, single-use, max 5 verify attempts → invalidate + 15-min cooldown. Used for: (a) login second factor (configurable: all users or admin/superadmin only in v1 of enforcement), (b) sensitive actions (role change, delete client, switch integrations to live) — step-up auth.
- **Session timeout:** **5-minute idle timeout**. No mouse/keyboard/API activity for 300s → access + refresh rejected (`401 {code:"session_expired"}`) → frontend logs out to login with "Session expired after 5 minutes of inactivity — please log in again." Absolute max session 12h regardless of activity.
- Transport in v1: OTP via `Log` driver + in-app test inbox (`OTP_MODE=mock` shows code in dev banner + `mock_outbox.json`); prod later via free email (SMTP) — never paid SMS in scope.

## 2. Data & config
- Tables: `otps(id, user_id, purpose enum[login,step_up], code_hash, expires_at, attempts, consumed_at, created_at)`, `sessions` reuse JWT denylist + `user_sessions(id, user_id, jti, ip, ua, last_activity_at, expired_at)`.
- Env: `OTP_MODE=mock|live`, `OTP_TTL=300`, `OTP_MAX_ATTEMPTS=5`, `SESSION_IDLE_TIMEOUT=300`, `SESSION_ABSOLUTE_TIMEOUT=43200`, `OTP_STEPUP_ACTIONS=role.change,client.delete,integrations.live`.
- Never store OTP plaintext (bcrypt `code_hash`); rate-limit `otp:send 5/min/user`, `otp:verify 10/min/user` (Laravel throttle).

## 3. Flows (enforcement phase)
1. Login: `POST /auth/login` → 200 `{otp_required:true, otp_challenge_id}` (no tokens yet) → user enters code → `POST /auth/otp/verify` → tokens issued + `user_sessions` row.
2. Idle: frontend `useIdleTimer(300s)` warns at 240s ("You'll be logged out in 60s — Stay signed in?") → any API call bumps `last_activity_at`; 300s idle → next call 401 `session_expired` → interceptor clears storage, redirects, toast.
3. Step-up: sensitive `POST` returns `428 {otp_required:true}` → modal OTP → retry original request with `X-StepUp-Token` (5-min single-use JWT claim).
- Audit every send/verify/expire/escalation; lockout after 5 bad codes.

## 4. v1 (MVP) forward-compat checklist — DO NOW so v2 is painless
- [ ] `users.phone` + `otp_enabled bool default false` columns migrated in v1 (unused until v2).
- [ ] `OtpServiceInterface + MockOtpService` + `otps` migration + `mock_outbox.json` writer (no enforcement).
- [ ] Frontend: `<OtpModal>` + `useIdleTimer` hook + `session_expired` interceptor branch scaffolded (flag `VITE_SESSION_TIMEOUT_ENABLED=false` in v1).
- [ ] Seed one OTP test user (`otp.demo@primepower.ph`, mock code `123456` documented in `12`).

## 5. UI (when enforced)
Login step 2 (6 boxes, auto-focus, paste support, 05:00 countdown, Resend after 60s with cooldown notice); idle warning modal with countdown + "Stay signed in" (re-verify if >4min idle); step-up modal for deletes/mode switches. All toasts + audit-visible ("OTP verified", "Session expired").

## 6. Acceptance (v2 gate)
- Code expires at exactly 300s (clock-skew ≤30s tolerated); replay of consumed code → 410.
- 5:01 idle → next request 401 `session_expired`; active clicking keeps session alive 12h max.
- Brute force: 6th bad attempt → 429 + cooldown; verified via `php artisan test --filter=Otp`.
