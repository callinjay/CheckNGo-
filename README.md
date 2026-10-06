# CheckNGo — Phase 1 (Foundation)

Context-aware personal belongings pre-departure readiness system.
**"Before you go, CheckNGo."**

This is **Phase 1** of the build: project structure, database schema,
authentication, and the Dark Gray + Glossy White Liquid Glass design
system, matching your uploaded UI reference. Checklist generation,
reminders, history/analytics, and personalization land in the next phases.

## What's included in this phase

- Full folder structure (`config/`, `controllers/`, `models/`, `services/`,
  `includes/`, `assets/`, `database/`, `cron/`)
- `database/checkngo_db.sql` — normalized schema for every table described
  in the spec, with FK constraints, indexes, and clearly-labeled demo data
- Secure authentication: registration, login, logout
  - `password_hash()` / `password_verify()`
  - PDO prepared statements only (no string-concatenated SQL anywhere)
  - Session regeneration on login/register
  - CSRF token on every POST form (`includes/csrf.php`)
- `services/ReadinessService.php` — the weighted readiness calculation,
  documented and used consistently (this is the one place the formula lives)
- Dashboard wired to **real** queries (`controllers/DashboardController.php`)
  — readiness card, next departure, frequently-unchecked items, and recent
  history all read from MySQL. There is no fake/hardcoded data.
- Liquid Glass design system (`assets/css/glass-ui.css`) implementing the
  exact palette from your reference: `#171717` / `#242424` / `#2B2B2B`
  backgrounds, translucent glass panels, glossy white buttons, no red or
  colorful gradients.

## Setup (XAMPP / local)

1. Copy the `checkngo/` folder into `C:\xampp\htdocs\` (or `/opt/lampp/htdocs/`
   on Linux), so the app is reachable at `http://localhost/checkngo/`.
2. Start **Apache** and **MySQL** in the XAMPP control panel.
3. Open phpMyAdmin (`http://localhost/phpmyadmin`), create nothing manually —
   just go to **Import**, choose `database/checkngo_db.sql`, and run it.
   This creates the `checkngo_db` database and all tables.
4. Check `config/database.php` — the defaults (`root` / empty password,
   `127.0.0.1`) match a stock XAMPP install. Change them if yours differs.
5. Visit `http://localhost/checkngo/` — you'll land on the login page.

### Demo account

A demo user is seeded (`alex@checkngo.test`). **Important:** the seeded
password hash in the SQL file is a placeholder for illustration. Before
first login, regenerate it:

```php
<?php
echo password_hash('Password123!', PASSWORD_DEFAULT);
```

Run that with `php -r "echo password_hash('Password123!', PASSWORD_DEFAULT);"`
and `UPDATE users SET password_hash = '<paste-here>' WHERE email = 'alex@checkngo.test';`
in phpMyAdmin. Or simpler: just register a fresh account through the UI —
registration is fully functional in this phase.

## Readiness calculation (documented)

```
weight(critical) = 5, weight(important) = 3, weight(optional) = 1
Readiness % = (Σ weight of CHECKED items / Σ weight of ALL items) × 100
```

Implemented once in `services/ReadinessService.php` and called everywhere
a readiness number is shown, so the dashboard, quick-check page, and
history will always agree.

## Phase 2 — Activities, Templates, My Items, Quick Check

Added on top of Phase 1:

- **`activities.php`** — glass cards for the six built-in categories
  (School, Work, Travel, Personal, Examination, Laboratory). Each user's
  `activities` row is created lazily on first visit (`Activity::getOrCreateByType`).
  Shows saved item count per category, pulled from the real template.
- **`template.php`** — checklist template management (spec section F).
  Add belongings from "My Items" into an activity's default template,
  set each item's priority (critical/important/optional), and remove
  items. Duplicate items in the same template are rejected server-side.
- **`my-items.php`** — full CRUD for personal belongings: add, edit,
  delete (soft delete — `is_active = 0`, so past checklist snapshots are
  never affected), search, and category filter.
