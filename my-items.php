<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth-check.php';
require_once __DIR__ . '/controllers/ItemController.php';
require_once __DIR__ . '/models/Belonging.php';

$userId = current_user_id();
$feedback = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $feedback = ItemController::handle($_POST, $userId);
}

$search = trim((string) ($_GET['search'] ?? ''));
$category = trim((string) ($_GET['category'] ?? ''));
$items = Belonging::listForUser($userId, $search, $category);
$categories = Belonging::categories($userId);

$pageTitle = 'My Items';
$activeNav = 'items';
include __DIR__ . '/includes/header.php';
?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>

<main class="main-content">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0">My Items</h1>
    <button class="btn btn-glossy" data-bs-toggle="modal" data-bs-target="#addItemModal">
      <i class="fa-solid fa-plus"></i> Add Item
    </button>
  </div>

  <?php if ($feedback && !$feedback['success']): ?>
    <div class="alert alert-secondary bg-transparent border-secondary text-white small">
      <?= htmlspecialchars($feedback['error']) ?>
    </div>
  <?php endif; ?>

  <form method="get" class="row g-2 mb-4">
    <div class="col-md-6">
      <input type="text" name="search" class="form-control" placeholder="Search items..."
             value="<?= htmlspecialchars($search) ?>">
    </div>
    <div class="col-md-4">
      <select name="category" class="form-select">
        <option value="">All Categories</option>
        <?php foreach ($categories as $cat): ?>
          <option value="<?= htmlspecialchars($cat) ?>" <?= $category === $cat ? 'selected' : '' ?>><?= htmlspecialchars($cat) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <button class="btn btn-outline-glass w-100" type="submit">Filter</button>
    </div>
  </form>

  <div class="glass-card p-4">
    <?php if (empty($items)): ?>
      <p class="text-muted-glass small mb-0">No items found. Add your first belonging to get started.</p>
    <?php else: ?>
      <?php foreach ($items as $item): ?>
        <div class="check-item">
          <div>
            <div class="fw-semibold"><?= htmlspecialchars($item['item_name']) ?></div>
            <div class="text-muted-glass small">
              <?= htmlspecialchars($item['category'] ?: 'Uncategorized') ?>
              <?php if ($item['description']): ?> • <?= htmlspecialchars($item['description']) ?><?php endif; ?>
            </div>
          </div>
          <div class="d-flex align-items-center gap-2">
            <span class="priority-badge <?= $item['default_priority'] === 'critical' ? 'priority-critical' : '' ?>">
              <?= ucfirst($item['default_priority']) ?>
            </span>
            <button class="btn btn-outline-glass btn-sm" data-bs-toggle="modal" data-bs-target="#editModal<?= $item['id'] ?>">
              <i class="fa-solid fa-pen"></i>
            </button>
            <form method="post" onsubmit="return confirm('Remove this item? It will stay in past checklist history.')">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
              <button class="btn btn-outline-glass btn-sm" type="submit"><i class="fa-solid fa-trash"></i></button>
            </form>
          </div>
        </div>

        <!-- Edit modal -->
        <div class="modal fade" id="editModal<?= $item['id'] ?>" tabindex="-1">
          <div class="modal-dialog">
            <div class="modal-content" style="background:var(--bg-secondary);color:var(--pure-white);border:1px solid var(--border);border-radius:var(--radius-lg);">
              <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                <div class="modal-header border-secondary">
                  <h5 class="modal-title">Edit Item</h5>
                  <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                  <div class="mb-3">
                    <label class="form-label small text-secondary-glass">Name</label>
                    <input type="text" name="item_name" class="form-control" value="<?= htmlspecialchars($item['item_name']) ?>" required>
                  </div>
                  <div class="mb-3">
                    <label class="form-label small text-secondary-glass">Category</label>
                    <input type="text" name="category" class="form-control" value="<?= htmlspecialchars($item['category'] ?? '') ?>">
                  </div>
                  <div class="mb-3">
                    <label class="form-label small text-secondary-glass">Notes</label>
                    <input type="text" name="description" class="form-control" value="<?= htmlspecialchars($item['description'] ?? '') ?>">
                  </div>
                  <div class="mb-3">
                    <label class="form-label small text-secondary-glass">Default Priority</label>
                    <select name="default_priority" class="form-select">
                      <?php foreach (['critical','important','optional'] as $p): ?>
                        <option value="<?= $p ?>" <?= $item['default_priority'] === $p ? 'selected' : '' ?>><?= ucfirst($p) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>
                <div class="modal-footer border-secondary">
                  <button type="submit" class="btn btn-glossy">Save Changes</button>
                </div>
              </form>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</main>

<!-- Add modal -->
<div class="modal fade" id="addItemModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content" style="background:var(--bg-secondary);color:var(--pure-white);border:1px solid var(--border);border-radius:var(--radius-lg);">
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <div class="modal-header border-secondary">
          <h5 class="modal-title">Add Item</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label small text-secondary-glass">Name</label>
            <input type="text" name="item_name" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label small text-secondary-glass">Category</label>
            <input type="text" name="category" class="form-control" placeholder="e.g. Electronics, Documents">
          </div>
          <div class="mb-3">
            <label class="form-label small text-secondary-glass">Notes</label>
            <input type="text" name="description" class="form-control">
          </div>
          <div class="mb-3">
            <label class="form-label small text-secondary-glass">Default Priority</label>
            <select name="default_priority" class="form-select">
              <option value="critical">Critical</option>
              <option value="important" selected>Important</option>
              <option value="optional">Optional</option>
            </select>
          </div>
        </div>
        <div class="modal-footer border-secondary">
          <button type="submit" class="btn btn-glossy">Add Item</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
