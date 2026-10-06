<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth-check.php';
require_once __DIR__ . '/models/Notification.php';
require_once __DIR__ . '/models/Activity.php';

$userId = current_user_id();
$notifications = Notification::listForUser($userId);

$pageTitle = 'Notifications';
$activeNav = 'notifications';
include __DIR__ . '/includes/header.php';
?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>

<main class="main-content">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0">Notification Center</h1>
    <button class="btn btn-outline-glass btn-sm" id="markAllReadBtn">Mark all as read</button>
  </div>

  <div class="glass-card p-4" id="notificationList">
    <?php if (empty($notifications)): ?>
      <p class="text-muted-glass small mb-0">
        No notifications yet. They'll appear here once a scheduled reminder is processed
        (see the note on the Reminders page about running the reminder processor locally).
      </p>
    <?php endif; ?>
    <?php foreach ($notifications as $n): ?>
      <div class="check-item" data-notification-id="<?= $n['id'] ?>">
        <div>
          <div class="fw-semibold <?= $n['read_at'] ? 'text-muted-glass' : '' ?>"><?= htmlspecialchars($n['title']) ?></div>
          <div class="text-secondary-glass small"><?= htmlspecialchars($n['message']) ?></div>
          <div class="text-muted-glass small mt-1"><?= date('M j, g:i A', strtotime($n['created_at'])) ?></div>
        </div>
        <div class="d-flex align-items-center gap-2">
          <?php if (in_array($n['notification_type'], ['checklist', 'final_check'], true)): ?>
            <a href="/checkngo/activities.php" class="btn btn-glossy btn-sm">Check Now</a>
          <?php endif; ?>
          <?php if (!$n['read_at']): ?>
            <button class="btn btn-outline-glass btn-sm dismiss-btn">Dismiss</button>
          <?php else: ?>
            <span class="text-muted-glass small">Read</span>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</main>

<script>
const CSRF_TOKEN = '<?= csrf_token() ?>';

async function markRead(id) {
  await fetch('/checkngo/ajax/mark-notification-read.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: new URLSearchParams({notification_id: id, csrf_token: CSRF_TOKEN})
  });
}

document.querySelectorAll('.dismiss-btn').forEach(btn => {
  btn.addEventListener('click', async (e) => {
    const row = e.target.closest('[data-notification-id]');
    await markRead(row.dataset.notificationId);
    row.querySelector('.fw-semibold').classList.add('text-muted-glass');
    e.target.outerHTML = '<span class="text-muted-glass small">Read</span>';
  });
});

document.getElementById('markAllReadBtn').addEventListener('click', async () => {
  await fetch('/checkngo/ajax/mark-notification-read.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: new URLSearchParams({all: '1', csrf_token: CSRF_TOKEN})
  });
  ckToast('All notifications marked as read.');
  setTimeout(() => location.reload(), 700);
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
