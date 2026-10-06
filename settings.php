<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth-check.php';
require_once __DIR__ . '/controllers/SettingsController.php';
require_once __DIR__ . '/controllers/AuthController.php';

$userId = current_user_id();
$feedback = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $action = $_POST['action'] ?? '';
    $feedback = SettingsController::handle($_POST, $userId);

    if ($action === 'delete_account' && $feedback['success']) {
        AuthController::logout();
        header('Location: /checkngo/login.php');
        exit;
    }
}

$prefs = Preference::get($userId);
$intervals = $prefs['default_reminder_intervals'] ?: ['preparation' => 30, 'checklist' => 15, 'final_check' => 5];
// Handle both the old flat-array shape ([30,15,5,0]) and the named-key shape.
if (array_is_list($intervals)) {
    $intervals = [
        'preparation' => $intervals[0] ?? 30,
        'checklist'   => $intervals[1] ?? 15,
        'final_check' => $intervals[2] ?? 5,
    ];
}
$notifPrefs = $prefs['notification_preferences'] ?: ['in_app' => true, 'browser' => false];

$pageTitle = 'Settings';
$activeNav = 'settings';
include __DIR__ . '/includes/header.php';
?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>

<main class="main-content">
  <h1 class="h4 mb-4">Settings</h1>

  <?php if ($feedback): ?>
    <div class="alert alert-secondary bg-transparent border-secondary text-white small">
      <?= $feedback['success'] ? 'Saved.' : htmlspecialchars($feedback['error']) ?>
    </div>
  <?php endif; ?>

  <div class="row g-3">
    <div class="col-lg-7">
      <div class="glass-card p-4 mb-3">
        <h2 class="h6 mb-3">Default Reminder Intervals</h2>
        <p class="text-muted-glass small">Used as the starting point whenever you schedule a new departure — you can still adjust per-schedule on the Reminders page.</p>
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="update_preferences">

          <div class="row g-2 mb-3">
            <div class="col-4">
              <label class="form-label small text-secondary-glass">Preparation (min before)</label>
              <input type="number" min="0" max="180" name="interval_preparation" class="form-control" value="<?= (int) $intervals['preparation'] ?>">
            </div>
            <div class="col-4">
              <label class="form-label small text-secondary-glass">Checklist (min before)</label>
              <input type="number" min="0" max="180" name="interval_checklist" class="form-control" value="<?= (int) $intervals['checklist'] ?>">
            </div>
            <div class="col-4">
              <label class="form-label small text-secondary-glass">Final Check (min before)</label>
              <input type="number" min="0" max="180" name="interval_final_check" class="form-control" value="<?= (int) $intervals['final_check'] ?>">
            </div>
          </div>

          <h2 class="h6 mb-2 mt-4">Notification Channels</h2>
          <div class="form-check mb-2">
            <input type="checkbox" class="form-check-input" name="notify_in_app" id="notifyInApp" <?= $notifPrefs['in_app'] ?? true ? 'checked' : '' ?>>
            <label class="form-check-label small" for="notifyInApp">In-app Notification Center (always reliable)</label>
          </div>
          <div class="form-check mb-3">
            <input type="checkbox" class="form-check-input" name="notify_browser" id="notifyBrowser" <?= $notifPrefs['browser'] ?? false ? 'checked' : '' ?>>
            <label class="form-check-label small" for="notifyBrowser">Browser notifications, if this browser allows it</label>
          </div>
          <div class="alert alert-secondary bg-transparent border-secondary small">
            <i class="fa-solid fa-circle-info"></i>
            Browser notifications require this permission to be granted <em>and</em> a tab open when a
            reminder fires — CheckNGo cannot wake a closed browser. The in-app Notification Center is the
            dependable channel in this build.
          </div>
          <button type="button" class="btn btn-outline-glass btn-sm mb-3" id="requestNotifPermBtn">
            <i class="fa-regular fa-bell"></i> Request Browser Permission
          </button>

          <h2 class="h6 mb-2 mt-3">Appearance</h2>
          <select name="theme" class="form-select mb-3" style="max-width:220px">
            <option value="dark" <?= ($prefs['theme'] ?? 'dark') === 'dark' ? 'selected' : '' ?>>Dark (recommended)</option>
            <option value="light" <?= ($prefs['theme'] ?? 'dark') === 'light' ? 'selected' : '' ?>>Light</option>
          </select>
          <div class="form-text text-muted-glass mb-3">CheckNGo's Liquid Glass design is built for dark mode; light mode is stored as a preference but not yet themed in this build.</div>

          <button type="submit" class="btn btn-glossy">Save Preferences</button>
        </form>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="glass-card p-4 mb-3">
        <h2 class="h6 mb-2">Privacy</h2>
        <p class="text-muted-glass small mb-0">
          Your belongings, checklists, and history are visible only to your account.
          Every page and API endpoint in CheckNGo checks that a record's <code>user_id</code>
          matches your session before showing or changing it.
        </p>
      </div>

      <div class="glass-card p-4 mb-3">
        <h2 class="h6 mb-2">Account</h2>
        <a href="/checkngo/logout.php" class="btn btn-outline-glass w-100 mb-2">
          <i class="fa-solid fa-arrow-right-from-bracket"></i> Log Out
        </a>
      </div>

      <div class="glass-card p-4" style="border-color:var(--error)">
        <h2 class="h6 mb-2">Danger Zone</h2>
        <p class="text-muted-glass small">Permanently deletes your account and all associated data (belongings, checklists, history, reminders). This cannot be undone.</p>
        <button class="btn btn-outline-glass w-100" data-bs-toggle="modal" data-bs-target="#deleteModal">
          Delete My Account
        </button>
      </div>
    </div>
  </div>
</main>

<div class="modal fade" id="deleteModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content" style="background:var(--bg-secondary);color:var(--pure-white);border:1px solid var(--border);border-radius:var(--radius-lg);">
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="delete_account">
        <div class="modal-header border-secondary">
          <h5 class="modal-title">Confirm Account Deletion</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p class="small text-secondary-glass">Enter your password to permanently delete your account.</p>
          <input type="password" name="confirm_password" class="form-control" placeholder="Password" required>
        </div>
        <div class="modal-footer border-secondary">
          <button type="submit" class="btn btn-glossy">Permanently Delete</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.getElementById('requestNotifPermBtn').addEventListener('click', async () => {
  if (!('Notification' in window)) {
    ckToast('This browser does not support notifications.', 'error');
    return;
  }
  const permission = await Notification.requestPermission();
  ckToast(permission === 'granted' ? 'Browser notifications enabled.' : 'Permission not granted.', permission === 'granted' ? 'success' : 'warning');
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
