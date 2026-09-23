# Primepower CRM — Demo Script (10–12 minutes)

How to show the whole system, start to finish. Assumes a fresh seed.
The story: **companies** are the root — leads and deals hang off them, and a
client is born only when the first deal is won.

## 0. Setup (1 min, before the audience arrives)

```bash
docker compose up -d db
cd backend && php artisan migrate:fresh --seed && php artisan serve --port=8000
cd frontend && npm run dev
```

Open `http://127.0.0.1:5173/`. Log in as sales rep:

- `rep.juandelacruz@primepower.ph` / `Primepower123!` (no OTP)

(Managers: `manager@primepower.ph`. Admins: `admin@primepower.ph` + OTP `123456`.)

## Act 1 — The company asks (Leads, ~2 min)

1. Sidebar → **Lead & Client Tracking → Leads**.
2. Point at the KPI strip: *Waiting on you / Hot leads / Won over*.
3. Click **+ New lead**. Type “ABC” — the duplicate lookup fires. Pick **create new**:
   company “ABC Manufacturing” (Manufacturing, Calamba, Laguna), then contact
   (name, position, +63 mobile) and requirement (**50 heads**, “production aides”).
4. Toggle **Needs a response** — the new lead sits on top, sorted by score.
5. Open it → qualify it. The **deal prompt** appears — open the first deal
   (value required). Skip once to show it's skippable, then open it.

> Say: “Capture starts with the company, not a free-text row. One company, one
> active lead — the system points you at the open one instead of duplicating.”

## Act 2 — The deal moves (Pipeline rituals, ~4 min)

1. Sidebar → **Opportunity Pipeline** (Visualization Board). The ABC deal has
   no client yet — that's correct at this stage.
2. **Drag Inquiry → Contacted**: log the first touch (call, “connected”, note).
   Note the live money strip. The touchpoint lands in history.
3. **Drag → Qualified**: terms editor (prefilled where known). Fill
   50 × ₱15,000 × 12 — the ₱/mo preview computes live. Backend gates back it.
4. **Drag → Quotation**: quoted value + sent date; 3-day follow-up pre-booked.
5. **Drag → Approval**: win chance automatic — add a discussion note via chips.
6. **Drop on Contract**: sign. Preview shows monthly × months = total.
7. **Mark won**: on an unsigned deal, show the reroute (“Sign the contract
   first”). On the signed deal, confirm — then linger on the green narration:
   job order, first monthly invoice, client timeline, survey shortcut.

> Say: “A deal can never be worth zero, and it can never be won without a
> signed contract. The money story is enforced, not hoped for.”

## Act 3 — A client is born (Client 360, ~2 min)

1. Follow the timeline link — **ABC Manufacturing is now a client** (active).
   The lead flipped to converted by itself; nobody clicked Convert.
2. Header strip: contracts, open deals, fulfillment %, monthly value, satisfaction.
3. Scroll: **Deals → Contracts → Operations** (50 required vs deployed bar) →
   **Conversations** (the Act 2 call is there) → **Billing** (invoice) →
   **Insights** (gap/renewal/risk actions).

> Say: “Clients aren't created by hand — they're what a company becomes when
> it signs and wins. Everything before that was one continuous story.”

## Act 4 — The relationship continues (~2 min)

1. **Engagement → Communications**: log the client's “8 workers still
   undeployed” call against the opportunity.
2. **Follow-ups**: auto-created reminders; month calendar with the day's
   details below; drag to reschedule.
3. **Satisfaction & Surveys**: send a Net Promoter Score survey; open
   Performance — promoter split, trend, per-client scores.

## Act 5 — Decision support (Dashboard + Reports, ~2 min)

1. **Dashboard**: clickable KPI cards, stage donut (click a slice → board),
   lifecycle strip, renewals, at-risk list with drivers.
2. **Reports**: generate the monthly pack — narrative, tables, CSV export.

## If asked…

- **Lead → client, exactly?** Capture (company + contact + need) → qualify →
  deal → sign → won. Won creates/links the client, converts open leads,
  activates the account. Lost leaves the lead qualified for the next try.
- **Unpredictable IDs?** Every record URL is an opaque hash (`/clients/xY9k…`);
  `/clients/1` 404s. Tampered hashes 404, cross-team access still 403s.
- **Who can do what?** Seeded superadmin (hidden, immutable) → admins
  (manager/sales only) → managers → sales. Try inviting an admin as an
  admin — blocked with an explanation.
- **Destructive actions?** Sign-out, deactivations, collections, survey sends —
  everything asks first. No browser popups anywhere.
- **Reset the demo?** `php artisan migrate:fresh --seed` (2 min). Logins in
  `specs/12-seeding-strategy.md`.
