<?php
/**
 * FoodBridge - Unified login (Donor, NGO, and Admin all use this one form)
 *
 * Flow: email + password submitted -> attempt_login() checks the
 * `users` table with password_verify() -> on success a session is
 * started (see includes/auth.php) -> redirect based on $_SESSION['role'].
 */
require_once __DIR__ . '/includes/auth.php';

// Already logged in? Skip straight to the right dashboard.
if (is_logged_in()) {
    redirect(dashboard_path_for_role(current_user_role()));
}

$errors = [];
$old_email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old_email = sanitize_input($_POST['email'] ?? '');
    $password  = $_POST['password'] ?? '';

    if ($old_email === '' || !is_valid_email($old_email)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if ($password === '') {
        $errors[] = 'Please enter your password.';
    }

    if (!$errors) {
        $user = attempt_login($pdo, $old_email, $password);

        if ($user === 'inactive') {
            $errors[] = 'This account has been deactivated. Please contact an administrator.';
        } elseif (!$user) {
            $errors[] = 'Invalid email or password.';
        } else {
            // Session now holds user_id + name + role -> route by role.
            redirect(dashboard_path_for_role($user['role']));
        }
    }
}
$page_title = 'Log In';
require __DIR__ . '/includes/header.php';
?>
<div class="fb-hero flex-grow-1 d-flex align-items-center py-5">
<div class="container py-4">
  <div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
      <div class="card shadow">
        <div class="card-body p-4">
          <a href="index.php" class="text-decoration-none d-inline-flex align-items-center gap-2 mb-3">
            <span class="brand-mark d-inline-flex align-items-center justify-content-center" style="width:34px;height:34px;border-radius:9px;background:var(--fb-primary);color:#fff;">🌉</span>
            <span class="fw-brand fs-5" style="color:var(--fb-primary);"><?= e(SITE_NAME) ?></span>
          </a>
          <h2 class="mb-1 h4">Welcome back</h2>
          <p class="text-muted mb-4">Log in to your account</p>

          <?php if (isset($_GET['registered'])): ?>
            <div class="alert alert-success">Registration successful. Please log in.</div>
          <?php endif; ?>
          <?php if (isset($_GET['loggedout'])): ?>
            <div class="alert alert-info">You have been logged out.</div>
          <?php endif; ?>
          <?php if (($_GET['error'] ?? '') === 'login_required'): ?>
            <div class="alert alert-warning">Please log in to continue.</div>
          <?php endif; ?>
          <?php if (($_GET['error'] ?? '') === 'account_deactivated'): ?>
            <div class="alert alert-danger">This account has been deactivated. Please contact an administrator.</div>
          <?php endif; ?>

          <?php if ($errors): ?>
            <div class="alert alert-danger">
              <ul class="mb-0">
                <?php foreach ($errors as $err): ?>
                  <li><?= e($err) ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>

          <form method="post" action="login.php" novalidate>
            <div class="mb-3">
              <label for="email" class="form-label">Email</label>
              <input type="email" class="form-control" id="email" name="email"
                     value="<?= e($old_email) ?>" required autofocus>
            </div>
            <div class="mb-3">
              <label for="password" class="form-label">Password</label>
              <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-success w-100">Log In</button>
          </form>

          <p class="text-center mt-3 mb-0">
            Don't have an account? <a href="register.php">Register as Donor / NGO</a>
          </p>
        </div>
      </div>
    </div>
  </div>
</div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