- **`checklist.php`** (Quick Check) — generates a session from the
  activity's template (`ChecklistService::startOrResume`), snapshotting
  each item's name/priority into `checklist_session_items` so later edits
  to belongings/templates don't rewrite history. Checkboxes save
  instantly via `ajax/toggle-item.php` (no page reload), and the
  readiness card updates live from the same `ReadinessService` used on
  the dashboard. **Confirm Departure** (`ajax/confirm-departure.php`)
  blocks on unchecked critical items with a SweetAlert warning, and asks
  for explicit override before allowing departure with `is_required`
  critical items unconfirmed — matching the spec's missing-item behavior.
- Departure confirmation also increments `item_check_stats` per item
  (checked vs. unchecked), which is the raw data the dashboard's
  "Smart Priority" / frequently-unchecked panel already reads from —
  so personalization starts working the moment you complete your first
  real checklist.

All new endpoints re-verify row ownership (`user_id = :uid`) before any
read or write — a user can never toggle or view another user's session
items, templates, or belongings, even by guessing IDs in the URL.

## Phase 3 — Departure Scheduling, Reminders, Notification Center

Added on top of Phases 1–2:

- **`reminders.php`** — schedule a departure (activity, date, time) against
  an activity that already has a checklist template. On save,
  `ReminderService::generateForSchedule()` writes four `reminders` rows
  (preparation / checklist / final_check / departure), each timestamped
  as an offset in minutes before departure — matching the spec's example
  (6:30 / 6:45 / 6:55 / 7:00 for a 7:00 AM departure). Schedules can be
  cancelled, which also cancels their still-pending reminders.
- **`services/ReminderService.php`** — the smart reminder engine.
  `processDueReminders()` finds reminders whose time has arrived, looks
  at the linked checklist (a live session if one's been started, else the
  template) to name unchecked required items, and writes a `notifications`
  row. Each reminder is locked (`SELECT ... FOR UPDATE`) and marked `sent`
  inside the same transaction that creates its notification, so re-running
  the processor never double-sends.
- **`cron/process-reminders.php`** — the actual script to schedule via
  cron or Windows Task Scheduler (every minute is reasonable). It's a
  thin CLI wrapper around `ReminderService::processDueReminders()`.
  **Important, and stated plainly in the UI too:** PHP cannot push a
  notification to a closed browser tab on its own — this script only
  writes rows to `notifications`; the person sees them the next time they
  open the app. Reminders.php also has a **"Process Due Reminders Now"**
  button so you can demo/test this without setting up a real cron job.
- **`notifications.php`** — the in-app notification center: lists
  notifications newest-first, "Check Now" / "Dismiss" actions, mark-all-read.
  The dashboard bell icon now shows a live unread count and links here.

### Honest limitation, stated deliberately

There is **no browser push notification** implementation in this build
(no service worker, no Web Push subscription flow) — the spec allows this
as long as it's not misrepresented and no paid API is required. This
build is in-app-first: reminders always land in the Notification Center,
which is reliable and requires nothing external. If you want real browser
push later, that's an additive Phase (Notification API + service worker),
not a rewrite of anything here.

### A note on the "Process Now" button and multi-user data

For local grading/demo convenience, "Process Due Reminders Now" runs
`ReminderService::processDueReminders()` system-wide (all users' due
reminders, same as the cron script would). In a real multi-tenant
deployment you'd remove this button and rely solely on the scheduled
CLI job — leaving it callable from a logged-in user's browser is a
deliberate simplification for a single-tester capstone demo, not a
pattern to keep in production.

## Phase 4 — History & Analytics, Personalized Recommendations

Added on top of Phases 1–3:

- **New table: `recommendation_actions`** (in `database/checkngo_db.sql`
  for fresh installs; `database/migration_phase4_recommendation_actions.sql`
  if you already imported an earlier phase's schema). Records whether the
  user accepted or dismissed a "you keep forgetting this" suggestion, so
  it only ever surfaces once per item/activity.
- **`services/RecommendationService.php`** — explicitly rule-based, not
  AI/ML (the spec is strict about this distinction). A belonging becomes
  a suggestion once `item_check_stats.unchecked_count >= 2` for that
  activity and no prior accept/dismiss exists. **Accept** bumps its
  priority to `critical` in that activity's default template (so future
  checklists treat it as required); **Dismiss** just records the choice
  and hides it. Both actions are one click, AJAX, no page reload
  (`ajax/recommendation-action.php`).
