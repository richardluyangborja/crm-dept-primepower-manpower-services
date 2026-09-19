# 02 — Roles & Permissions

## 1. Roles (v1, extensible)
| Role | Description | Typical holder |
|---|---|---|
| `superadmin` | Full access incl. org settings, roles, backups, integrations toggle | IT owner |
| `admin` | Manage users/teams, master data (stages, templates, industries), reassign anything | Sales ops |
| `manager` | Team pipeline, approve proposals, view surveys/reports, reassign within team, escalations | Sales manager |
| `sales_rep` | Own leads/clients/opps/comms/surveys/followups assigned to them | Account executive |

Future roles (reserve ids/slugs): `viewer` (read-only), `client_contact` (portal, post-v1), `api_consumer` (BI/other depts). Adding a role = new row + policy update, no schema change.

## 2. Permission matrix (enforced backend via Policy + `role` middleware; frontend hides/guards routes)
| Capability | superadmin | admin | manager | sales_rep |
|---|---|---|---|---|
| Manage org settings/appearance | ✅ | ❌ | ❌ | ❌ |
| Manage users & roles | ✅ | ✅ | view team | ❌ |
| Master data (stages, templates, sources) | ✅ | ✅ | ❌ | ❌ |
| Create lead/client | ✅ | ✅ | ✅ | ✅ |
| View all leads/clients | ✅ | ✅ | team | own (+team view read-only optional) |
| Assign / reassign owner | ✅ | ✅ | team | ❌ |
| Convert lead→client→opportunity | ✅ | ✅ | ✅ | ✅ |
| Move opportunity stage / mark won/lost | ✅ | ✅ | ✅ | ✅ (own) |
| Send/log survey | ✅ | ✅ | ✅ | ✅ (own clients) |
| View survey analytics | ✅ | ✅ | ✅ | own |
| Log/view communications | ✅ | ✅ | team | own |
| Create/complete follow-ups | ✅ | ✅ | ✅ | ✅ (own) |
| View reminders calendar (team) | ✅ | ✅ | ✅ | own |
| Export CSV | ✅ | ✅ | ✅ | own |
| View AI insights & management reports (`15`) | ✅ | ✅ | ✅ | own (team rollup read-only optional) |
| Step-up OTP for deletes / go-live switches (`16`, v2) | ✅ | ✅ | ✅ | ✅ (own scope) |
| Delete records | ✅ (soft) | ✅ (soft, own scope) | request | ❌ |

Default scope: `sales_rep` sees `owner_id = me`; `manager` sees `team_id = my team`; `admin/superadmin` see all. Implemented via global scope `OwnedByTeam` + Policy `viewAny/view/update`.

## 3. Auth rules (JWT + deferred OTP/session, see `16`)
- Login `POST /auth/login` (email+password) → `{access_token, refresh_token, user, role}`. Refresh `POST /auth/refresh`. Logout denylists token.
- **v1:** JWT baseline only (`VITE_SESSION_TIMEOUT_ENABLED=false`, `OTP_MODE=mock`, no enforcement).
- **v2 (deferred):** login returns `{otp_required:true}` → `POST /auth/otp/verify` (6-digit, 5-min expiry) → tokens; **5-min idle timeout** (`401 session_expired` → re-login); step-up OTP (`428`) for role changes/deletes/go-live.
- Frontend stores tokens in memory + `sessionStorage` (never localStorage for access); refresh rotation on 401. Route guards: `RequireAuth`, `RequireRole(['manager','admin','superadmin'])`.
- Passwords: bcrypt 12, min 10 chars, lockout 5 attempts/15 min. Audit every login/refresh/logout.

## 4. Seed users (static, no Faker)
- `superadmin@primepower.ph / PrimePower123!`, `admin@primepower.ph`, `manager@primepower.ph` (team Manila), `rep.juandelacruz@primepower.ph`, `rep.mariasantos@primepower.ph`. Passwords overridden via env in prod.
