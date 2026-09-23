# PrimePower CRM — Demo Script (10–12 minutes)

How to show the whole system, start to finish. Assumes a fresh seed.

## 0. Setup (1 min, before the audience arrives)

```bash
docker compose up -d db
cd backend && php artisan migrate:fresh --seed && php artisan serve --port=8000
cd frontend && npm run dev
```

Open `http://127.0.0.1:5173/`. Log in as sales rep:

- `rep.juandelacruz@primepower.ph` / `PrimePower123!` (no OTP)

(Managers: `manager@primepower.ph`. Admins: `admin@primepower.ph` + OTP `123456`.)

## Act 1 — The inquiry arrives (Leads, ~2 min)

1. Sidebar → **Lead & Client Tracking → Leads**.
2. Point at the KPI strip: *Waiting on you / Hot leads / Won over*.
3. Click **+ New lead**: company “ABC Manufacturing”, contact, +63 mobile, **50 heads**, positions “production aides”, source facebook. Save.
4. Toggle **Needs a response** — the new lead sits on top, sorted by score.
5. Open it → qualify it (status dropdown). Note the score explainer.

> Say: “Every inquiry carries the manpower requirement from the first minute — heads and positions are first-class, not notes.”

## Act 2 — The deal moves (Pipeline rituals, ~4 min)

1. Sidebar → **Opportunity Pipeline** (Visualization Board). The ABC deal is in Inquiry — create it first via **+ New deal** if needed (value required).
2. **Drag Inquiry → Contacted**: the popup asks to log the first touch. Log a call, “connected”, add a note. Note the live money strip. Confirm — the touchpoint is now in history.
3. **Drag → Qualified**: the popup demands heads/rate/months + value (prefilled where known). Fill 50 × ₱15,000 × 12. The ₱/mo preview computes live.
4. **Drag → Quotation**: record the quoted value + sent date. A 3-day follow-up is pre-booked.
5. **Drag → Approval**: win chance is automatic — add a discussion note via the suggestion chips.
6. **Drop on Contract**: sign with the same terms. The preview shows monthly × months = total.
7. **Mark won**: try it *without* signing on another deal to show the reroute (“Sign the contract first”). On the signed deal, confirm the win.
8. Linger on the green narration: job order ref, first monthly invoice, client timeline link, survey shortcut.

> Say: “A deal can never be worth zero, and it can never be won without a signed contract. The money story is enforced, not hoped for.”

## Act 3 — One client, full picture (Client 360, ~2 min)

1. Open **Lead & Client Tracking → Clients → ABC Manufacturing** (or follow the timeline link).
2. Header strip: active contracts, open deals, fulfillment %, monthly value, satisfaction.
3. Scroll: **Deals → Contracts → Operations** (required/deployed/remaining bar, via Client Management) → **Conversations** (the call from Act 2 is there) → **Billing** (invoice, via Finance) → **Insights** (health + next actions).

> Say: “This is the front-office promise: requirement, contract, history, and context — one page. Deployment and money stay with the back office; we show their status.”

## Act 4 — The relationship continues (~2 min)

1. **Engagement → Communications**: log the client's “8 workers still undeployed” call against the opportunity.
2. **Follow-ups**: show the auto-created reminders; drag one on the calendar.
3. **Satisfaction & Surveys**: send a Net Promoter Score survey; open Performance — promoter split, trend, per-client scores.

## Act 5 — Decision support (Dashboard + Reports, ~2 min)

1. **Dashboard**: KPI cards (all clickable), stage donut (click a slice → board), lifecycle strip (contracts, monthly recurring, deployed, AR), renewals card, at-risk list with drivers.
2. **Reports**: generate the monthly pack — narrative, tables, CSV export.

## If asked…

- **Unpredictable IDs?** Every record URL is an opaque hash (`/clients/xY9k…`); `/clients/1` 404s. Tampered hashes 404, cross-team access still 403s.
- **Who can do what?** Superadmin (seeded, hidden from lists) → admins → managers/sales. Try inviting an admin as an admin — blocked with an explanation.
- **Destructive actions?** Sign-out, deactivations, collections, survey sends — everything asks first. No browser popups anywhere.
- **Reset the demo?** `php artisan migrate:fresh --seed` (2 min). Logins in `specs/12-seeding-strategy.md`.