- **`controllers/HistoryController.php`** + **`history.php`** — the full
  History & Analytics page: total/completed/incomplete session counts
  and average readiness (all computed live via SQL aggregates — nothing
  hardcoded), a most-frequently-completed-activity callout, a simple bar
  visualization of the last 10 completed sessions' readiness scores, and
  a filterable session list (Today / This Week / This Month / activity
  category / completed / incomplete), matching the spec's filter list.
  The frequently-unchecked panel here uses the same `item_check_stats`
  data as the dashboard's Smart Priority widget, and the dashboard now
  links to History for the full accept/dismiss controls.

## Phase 5 — Profile, Settings, and Final Pass (Project Complete)

Added on top of Phases 1–4:

- **`profile.php`** — view account info and session stats (total /
  completed / average readiness, pulled from `HistoryController`), edit
  full name, and change password (requires correct current password,
  re-hashed with `password_hash()`).
- **`settings.php`** — default reminder intervals (used as the starting
  point on the "Schedule Departure" form), notification channel
  preferences (in-app always-on; a real `Notification.requestPermission()`
  browser-permission button, with an honest note that a closed tab still
  won't receive anything — no dishonest promises), an appearance
  preference (dark is fully themed; light is stored but not yet styled,
  said plainly rather than faked), and a **Danger Zone** to permanently
  delete the account. Deletion requires re-entering the password and
  relies on the schema's `ON DELETE CASCADE` foreign keys — one `DELETE
  FROM users` removes every belonging, activity, template, session,
  schedule, reminder, and notification that pointed at that user.
- **`models/Preference.php`**, **`controllers/ProfileController.php`**,
  **`controllers/SettingsController.php`** — the data access and logic
  behind both pages, following the same ownership-checked pattern as
  every other controller in the project.
- **`TESTING.md`** — the spec's 17-point testing checklist, mapped to
  where each behavior lives in the code, plus a page-by-page manual pass
  to run on your own XAMPP install before a defense or submission, and a
  final, honest list of what this build does **not** do (no push
  notifications, no physical-item detection, no AI/ML, no light theme
  yet) so nothing is overclaimed.

This closes out all five phases from the original plan. Every page listed
in the spec's "Required Application Pages" section now exists and is
wired to real MySQL data — see `TESTING.md` for the verification pass.

## Known limitations (see `TESTING.md` for the full, itemized list)

- No browser push notifications — in-app Notification Center is the
  reliable channel by design (stated to the user, not hidden).
- No physical-item detection of any kind (no GPS/Bluetooth/AI) — every
  "checked" state is a manual confirmation.
- Light theme is stored as a preference but not yet visually implemented.
- City images referenced in `style.css` (`assets/images/city-login.jpg`,
  `city-dashboard.jpg`) are not included — drop your own royalty-free
  photos in `assets/images/` with those filenames, or the CSS gradient
  fallback renders instead (no broken image icons).
- Built and reviewed without a live PHP/MySQL runtime in this
  environment — the code is logically traced and consistent, but you
  should run the `TESTING.md` pass yourself before submitting.

## Architecture

MVC-inspired: `controllers/` orchestrate requests and call into `models/`
(pure data access, one class per table-ish) and `services/` (business
logic like readiness scoring, checklist generation, reminder timing, and
rule-based recommendations — kept separate from models so the logic is
unit-testable independent of the database). Views are the top-level
`.php` files, sharing layout through `includes/`. `ajax/` holds small
JSON endpoints for interactions that shouldn't reload the page. `cron/`
holds the one CLI-only script.

## Project status: all 5 phases complete

1. Foundation (auth, schema, design system) ✅
2. Activities, templates, My Items, Quick Check ✅
3. Departure scheduling, reminders, notification center ✅
4. History & analytics, personalized recommendations ✅
5. Profile, settings, final pass & testing checklist ✅
