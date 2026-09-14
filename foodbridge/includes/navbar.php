<?php
/**
 * FoodBridge - Public/guest navigation bar
 * ===========================================
 * Used by the public pages only (index.php, about.php, how-it-works.php,
 * login.php, register.php). Donor, NGO, and admin pages have their own
 * role-specific navigation (donor/_nav.php, ngo/_nav.php,
 * admin/_sidebar.php) instead - this one is for visitors who haven't
 * signed in yet, plus a "Go to Dashboard" shortcut if they have.
 *
 * Set $current_page ('home' | 'about' | 'how' | '') before including
 * this to highlight the active link.
 */
$current_page = $current_page ?? '';
$logged_in = is_logged_in();
?>
<nav class="navbar navbar-expand-lg fb-navbar sticky-top">
  <div class="container">
    <a class="navbar-brand" href="<?= e(BASE_URL) ?>index.php">
      <span class="brand-mark">🌉</span> <?= e(SITE_NAME) ?>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#publicNav" aria-controls="publicNav" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="publicNav">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item"><a class="nav-link <?= $current_page === 'home' ? 'active' : '' ?>" href="<?= e(BASE_URL) ?>index.php">Home</a></li>
        <li class="nav-item"><a class="nav-link <?= $current_page === 'about' ? 'active' : '' ?>" href="<?= e(BASE_URL) ?>about.php">About</a></li>
        <li class="nav-item"><a class="nav-link <?= $current_page === 'how' ? 'active' : '' ?>" href="<?= e(BASE_URL) ?>how-it-works.php">How It Works</a></li>
      </ul>
      <div class="d-flex gap-2">
        <?php if ($logged_in): ?>
          <a href="<?= e(dashboard_path_for_role(current_user_role())) ?>" class="btn btn-success btn-sm px-3">Go to Dashboard</a>
        <?php else: ?>
          <a href="<?= e(BASE_URL) ?>login.php" class="btn btn-outline-success btn-sm px-3">Log In</a>
          <a href="<?= e(BASE_URL) ?>register.php" class="btn btn-success btn-sm px-3">Register</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</nav>
