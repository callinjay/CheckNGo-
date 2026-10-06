<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth-check.php';
require_once __DIR__ . '/controllers/HistoryController.php';
require_once __DIR__ . '/services/RecommendationService.php';
require_once __DIR__ . '/models/Activity.php';

$userId = current_user_id();

$filters = [
    'period' => (string) ($_GET['period'] ?? ''),
    'activity_type' => (string) ($_GET['activity_type'] ?? ''),
    'status' => (string) ($_GET['status'] ?? ''),
];

$sessions = HistoryController::listSessions($userId, $filters);
$stats = HistoryController::getSummaryStats($userId);
$mostFrequent = HistoryController::getMostFrequentActivity($userId);
$frequentlyUnchecked = HistoryController::getFrequentlyUnchecked($userId);
$trend = HistoryController::getReadinessTrend($userId);
$weekly = HistoryController::getWeeklySummary($userId);
$recommendations = RecommendationService::getActiveRecommendations($userId);

$pageTitle = 'History & Analytics';
$activeNav = 'history';
include __DIR__ . '/includes/header.php';
?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>

<main class="main-content" id="insights">
  <h1 class="h4 mb-4">History &amp; Analytics</h1>

  <!-- Summary stat cards -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="glass-card p-3 text-center"><div class="h4 mb-0"><?= $stats['total'] ?></div><div class="text-muted-glass small">Total Sessions</div></div>
    </div>
    <div class="col-6 col-md-3">
      <div class="glass-card p-3 text-center"><div class="h4 mb-0"><?= $stats['completed'] ?></div><div class="text-muted-glass small">Completed</div></div>
    </div>
    <div class="col-6 col-md-3">
      <div class="glass-card p-3 text-center"><div class="h4 mb-0"><?= $stats['incomplete'] ?></div><div class="text-muted-glass small">Incomplete</div></div>
    </div>
    <div class="col-6 col-md-3">
      <div class="glass-card p-3 text-center"><div class="h4 mb-0"><?= $stats['average_readiness'] ?>%</div><div class="text-muted-glass small">Avg Readiness</div></div>
    </div>
  </div>

  <div class="row g-3 mb-4">
    <!-- Personalized recommendations -->
    <div class="col-lg-6">
      <div class="glass-card p-4 h-100">
        <h2 class="h6 mb-3"><i class="fa-solid fa-lightbulb"></i> Personalized Suggestions</h2>
        <p class="text-muted-glass small">Rule-based, from your own history — not AI. Based on items you've repeatedly left unchecked.</p>
        <div id="recommendationList">
          <?php if (empty($recommendations)): ?>
            <p class="text-muted-glass small mb-0">No suggestions right now — keep completing checklists and CheckNGo will learn your patterns.</p>
          <?php endif; ?>
          <?php foreach ($recommendations as $rec): ?>
            <div class="check-item" data-activity-id="<?= $rec['activity_id'] ?>" data-belonging-id="<?= $rec['belonging_id'] ?>">
              <div>
                <div class="fw-semibold"><?= htmlspecialchars($rec['item_name']) ?></div>
                <div class="text-muted-glass small">
                  Left unchecked <?= (int) $rec['unchecked_count'] ?> times during <?= htmlspecialchars($rec['activity_name']) ?> checklists.
                  Prioritize it for next time?
                </div>
              </div>
              <div class="d-flex gap-2">
                <button class="btn btn-glossy btn-sm rec-accept">Accept</button>
                <button class="btn btn-outline-glass btn-sm rec-dismiss">Dismiss</button>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- Frequently unchecked -->
    <div class="col-lg-6">
      <div class="glass-card p-4 h-100">
        <h2 class="h6 mb-3"><i class="fa-solid fa-chart-simple"></i> Frequently Unchecked</h2>
        <?php if (empty($frequentlyUnchecked)): ?>
          <p class="text-muted-glass small mb-0">No patterns yet.</p>
        <?php endif; ?>
        <?php foreach ($frequentlyUnchecked as $fu): ?>
          <div class="check-item">
            <div>
              <div class="fw-semibold"><?= htmlspecialchars($fu['item_name']) ?></div>
              <div class="text-muted-glass small"><?= htmlspecialchars($fu['activity_name']) ?></div>
            </div>
            <span class="priority-badge"><?= (int) $fu['unchecked_count'] ?>× missed</span>
          </div>
        <?php endforeach; ?>
        <?php if ($mostFrequent): ?>
          <div class="mt-3 pt-3 border-top" style="border-color:var(--border) !important;">
            <span class="text-muted-glass small">Most completed activity:</span>
            <span class="fw-semibold"><?= htmlspecialchars($mostFrequent['activity_name']) ?></span>
            <span class="text-muted-glass small">(<?= (int) $mostFrequent['c'] ?> sessions)</span>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Readiness trend -->
  <?php if (!empty($trend)): ?>
    <div class="glass-card p-4 mb-4">
      <h2 class="h6 mb-3">Readiness Trend (last <?= count($trend) ?> completed sessions)</h2>
      <div class="d-flex align-items-end gap-2" style="height:120px;">
        <?php foreach ($trend as $t): ?>
          <?php $h = max(6, (float) $t['readiness_score']); ?>
          <div class="text-center flex-fill">
            <div style="height:<?= $h ?>px; background:var(--glass-strong); border-radius:6px 6px 0 0; margin:0 auto; width:60%;"
                 title="<?= (float) $t['readiness_score'] ?>%"></div>
            <div class="text-muted-glass" style="font-size:0.65rem;"><?= date('n/j', strtotime($t['session_date'])) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <!-- Filters -->
  <form method="get" class="row g-2 mb-3">
    <div class="col-md-3">
      <select name="period" class="form-select" onchange="this.form.submit()">
        <option value="">All Time</option>
        <option value="today" <?= $filters['period'] === 'today' ? 'selected' : '' ?>>Today</option>
        <option value="week" <?= $filters['period'] === 'week' ? 'selected' : '' ?>>This Week</option>
        <option value="month" <?= $filters['period'] === 'month' ? 'selected' : '' ?>>This Month</option>
      </select>
    </div>
    <div class="col-md-3">
      <select name="activity_type" class="form-select" onchange="this.form.submit()">
        <option value="">All Activities</option>
        <?php foreach (Activity::CATEGORIES as $type => [$label]): ?>
          <option value="<?= $type ?>" <?= $filters['activity_type'] === $type ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3">
      <select name="status" class="form-select" onchange="this.form.submit()">
        <option value="">Completed &amp; Incomplete</option>
        <option value="completed" <?= $filters['status'] === 'completed' ? 'selected' : '' ?>>Completed Only</option>
        <option value="incomplete" <?= $filters['status'] === 'incomplete' ? 'selected' : '' ?>>Incomplete Only</option>
      </select>
    </div>
  </form>

  <!-- Session list -->
  <div class="glass-card p-4">
    <?php if (empty($sessions)): ?>
      <p class="text-muted-glass small mb-0">No sessions match these filters.</p>
    <?php else: ?>
      <?php foreach ($sessions as $s): ?>
        <div class="check-item">
          <div>
            <div class="fw-semibold"><?= htmlspecialchars($s['activity_name']) ?></div>
            <div class="text-muted-glass small">
              <?= date('M j, Y', strtotime($s['session_date'])) ?>
              <?php if ($s['completion_time']): ?> • completed <?= date('g:i A', strtotime($s['completion_time'])) ?><?php endif; ?>
            </div>
          </div>
          <div class="text-end">
            <div class="fw-semibold"><?= number_format((float) $s['readiness_score'], 0) ?>%</div>
            <span class="priority-badge"><?= htmlspecialchars($s['session_status']) ?></span>
            <?php if ($s['departure_confirmed']): ?><span class="priority-badge">departed</span><?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</main>

<script>
const CSRF_TOKEN = '<?= csrf_token() ?>';

async function sendDecision(row, decision) {
  const res = await fetch('/checkngo/ajax/recommendation-action.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: new URLSearchParams({
      activity_id: row.dataset.activityId,
      belonging_id: row.dataset.belongingId,
      decision, csrf_token: CSRF_TOKEN
    })
  });
  const data = await res.json();
  if (data.success) {
    row.style.opacity = '0';
    setTimeout(() => row.remove(), 200);
    ckToast(decision === 'accept' ? 'Marked as critical for next time.' : 'Suggestion dismissed.');
  } else {
    ckToast('Could not save your choice.', 'error');
  }
}

document.querySelectorAll('.rec-accept').forEach(btn => {
  btn.addEventListener('click', (e) => sendDecision(e.target.closest('[data-activity-id]'), 'accept'));
});
document.querySelectorAll('.rec-dismiss').forEach(btn => {
  btn.addEventListener('click', (e) => sendDecision(e.target.closest('[data-activity-id]'), 'dismiss'));
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
