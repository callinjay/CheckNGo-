<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth-check.php';
require_once __DIR__ . '/controllers/DashboardController.php';
require_once __DIR__ . '/models/Notification.php';

$userId = current_user_id();
$unreadCount = Notification::unreadCount($userId);

$activeSession = DashboardController::getActiveSession($userId);
$readiness = ['percent' => 0, 'critical_total' => 0, 'critical_checked' => 0,
              'important_total' => 0, 'important_checked' => 0,
              'optional_total' => 0, 'optional_checked' => 0,
              'has_unchecked_critical' => false, 'status_message' => 'No active checklist yet.'];

if ($activeSession) {
    $items = DashboardController::getSessionItems((int) $activeSession['id']);
    $readiness = ReadinessService::calculate($items);
}

$nextDeparture = DashboardController::getNextDeparture($userId);
$frequentlyUnchecked = DashboardController::getFrequentlyUnchecked($userId);
$recentHistory = DashboardController::getRecentHistory($userId);

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
include __DIR__ . '/includes/header.php';
?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>

<main class="main-content">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0">Dashboard</h1>
    <div class="d-flex align-items-center gap-3">
      <a href="/checkngo/notifications.php" class="position-relative text-white">
        <i class="fa-regular fa-bell"></i>
        <?php if ($unreadCount > 0): ?>
          <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill" style="background:var(--glossy-white);color:#171717;font-size:0.6rem;">
            <?= $unreadCount > 9 ? '9+' : $unreadCount ?>
          </span>
        <?php endif; ?>
      </a>
      <span class="text-secondary-glass small"><?= htmlspecialchars($_SESSION['user_name']) ?></span>
    </div>
  </div>

  <div class="city-hero dashboard-hero fade-in-up mb-4 p-4">
    <h2 class="h3">Good morning, <?= htmlspecialchars(explode(' ', $_SESSION['user_name'])[0]) ?></h2>
    <p class="text-secondary-glass">Ready for your next activity?</p>

    <div class="row g-3 mt-2">
      <div class="col-md-4">
        <div class="glass-panel p-3 h-100">
          <div class="display-6 fw-bold"><?= $readiness['percent'] ?>%</div>
          <div class="text-secondary-glass small">READINESS</div>
          <div class="small mt-2">Critical <?= $readiness['critical_checked'] ?>/<?= $readiness['critical_total'] ?></div>
          <div class="small">Important <?= $readiness['important_checked'] ?>/<?= $readiness['important_total'] ?></div>
          <div class="small">Optional <?= $readiness['optional_checked'] ?>/<?= $readiness['optional_total'] ?></div>
          <?php if ($readiness['has_unchecked_critical']): ?>
            <div class="small mt-2" style="color:var(--error)">
              <i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($readiness['status_message']) ?>
            </div>
          <?php endif; ?>
          <a href="/checkngo/checklist.php" class="btn btn-glossy w-100 mt-3">Start Check</a>
        </div>
      </div>

      <div class="col-md-4">
        <div class="glass-panel p-3 h-100">
          <div class="fw-semibold mb-2"><i class="fa-solid fa-bolt"></i> Smart Priority</div>
          <?php if ($frequentlyUnchecked): ?>
            <?php foreach ($frequentlyUnchecked as $fu): ?>
              <div class="mb-2">
                <div class="fw-semibold"><?= htmlspecialchars($fu['item_name']) ?></div>
                <div class="text-muted-glass small">Missed <?= (int) $fu['unchecked_count'] ?> times before</div>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <p class="text-muted-glass small">No patterns yet — keep checking in and CheckNGo will learn what you tend to forget.</p>
          <?php endif; ?>
          <a href="/checkngo/history.php#insights" class="small text-secondary-glass text-decoration-underline">Manage suggestions</a>
        </div>
      </div>

      <div class="col-md-4">
        <div class="glass-panel p-3 h-100">
          <div class="fw-semibold mb-2">Next Activity</div>
          <?php if ($nextDeparture): ?>
            <div class="h5"><?= htmlspecialchars($nextDeparture['activity_name']) ?></div>
            <div class="text-secondary-glass small"><?= htmlspecialchars($nextDeparture['destination_label'] ?? '') ?></div>
            <div class="display-6 fw-bold mt-2"><?= date('g:i A', strtotime($nextDeparture['departure_time'])) ?></div>
            <a href="/checkngo/checklist.php" class="btn btn-outline-glass w-100 mt-3">Open Activity</a>
          <?php else: ?>
            <p class="text-muted-glass small">No upcoming departures scheduled.</p>
            <a href="/checkngo/reminders.php" class="btn btn-outline-glass w-100">Schedule One</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <div class="glass-card p-4 fade-in-up">
    <h2 class="h6 mb-3">Recent Checklist History</h2>
    <?php if (empty($recentHistory)): ?>
      <p class="text-muted-glass small mb-0">No sessions yet. Start your first check above.</p>
    <?php else: ?>
      <?php foreach ($recentHistory as $h): ?>
        <div class="check-item">
          <div>
            <div class="fw-semibold"><?= htmlspecialchars($h['activity_name']) ?></div>
            <div class="text-muted-glass small"><?= htmlspecialchars($h['session_date']) ?></div>
          </div>
          <div class="text-end">
            <div><?= number_format((float) $h['readiness_score'], 0) ?>%</div>
            <span class="priority-badge"><?= htmlspecialchars($h['session_status']) ?></span>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
