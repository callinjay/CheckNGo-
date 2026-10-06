<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth-check.php';
require_once __DIR__ . '/models/Activity.php';
require_once __DIR__ . '/models/Checklist.php';

$userId = current_user_id();
$existing = Activity::listForUser($userId);

$cards = [];
foreach (Activity::CATEGORIES as $type => [$label, $icon, $defaultDest, $description]) {
    $activityRow = $existing[$type] ?? null;
    $itemCount = 0;
    $activityId = null;

    if ($activityRow) {
        $activityId = (int) $activityRow['id'];
        $template = Checklist::getOrCreateDefaultTemplate($userId, $activityId);
        $itemCount = Checklist::itemCount((int) $template['id']);
        $destination = $activityRow['destination_label'];
    } else {
        $destination = $defaultDest;
    }

    $cards[] = compact('type', 'label', 'icon', 'description', 'itemCount', 'activityId', 'destination');
}

$pageTitle = 'Activities';
$activeNav = 'activities';
include __DIR__ . '/includes/header.php';
?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>

<main class="main-content">
  <h1 class="h4 mb-1">Activities</h1>
  <p class="text-secondary-glass mb-4">Pick an activity to generate its checklist, or manage its template.</p>

  <div class="row g-3">
    <?php foreach ($cards as $c): ?>
      <div class="col-md-6 col-lg-4">
        <div class="glass-card p-4 h-100 fade-in-up">
          <i class="fa-solid <?= $c['icon'] ?> fa-lg mb-2" style="color:var(--glossy-white)"></i>
          <h2 class="h6 mb-1"><?= htmlspecialchars($c['label']) ?></h2>
          <p class="text-muted-glass small mb-2"><?= htmlspecialchars($c['description']) ?></p>
          <p class="text-secondary-glass small mb-3">
            <i class="fa-regular fa-location-dot"></i> <?= htmlspecialchars($c['destination']) ?>
            &nbsp;•&nbsp; <?= $c['itemCount'] ?> saved item<?= $c['itemCount'] === 1 ? '' : 's' ?>
          </p>
          <div class="d-flex gap-2">
            <?php if ($c['activityId'] && $c['itemCount'] > 0): ?>
              <a href="/checkngo/checklist.php?activity_id=<?= $c['activityId'] ?>" class="btn btn-glossy btn-sm flex-fill">Start Check</a>
              <a href="/checkngo/template.php?type=<?= $c['type'] ?>" class="btn btn-outline-glass btn-sm">Manage</a>
            <?php else: ?>
              <a href="/checkngo/template.php?type=<?= $c['type'] ?>" class="btn btn-outline-glass btn-sm flex-fill">Set Up Checklist</a>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
