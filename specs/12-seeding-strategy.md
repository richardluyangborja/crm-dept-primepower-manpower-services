# 12 — Seeding Strategy (PH-localized, NO Faker)

## 1. Hard rule
**Do not use Faker** (breaks offline/prod minimal builds and yields non-PH data). All seeds are **static PHP arrays** in `backend/database/seeders/` + JSON fixtures in `backend/database/fixtures/`. `php artisan migrate:fresh --seed` must work offline and be idempotent (`firstOrCreate` by email/token/ref).

## 2. Files
```
Seeders: DatabaseSeeder → TeamSeeder, UserSeeder, LeadSeeder, ClientSeeder,
  OpportunitySeeder, ActivitySeeder, SurveyTemplateSeeder, SurveySeeder, FollowupSeeder
Fixtures: job_orders.json, invoices.json, headcount.json, bi_pull.json
```

## 3. PH data pools (copy-paste arrays)
- **Companies:** "BDO Unibank Inc.", "SM Supermalls – Cebu", "Laguna Auto Parts Corp.", "BGC Tech Solutions Inc.", "Cebu Pacific Catering Services", "Davao Prime Hotel", "Quezon City Retail Group", "Makati Medical Center", "Subic Logistics Corp.", "Iloilo Food Manufacturing Co."
- **People:** Juan Dela Cruz, Maria Santos, Jose Reyes, Ana Mendoza, Mark Villanueva, Jen Aquino, Marites Reyes, Paolo Gutierrez; positions: HR Manager, Procurement Head, Admin Officer.
- **Contacts:** `hrd@<domain>.ph`, mobiles `+63917…/+63927…/+63945…` (valid format, fictional).
- **Addresses:** "Ayala Ave, Makati City, Metro Manila", "IT Park, Cebu City, Cebu", "Lanang, Davao City", "Calamba, Laguna", "Clark, Pampanga".
- **Deals:** "80 security guards — Davao Prime Hotel — ₱2.4M", "120 janitors — SM Cebu — ₱4.8M", "50 production aides — Laguna Auto Parts — ₱1.9M".
- **Survey comments:** "Mabilis ang deployment, salamat!", "Ok ang guards pero need reliever pag Sunday.", "Paki-follow up ang billing, thanks."

## 4. Volumes (demo-friendly)
5 users, 2 teams + 10 leads, 8 clients (+14 contacts), 10 opps (spread stages), 30 activities, 3 templates + 8 surveys (5 responded), 12 followups (incl. overdue/escalated). All linked so every tab/calendar has content and no screen is empty on first run.
- AI (`15`): 2 high-risk clients (45d inactive + NPS ≤6 + 2 overdue), 1 low-risk (weekly activity + NPS 9); prefilled `insights_cache` so dashboard renders offline.
- OTP/session (`16`): `otp.demo@primepower.ph` (mock code `123456`, `OTP_MODE=mock` only); no enforcement in v1 seeds.

## 5. Neon/cloud note
Same seeders run against `NEON_DATABASE_URL` (`php artisan migrate --force --seed` in CI). No `faker` in `composer.json` require-dev path that seeds depend on.
