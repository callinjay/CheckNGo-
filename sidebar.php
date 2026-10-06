<?php
/** includes/sidebar.php — expects $activeNav to be set by the including page. */
$activeNav = $activeNav ?? '';
$links = [
    'dashboard'  => ['Dashboard',  'fa-house',        '/checkngo/dashboard.php'],
    'quick'      => ['Quick Check','fa-square-check', '/checkngo/checklist.php'],
    'activities' => ['Activities', 'fa-layer-group',  '/checkngo/activities.php'],
    'items'      => ['My Items',   'fa-box-archive',  '/checkngo/my-items.php'],
    'reminders'  => ['Reminders',  'fa-bell',         '/checkngo/reminders.php'],
    'notifications' => ['Notifications', 'fa-envelope', '/checkngo/notifications.php'],
    'history'    => ['History',    'fa-clock-rotate-left', '/checkngo/history.php'],
    'insights'   => ['Insights',   'fa-chart-simple', '/checkngo/history.php#insights'],
    'profile'    => ['Profile',    'fa-user',         '/checkngo/profile.php'],
    'settings'   => ['Settings',   'fa-gear',         '/checkngo/settings.php'],
];
?>
<aside class="sidebar d-flex flex-column justify-content-between">
  <div>
    <div class="d-flex align-items-center gap-2 mb-4 px-1">
      <i class="fa-solid fa-check-double" style="color:var(--glossy-white)"></i>
      <strong>CheckNGo</strong>
    </div>
    <nav>
      <?php foreach ($links as $key => [$label, $icon, $href]): ?>
        <a href="<?= $href ?>" class="nav-link <?= $activeNav === $key ? 'active' : '' ?>">
          <i class="fa-solid <?= $icon ?>"></i> <span><?= $label ?></span>
        </a>
      <?php endforeach; ?>
    </nav>
  </div>
  <a href="/checkngo/profile.php" class="nav-link">
    <i class="fa-solid fa-circle-user"></i>
    <span><?= htmlspecialchars($_SESSION['user_name'] ?? 'Account') ?></span>
  </a>
</aside>

<nav class="bottom-nav">
  <?php foreach (['dashboard','quick','items','reminders','history','profile'] as $key): ?>
    <?php [$label, $icon, $href] = $links[$key]; ?>
    <a href="<?= $href ?>" class="<?= $activeNav === $key ? 'active' : '' ?>">
      <i class="fa-solid <?= $icon ?>"></i>
    </a>
  <?php endforeach; ?>
</nav>
