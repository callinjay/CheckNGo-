# CheckNGo — Testing Checklist

Status of each verification point from the original spec (section 21),
as of the end of Phase 5. "Manually verified" means: I traced the code
path and it is logically correct and uses real DB reads/writes with
proper scoping — but since this was built without a live PHP/MySQL
runtime available in this environment, **you should still click through
each flow yourself on your XAMPP install** before a defense or submission.
Treat this file as a guide for that pass, not a substitute for it.

| # | Requirement | Status | Where to check |
|---|---|---|---|
| 1 | Register and log in | Implemented | `register.php`, `login.php`, `AuthController.php` |
| 2 | Create and manage belongings | Implemented | `my-items.php`, `Belonging.php`, `ItemController.php` |
| 3 | Create activity-specific templates | Implemented | `template.php`, `Checklist.php` |
| 4 | Correct checklist generated per activity | Implemented | `checklist.php` → `ChecklistService::startOrResume` snapshots the activity's default template |
| 5 | Priority levels saved correctly | Implemented | `checklist_template_items.priority`, editable in `template.php` |
| 6 | Schedule departure reminders | Implemented | `reminders.php`, `Schedule.php`, `ReminderService::generateForSchedule` |
| 7 | Reminder processor identifies due reminders | Implemented | `ReminderService::processDueReminders`, `cron/process-reminders.php`, or the manual "Process Due Reminders Now" button |
| 8 | Notifications include relevant checklist info | Implemented | `ReminderService::buildContext` pulls live unchecked required items into the message |
| 9 | Checklist progress saved correctly | Implemented | `ajax/toggle-item.php` → `ChecklistService::toggleItem`, persisted per keystroke, no "Save" step needed |
| 10 | Readiness calculations accurate | Implemented, documented | `services/ReadinessService.php` — single formula, used everywhere a percentage is shown |
| 11 | Critical unchecked items trigger warning | Implemented | Quick Check status message + blocked/override-confirmed departure in `ChecklistService::confirmDeparture` |
| 12 | Completed sessions appear in history | Implemented | `history.php` lists all `checklist_sessions` with filters |
| 13 | Statistics reflect actual DB records | Implemented | `HistoryController` — every number is a live `COUNT`/`AVG` query, no hardcoded stats |
| 14 | Personalized recommendations use real history | Implemented | `RecommendationService` reads `item_check_stats`, updated only on real `confirmDeparture` calls |
| 15 | Users cannot access another user's data | Implemented | Every model method filters by `user_id`; every AJAX endpoint re-verifies ownership before reading/writing |
| 16 | Works on mobile and desktop | Implemented, needs your visual check | Sidebar hides under 992px, floating bottom nav takes over; `responsive.css` |
| 17 | No nonfunctional buttons/forms | Believed complete — verify per page | See the page-by-page pass below |

## Page-by-page manual pass (do this before submitting)

For each page: load it, open the browser console (should show **no**
JS errors), and exercise every button/form:

- **Login / Register** — wrong password shows the generic "incorrect
  email or password" message (not "email not found" — that's
  intentional, it avoids leaking which emails are registered);
  duplicate email is rejected; a fresh registration lands you on the
  dashboard already logged in (session regenerated).
- **Dashboard** — readiness card, next activity, and history all show
  real numbers for your test account; bell badge count matches
  Notifications page unread count.
- **Activities** — each of the 6 cards reflects real item counts;
  "Set Up Checklist" vs "Start Check" switches correctly once a
  template has items.
- **Manage Template** — add/remove/re-prioritize items; try adding the
  same item twice (should be rejected).
- **My Items** — add, edit, delete (soft-delete — deleted items vanish
  from the list but a past checklist session that used them should
  still show the item name correctly, since it's a snapshot).
- **Quick Check** — check/uncheck items and confirm the percentage,
  critical/important/optional counts, and status message all update
  without a page reload; try **Confirm Departure** with a critical item
  still unchecked (should warn and require an explicit "Confirm
  Anyway"), then with everything checked (should succeed immediately).
- **Reminders** — schedule a departure a few minutes in the future,
  confirm 4 reminder rows appear; click "Process Due Reminders Now"
  before their time (should process 0) and after (should process them
  and they should show as "sent").
- **Notifications** — the reminders you just processed appear here with
  the actual unchecked item names in the message; Dismiss and "Mark all
  as read" both work without reload.
- **History** — filters (period / activity / status) narrow the session
  list correctly; a recommendation (after you've left something
  unchecked ≥2 times across confirmed sessions for one activity) shows
  up with working Accept/Dismiss.
- **Profile / Settings** — name update reflects in the sidebar/header
  immediately; wrong current password is rejected on password change;
  preference save round-trips (reload the page, values persist);
  account deletion requires the correct password and actually logs you
  out afterward.

## Known limitations (stated plainly, not hidden)

- No browser push notifications (Web Push/service worker) — by design,
  see `reminders.php` and `settings.php` for the explanation shown to
  the user. The in-app Notification Center is the reliable channel.
- No GPS, Bluetooth, RFID, or any physical-item detection of any kind.
  Every "checked" state in this system is the user's manual confirmation
  — CheckNGo cannot verify a bag actually contains anything.
- No AI/ML. `RecommendationService` is fixed-threshold rule matching on
  your own historical counts, stated as such in the UI.
- The readiness trend chart is a plain CSS bar chart, not a JS charting
  library, to keep the build dependency-light.
- Light mode is stored as a preference but not yet themed — the CSS
  variables exist in `glass-ui.css` for a follow-up if you want it.
- `cron/process-reminders.php` needs an actual cron entry / Task
  Scheduler job to run unattended; the manual button on `reminders.php`
  exists specifically so grading doesn't depend on that being configured.
