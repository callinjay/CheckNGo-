<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/controllers/AuthController.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: /checkngo/dashboard.php');
    exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $result = AuthController::register($_POST);
    if ($result['success']) {
        header('Location: /checkngo/dashboard.php');
        exit;
    }
    $errors = $result['errors'];
}

$pageTitle = 'Create Account';
include __DIR__ . '/includes/header.php';
?>
<div class="auth-wrapper">
  <div class="glass-panel auth-card fade-in-up">
    <div class="text-center mb-4">
      <i class="fa-solid fa-check-double fa-2x mb-2" style="color:var(--glossy-white)"></i>
      <h1 class="h4 mb-0">Create your account</h1>
      <p class="text-muted-glass small">Before you go, CheckNGo.</p>
    </div>

    <form method="post" novalidate>
      <?= csrf_field() ?>
      <div class="mb-3">
        <label class="form-label text-secondary-glass small">Full Name</label>
        <input type="text" name="full_name" class="form-control" required
               value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>">
        <?php if (!empty($errors['full_name'])): ?><div class="small mt-1" style="color:var(--error)"><?= htmlspecialchars($errors['full_name']) ?></div><?php endif; ?>
      </div>
      <div class="mb-3">
        <label class="form-label text-secondary-glass small">Email</label>
        <input type="email" name="email" class="form-control" required
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        <?php if (!empty($errors['email'])): ?><div class="small mt-1" style="color:var(--error)"><?= htmlspecialchars($errors['email']) ?></div><?php endif; ?>
      </div>
      <div class="mb-3">
        <label class="form-label text-secondary-glass small">Password</label>
        <input type="password" name="password" class="form-control" required>
        <?php if (!empty($errors['password'])): ?><div class="small mt-1" style="color:var(--error)"><?= htmlspecialchars($errors['password']) ?></div><?php endif; ?>
      </div>
      <div class="mb-3">
        <label class="form-label text-secondary-glass small">Confirm Password</label>
        <input type="password" name="confirm_password" class="form-control" required>
        <?php if (!empty($errors['confirm_password'])): ?><div class="small mt-1" style="color:var(--error)"><?= htmlspecialchars($errors['confirm_password']) ?></div><?php endif; ?>
      </div>
      <div class="form-check mb-3">
        <input class="form-check-input" type="checkbox" name="terms" id="terms">
        <label class="form-check-label text-secondary-glass small" for="terms">
          I agree to the Terms and Privacy Policy.
        </label>
        <?php if (!empty($errors['terms'])): ?><div class="small mt-1" style="color:var(--error)"><?= htmlspecialchars($errors['terms']) ?></div><?php endif; ?>
      </div>
      <button type="submit" class="btn btn-glossy w-100">Create Account</button>
    </form>

    <p class="text-center text-secondary-glass small mt-4 mb-0">
      Already have an account? <a href="/checkngo/login.php" class="text-white text-decoration-underline">Log in</a>
    </p>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
