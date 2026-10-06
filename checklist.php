<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth-check.php';
require_once __DIR__ . '/models/Activity.php';
require_once __DIR__ . '/services/ChecklistService.php';

$userId = current_user_id();
$activityId = (int) ($_GET['activity_id'] ?? 0);

$activity = $activityId ? Activity::findById($activityId, $userId) : null;
if (!$activity) {
    header('Location: /checkngo/activities.php');
    exit;
}

try {
    $scheduleStmt = get_db()->prepare(
        'SELECT id FROM departure_schedules
         WHERE user_id = :uid AND activity_id = :aid AND status = "upcoming" AND departure_date = CURDATE()
         ORDER BY departure_time ASC LIMIT 1'
    );
    $scheduleStmt->execute(['uid' => $userId, 'aid' => $activityId]);
    $matchedSchedule = $scheduleStmt->fetch();
    $scheduleId = $matchedSchedule ? (int) $matchedSchedule['id'] : null;

    $sessionId = ChecklistService::startOrResume($userId, $activityId, $scheduleId);
} catch (RuntimeException $e) {
    header('Location: /checkngo/template.php?type=' . urlencode($activity['activity_type']) . '&ok=0&msg=' . urlencode($e->getMessage()));
    exit;
}

$session = ChecklistService::getSession($sessionId, $userId);
$items = ChecklistService::getItems($sessionId);
$readiness = ReadinessService::calculate(array_map(
    fn ($i) => ['priority' => $i['priority_snapshot'], 'is_checked' => $i['is_checked']],
    $items
));

$pageTitle = 'Quick Check';
$activeNav = 'quick';
include __DIR__ . '/includes/header.php';
?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>

<main class="main-content">
  <div class="d-flex align-items-center gap-2 mb-1">
    <a href="/checkngo/activities.php" class="text-secondary-glass"><i class="fa-solid fa-arrow-left"></i></a>
    <h1 class="h4 mb-0"><?= htmlspecialchars($activity['activity_name']) ?> — Quick Check</h1>
  </div>
  <p class="text-secondary-glass mb-4"><?= htmlspecialchars($activity['destination_label'] ?? '') ?></p>

  <div class="row g-3">
    <div class="col-lg-8">
      <div class="glass-card p-4" id="checklistCard" data-session-id="<?= $sessionId ?>">
        <?php
        $currentPriority = null;
        foreach ($items as $item):
          if ($item['priority_snapshot'] !== $currentPriority):
            $currentPriority = $item['priority_snapshot'];
        ?>
          <div class="text-muted-glass small text-uppercase mt-3 mb-1"><?= htmlspecialchars($currentPriority) ?></div>
        <?php endif; ?>
          <label class="check-item" style="cursor:pointer">
            <div class="d-flex align-items-center gap-3">
              <input type="checkbox" class="form-check-input item-checkbox"
                     data-item-id="<?= $item['id'] ?>"
                     <?= $item['is_checked'] ? 'checked' : '' ?>>
              <span class="<?= $item['is_checked'] ? 'text-muted-glass' : '' ?>" style="<?= $item['is_checked'] ? 'text-decoration:line-through' : '' ?>">
                <?= htmlspecialchars($item['item_name_snapshot']) ?>
              </span>
            </div>
            <span class="priority-badge <?= $item['priority_snapshot'] === 'critical' ? 'priority-critical' : '' ?>">
              <?= ucfirst($item['priority_snapshot']) ?>
            </span>
          </label>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="glass-card p-4 text-center mb-3">
        <div class="display-5 fw-bold" id="readinessPercent"><?= $readiness['percent'] ?>%</div>
        <div class="text-secondary-glass small mb-2">READINESS</div>
        <div id="readinessDetail" class="small text-start">
          <div>Critical: <span id="critCount"><?= $readiness['critical_checked'] ?>/<?= $readiness['critical_total'] ?></span></div>
          <div>Important: <span id="impCount"><?= $readiness['important_checked'] ?>/<?= $readiness['important_total'] ?></span></div>
          <div>Optional: <span id="optCount"><?= $readiness['optional_checked'] ?>/<?= $readiness['optional_total'] ?></span></div>
        </div>
        <div id="statusMessage" class="small mt-2 <?= $readiness['has_unchecked_critical'] ? '' : 'text-muted-glass' ?>"
             style="<?= $readiness['has_unchecked_critical'] ? 'color:var(--error)' : '' ?>">
          <?= htmlspecialchars($readiness['status_message']) ?>
        </div>
      </div>
      <button id="confirmDepartureBtn" class="btn btn-glossy w-100">Confirm Departure</button>
    </div>
  </div>
</main>

<script>
const SESSION_ID = <?= $sessionId ?>;
const CSRF_TOKEN = '<?= csrf_token() ?>';

document.querySelectorAll('.item-checkbox').forEach(cb => {
  cb.addEventListener('change', async (e) => {
    const itemId = e.target.dataset.itemId;
    const checked = e.target.checked;
    const label = e.target.closest('label').querySelector('span');
    label.style.textDecoration = checked ? 'line-through' : 'none';
    label.classList.toggle('text-muted-glass', checked);

    try {
      const res = await fetch('/checkngo/ajax/toggle-item.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({session_item_id: itemId, checked: checked ? '1' : '0', csrf_token: CSRF_TOKEN})
      });
      const data = await res.json();
      if (!data.success) { ckToast(data.error || 'Could not save.', 'error'); return; }

      const r = data.readiness;
      document.getElementById('readinessPercent').textContent = r.percent + '%';
      document.getElementById('critCount').textContent = r.critical_checked + '/' + r.critical_total;
      document.getElementById('impCount').textContent = r.important_checked + '/' + r.important_total;
      document.getElementById('optCount').textContent = r.optional_checked + '/' + r.optional_total;
      const statusEl = document.getElementById('statusMessage');
      statusEl.textContent = r.status_message;
      statusEl.style.color = r.has_unchecked_critical ? 'var(--error)' : '';
    } catch (err) {
      ckToast('Network error saving item.', 'error');
    }
  });
});

document.getElementById('confirmDepartureBtn').addEventListener('click', async () => {
  const attempt = async (force) => {
    const res = await fetch('/checkngo/ajax/confirm-departure.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: new URLSearchParams({session_id: SESSION_ID, force: force ? '1' : '0', csrf_token: CSRF_TOKEN})
    });
    return res.json();
  };

  const first = await attempt(false);
  if (first.success) {
    await Swal.fire({
      title: "You're All Set!",
      text: 'Departure confirmed. Have a great trip.',
      icon: 'success',
      background: '#242424', color: '#F8F8F8', confirmButtonColor: '#F8F8F8',
    });
    window.location.href = '/checkngo/dashboard.php';
    return;
  }

  // Critical items still unchecked — warn and offer to override.
  const proceed = await ckConfirm(
    'Critical item is still unchecked',
    'You still have unconfirmed critical items. Are you sure you want to confirm departure anyway?',
    'Confirm Anyway'
  );
  if (proceed) {
    const forced = await attempt(true);
    if (forced.success) {
      window.location.href = '/checkngo/dashboard.php';
    } else {
      ckToast(forced.error || 'Could not confirm departure.', 'error');
    }
  }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
