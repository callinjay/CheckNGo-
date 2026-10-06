<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth-check.php';
require_once __DIR__ . '/controllers/ReminderController.php';
require_once __DIR__ . '/models/Schedule.php';
require_once __DIR__ . '/models/Reminder.php';

$userId = current_user_id();
$feedback = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $feedback = ReminderController::handle($_POST, $userId);
}

$schedules = Schedule::listForUser($userId);
$pageTitle = 'Reminders';
$activeNav = 'reminders';
include __DIR__ . '/includes/header.php';
?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>

<main class="main-content">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0">Reminders</h1>
    <button class="btn btn-glossy" data-bs-toggle="modal" data-bs-target="#scheduleModal">
      <i class="fa-solid fa-plus"></i> Schedule Departure
    </button>
  </div>

  <?php if ($feedback && !$feedback['success']): ?>
    <div class="alert alert-secondary bg-transparent border-secondary text-white small">
      <?= htmlspecialchars($feedback['error']) ?>
    </div>
  <?php elseif ($feedback && $feedback['success']): ?>
    <div class="alert alert-secondary bg-transparent border-secondary text-white small">Saved.</div>
  <?php endif; ?>

  <?php if (empty($schedules)): ?>
    <div class="glass-card p-4">
      <p class="text-muted-glass small mb-0">No departure schedules yet. Schedule one to start receiving reminders.</p>
    </div>
  <?php endif; ?>

  <?php foreach ($schedules as $sched): ?>
    <?php $reminders = Reminder::listForSchedule((int) $sched['id']); ?>
    <div class="glass-card p-4 mb-3 fade-in-up">
      <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
          <div class="fw-semibold"><?= htmlspecialchars($sched['activity_name']) ?> — <?= htmlspecialchars($sched['destination_label'] ?? '') ?></div>
          <div class="text-secondary-glass small">
            <?= date('M j, Y', strtotime($sched['departure_date'])) ?> at <?= date('g:i A', strtotime($sched['departure_time'])) ?>
          </div>
        </div>
        <div class="d-flex align-items-center gap-2">
          <span class="priority-badge"><?= ucfirst($sched['status']) ?></span>
          <?php if ($sched['status'] === 'upcoming'): ?>
            <form method="post" onsubmit="return confirm('Cancel this schedule and its pending reminders?')">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="cancel_schedule">
              <input type="hidden" name="schedule_id" value="<?= $sched['id'] ?>">
              <button class="btn btn-outline-glass btn-sm" type="submit"><i class="fa-solid fa-ban"></i> Cancel</button>
            </form>
          <?php endif; ?>
        </div>
      </div>

      <div class="mt-3 d-flex flex-wrap gap-2">
        <?php foreach ($reminders as $r): ?>
          <span class="priority-badge">
            <i class="fa-regular fa-clock"></i>
            <?= ucfirst(str_replace('_', ' ', $r['reminder_type'])) ?> —
            <?= date('g:i A', strtotime($r['reminder_datetime'])) ?>
            (<?= htmlspecialchars($r['status']) ?>)
          </span>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>

  <div class="glass-card p-4 mt-4">
    <h2 class="h6 mb-2">Local testing note</h2>
    <p class="text-muted-glass small mb-2">
      PHP cannot push notifications while no page is open. On a real server this runs via
      <code>cron</code>; on XAMPP, run
      <code>php cron/process-reminders.php</code> from a terminal (or point Windows Task
      Scheduler at it every minute) to turn due reminders into notifications. See the README
      for exact steps.
    </p>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="process_now">
      <button type="submit" class="btn btn-outline-glass btn-sm">
        <i class="fa-solid fa-play"></i> Process Due Reminders Now
      </button>
    </form>
    <?php if ($feedback && isset($feedback['processed'])): ?>
      <p class="small text-secondary-glass mt-2 mb-0">
        Processed <?= $feedback['processed'] ?> reminder(s), <?= $feedback['failed'] ?> failed.
        Check <a href="/checkngo/notifications.php" class="text-white text-decoration-underline">Notifications</a>.
      </p>
    <?php endif; ?>
  </div>
</main>

<!-- Schedule modal -->
<div class="modal fade" id="scheduleModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content" style="background:var(--bg-secondary);color:var(--pure-white);border:1px solid var(--border);border-radius:var(--radius-lg);">
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create_schedule">
        <div class="modal-header border-secondary">
          <h5 class="modal-title">Schedule Departure</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label small text-secondary-glass">Activity</label>
            <select name="activity_type" class="form-select" required>
              <?php foreach (Activity::CATEGORIES as $type => [$label]): ?>
                <option value="<?= $type ?>"><?= htmlspecialchars($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="row g-2 mb-3">
            <div class="col-6">
              <label class="form-label small text-secondary-glass">Date</label>
              <input type="date" name="departure_date" class="form-control" required min="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-6">
              <label class="form-label small text-secondary-glass">Time</label>
              <input type="time" name="departure_time" class="form-control" required>
            </div>
          </div>

          <label class="form-label small text-secondary-glass">Reminders</label>
          <?php
          $reminderRows = [
              'preparation' => ['Preparation reminder', 30],
              'checklist'   => ['Checklist reminder', 15],
              'final_check' => ['Final check', 5],
              'departure'   => ['Departure time', 0],
          ];
          foreach ($reminderRows as $type => [$label, $default]):
          ?>
            <div class="d-flex align-items-center gap-2 mb-2">
              <input type="checkbox" class="form-check-input" name="reminder_<?= $type ?>" id="r_<?= $type ?>" checked>
              <label class="form-check-label small flex-fill" for="r_<?= $type ?>"><?= $label ?></label>
              <input type="number" min="0" max="180" name="offset_<?= $type ?>" value="<?= $default ?>"
                     class="form-control form-control-sm" style="width:80px" <?= $type === 'departure' ? 'readonly' : '' ?>>
              <span class="text-muted-glass small">min before</span>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="modal-footer border-secondary">
          <button type="submit" class="btn btn-glossy">Save Schedule</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
