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
    $result = AuthController::login($_POST);
    if ($result['success']) {
        header('Location: /checkngo/dashboard.php');
        exit;
    }
    $errors = $result['errors'];
}

$pageTitle = 'Log In';
include __DIR__ . '/includes/header.php';
?>
<div class="auth-wrapper">
  <div class="glass-panel auth-card fade-in-up">
    <div class="text-center mb-4">
      <i class="fa-solid fa-check-double fa-2x mb-2" style="color:var(--glossy-white)"></i>
      <h1 class="h4 mb-0">CheckNGo</h1>
      <p class="text-muted-glass small">Check it. Pack it. Go.</p>
    </div>

    <?php if (!empty($errors)): ?>
      <div class="alert alert-secondary bg-transparent border-secondary text-white small">
        <?= htmlspecialchars(reset($errors)) ?>
      </div>
    <?php endif; ?>

    <form method="post" novalidate>
      <?= csrf_field() ?>
      <div class="mb-3">
        <label class="form-label text-secondary-glass small">Email</label>
        <input type="email" name="email" class="form-control" required
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
      </div>
      <div class="mb-3">
        <label class="form-label text-secondary-glass small">Password</label>
        <div class="input-group">
          <input type="password" name="password" id="passwordInput" class="form-control" required>
          <button class="btn btn-outline-glass" type="button" onclick="togglePassword()">
            <i class="fa-solid fa-eye" id="toggleIcon"></i>
          </button>
        </div>
      </div>
      <div class="form-check mb-3">
        <input class="form-check-input" type="checkbox" name="remember" id="remember">
        <label class="form-check-label text-secondary-glass small" for="remember">Remember me</label>
      </div>
      <button type="submit" class="btn btn-glossy w-100">Log In</button>
    </form>

    <p class="text-center text-secondary-glass small mt-4 mb-0">
      Don't have an account? <a href="/checkngo/register.php" class="text-white text-decoration-underline">Register</a>
    </p>
  </div>
</div>
<script>
function togglePassword(){
  const input = document.getElementById('passwordInput');
  const icon = document.getElementById('toggleIcon');
  const show = input.type === 'password';
  input.type = show ? 'text' : 'password';
  icon.classList.toggle('fa-eye');
  icon.classList.toggle('fa-eye-slash');
}
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
