<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth-check.php';
require_once __DIR__ . '/controllers/ProfileController.php';
require_once __DIR__ . '/controllers/HistoryController.php';

$userId = current_user_id();
$feedback = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $feedback = ProfileController::handle($_POST, $userId);
}

$user = User::findById($userId);
$stats = HistoryController::getSummaryStats($userId);

$pageTitle = 'Profile';
$activeNav = 'profile';
include __DIR__ . '/includes/header.php';
?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>

<main class="main-content">
  <h1 class="h4 mb-4">Profile</h1>

  <?php if ($feedback): ?>
    <div class="alert alert-secondary bg-transparent border-secondary text-white small">
      <?= $feedback['success'] ? 'Saved.' : htmlspecialchars($feedback['error']) ?>
    </div>
  <?php endif; ?>

  <div class="row g-3">
    <div class="col-lg-4">
      <div class="glass-card p-4 text-center">
        <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-3"
             style="width:84px;height:84px;background:var(--glass-strong);">
          <i class="fa-solid fa-user fa-2x"></i>
        </div>
        <div class="fw-semibold"><?= htmlspecialchars($user['full_name']) ?></div>
        <div class="text-muted-glass small mb-3"><?= htmlspecialchars($user['email']) ?></div>
        <div class="row text-center g-2">
          <div class="col-4"><div class="fw-semibold"><?= $stats['total'] ?></div><div class="text-muted-glass" style="font-size:0.7rem;">Sessions</div></div>
          <div class="col-4"><div class="fw-semibold"><?= $stats['completed'] ?></div><div class="text-muted-glass" style="font-size:0.7rem;">Completed</div></div>
          <div class="col-4"><div class="fw-semibold"><?= $stats['average_readiness'] ?>%</div><div class="text-muted-glass" style="font-size:0.7rem;">Avg Ready</div></div>
        </div>
      </div>
    </div>

    <div class="col-lg-8">
      <div class="glass-card p-4 mb-3">
        <h2 class="h6 mb-3">Edit Profile</h2>
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="update_profile">
          <div class="mb-3">
            <label class="form-label small text-secondary-glass">Full Name</label>
            <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($user['full_name']) ?>" required>
          </div>
          <div class="mb-3">
            <label class="form-label small text-secondary-glass">Email</label>
            <input type="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" disabled>
            <div class="form-text text-muted-glass">Email changes aren't supported in this build.</div>
          </div>
          <button type="submit" class="btn btn-glossy">Save Changes</button>
        </form>
      </div>

      <div class="glass-card p-4 mb-3">
        <h2 class="h6 mb-3">Change Password</h2>
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="change_password">
          <div class="mb-3">
            <label class="form-label small text-secondary-glass">Current Password</label>
            <input type="password" name="current_password" class="form-control" required>
          </div>
          <div class="row g-2 mb-3">
            <div class="col-md-6">
              <label class="form-label small text-secondary-glass">New Password</label>
              <input type="password" name="new_password" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label small text-secondary-glass">Confirm New Password</label>
              <input type="password" name="confirm_password" class="form-control" required>
            </div>
          </div>
          <button type="submit" class="btn btn-glossy">Update Password</button>
        </form>
      </div>

      <div class="glass-card p-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
          <div class="fw-semibold">Preferences &amp; Notifications</div>
          <div class="text-muted-glass small">Reminder intervals, notification channels, appearance.</div>
        </div>
        <a href="/checkngo/settings.php" class="btn btn-outline-glass">Open Settings</a>
      </div>
    </div>
  </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
