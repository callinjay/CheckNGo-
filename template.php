<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth-check.php';
require_once __DIR__ . '/models/Activity.php';
require_once __DIR__ . '/models/Checklist.php';
require_once __DIR__ . '/models/Belonging.php';
require_once __DIR__ . '/controllers/ChecklistController.php';

$userId = current_user_id();
$type = (string) ($_GET['type'] ?? '');

if (!isset(Activity::CATEGORIES[$type])) {
    header('Location: /checkngo/activities.php');
    exit;
}

$activity = Activity::getOrCreateByType($userId, $type);
$template = Checklist::getOrCreateDefaultTemplate($userId, (int) $activity['id']);
$templateId = (int) $template['id'];

$feedback = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $feedback = ChecklistController::handleTemplateAction($_POST, $userId, $templateId);
    header('Location: /checkngo/template.php?type=' . urlencode($type) . '&ok=' . ($feedback['success'] ? '1' : '0') .
           ($feedback['error'] ? '&msg=' . urlencode($feedback['error']) : ''));
    exit;
}

$templateItems = Checklist::getTemplateItems($templateId);
$templateItemIds = array_column($templateItems, 'belonging_id');
$allBelongings = Belonging::listForUser($userId);
$availableBelongings = array_filter($allBelongings, fn ($b) => !in_array($b['id'], $templateItemIds, true));

$pageTitle = 'Manage Template — ' . Activity::CATEGORIES[$type][0];
$activeNav = 'activities';
include __DIR__ . '/includes/header.php';
?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>

<main class="main-content">
  <div class="d-flex align-items-center gap-2 mb-1">
    <a href="/checkngo/activities.php" class="text-secondary-glass"><i class="fa-solid fa-arrow-left"></i></a>
    <h1 class="h4 mb-0"><?= htmlspecialchars(Activity::CATEGORIES[$type][0]) ?> Checklist Template</h1>
  </div>
  <p class="text-secondary-glass mb-4">Add belongings and set their importance. This is the checklist CheckNGo will generate every time you start a <?= htmlspecialchars(strtolower(Activity::CATEGORIES[$type][0])) ?> check.</p>

  <?php if (isset($_GET['ok'])): ?>
    <?php if ($_GET['ok'] === '1'): ?>
      <div class="alert alert-secondary bg-transparent border-secondary text-white small">Template updated.</div>
    <?php else: ?>
      <div class="alert alert-secondary bg-transparent border-secondary text-white small">
        <?= htmlspecialchars($_GET['msg'] ?? 'Could not update the template.') ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>

  <div class="row g-3">
    <div class="col-lg-7">
      <div class="glass-card p-4">
        <h2 class="h6 mb-3">Checklist Items (<?= count($templateItems) ?>)</h2>
        <?php if (empty($templateItems)): ?>
          <p class="text-muted-glass small">No items yet — add belongings from the panel on the right.</p>
        <?php endif; ?>
        <?php foreach ($templateItems as $item): ?>
          <div class="check-item">
            <div>
              <div class="fw-semibold"><?= htmlspecialchars($item['item_name']) ?></div>
              <?php if ($item['category']): ?><div class="text-muted-glass small"><?= htmlspecialchars($item['category']) ?></div><?php endif; ?>
            </div>
            <div class="d-flex align-items-center gap-2">
              <form method="post" class="d-flex align-items-center gap-2">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_priority">
                <input type="hidden" name="template_item_id" value="<?= $item['id'] ?>">
                <select name="priority" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
                  <option value="critical" <?= $item['priority'] === 'critical' ? 'selected' : '' ?>>Critical</option>
                  <option value="important" <?= $item['priority'] === 'important' ? 'selected' : '' ?>>Important</option>
                  <option value="optional" <?= $item['priority'] === 'optional' ? 'selected' : '' ?>>Optional</option>
                </select>
              </form>
              <form method="post" onsubmit="return confirm('Remove this item from the template?')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="remove_item">
                <input type="hidden" name="template_item_id" value="<?= $item['id'] ?>">
                <button class="btn btn-outline-glass btn-sm" type="submit"><i class="fa-solid fa-trash"></i></button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="glass-card p-4">
        <h2 class="h6 mb-3">Add From My Items</h2>
        <?php if (empty($availableBelongings)): ?>
          <p class="text-muted-glass small">All your items are already in this checklist, or you haven't added any items yet.</p>
          <a href="/checkngo/my-items.php" class="btn btn-outline-glass btn-sm">Add Items</a>
        <?php else: ?>
          <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_item">
            <div class="mb-3">
              <label class="form-label text-secondary-glass small">Item</label>
              <select name="belonging_id" class="form-select" required>
                <?php foreach ($availableBelongings as $b): ?>
                  <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['item_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label text-secondary-glass small">Priority</label>
              <select name="priority" class="form-select">
                <option value="critical">Critical</option>
                <option value="important" selected>Important</option>
                <option value="optional">Optional</option>
              </select>
            </div>
            <div class="form-check mb-3">
              <input type="checkbox" class="form-check-input" name="is_required" id="isRequired" checked>
              <label class="form-check-label text-secondary-glass small" for="isRequired">Required for this activity</label>
            </div>
            <button type="submit" class="btn btn-glossy w-100">Add to Checklist</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
