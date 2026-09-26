# 08 — Follow-up Reminders System

## 1. Submodules
1. **Reminders CRUD** — title, linked client/opp, due datetime (Asia/Manila), priority, assignee.
2. **Snooze / done / overdue** — lifecycle `open→done|snoozed→open|overdue→escalated`.
3. **Escalation** — overdue >24h notifies owner + manager; >72h auto-escalates (`escalated_to=manager`, bell + in-app).
4. **Calendar & tasks** — month (prev/next/today, past + future) /week/day + "My tasks" list, drag to reschedule (manager/owner only). Month view is full-width with the selected day's reminders as a section below. Actions share one hierarchy everywhere: Done (primary green) · Snooze · Escalate (danger, overdue only).

## 2. User stories & acceptance
- As rep I create reminder → due picker blocks past, priority color preview, link picker (client/opp search); toast "Reminder set for Tue 9:00 AM — we'll notify you". Reps own what they set (locked to self).
- **Ownership (overhaul Phase 3):** admin/manager may assign to any active rep/manager via select (New Reminder, Client 360, stage ritual); unassigned reminders default to the company/account owner. Reassignment (PUT `owner_id`) is limited to the owner, the owner's team manager, or admin+, and writes a `reassigned` audit. Targets must be active reps/managers.
- As rep I work my day → "Due today" queue sorted by priority+time, one-click Done/Snooze (1d/3d/1w/custom); overdue section red with "Escalate now" if >72h.
- As manager I see team calendar → filter by member, overdue badge counts, reassign via dropdown (audit logged).
- Scheduler `reminders:dispatch` (every minute): due in 60m → notify; overdue transitions + notifications (DB; mail mocked). Timezone-safe (store UTC, display Manila).

## 3. API
```
GET /followups?status&owner_id&due_from&due_to&priority
POST /followups  GET|PUT|DELETE /followups/{id}
POST /followups/{id}/done|snooze {snoozed_until}|escalate {to_user_id}
GET /notifications  POST /notifications/{id}/read
```
Rules: `due_at` > now on create; only owner/manager/admin can complete others' (policy); delete = soft + audit. `owner_id` accepts active reps/managers only; reps are forced to self on create.

## 4. UI
- `ReminderCalendar` (month grid with priority dots + month nav, click day → full-width details section below) + "My tasks" `DataTable`; bell badge = unread count; browser Notification API opt-in (fallback in-app).
- Feedback: overdue banner ("3 overdue — clear them to keep pipeline healthy"), snooze toast with Undo, escalation notice ("Escalated to Marites Reyes (Manager)").

## 5. Seeds
Per rep: 2 due-today, 1 overdue, 1 snoozed, 1 done; one escalated example. Titles PH-contextual ("Follow up quotation — Cebu Pacific catering headcount").
