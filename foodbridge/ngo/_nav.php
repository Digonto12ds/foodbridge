<?php
/**
 * Shared top nav for the NGO module. Included by every ngo/*.php page
 * after require_role('ngo'). Set $current_page before including this
 * to highlight the active link.
 */
$current_page = $current_page ?? '';
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4 sticky-top">
  <div class="container">
    <a class="navbar-brand" href="<?= e(BASE_URL) ?>ngo/dashboard.php"><?= e(SITE_NAME) ?> - NGO</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#ngoNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="ngoNav">
      <ul class="navbar-nav me-auto">
        <li class="nav-item">
          <a class="nav-link <?= $current_page === 'dashboard' ? 'active fw-bold' : '' ?>" href="<?= e(BASE_URL) ?>ngo/dashboard.php">Dashboard</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $current_page === 'available_food' ? 'active fw-bold' : '' ?>" href="<?= e(BASE_URL) ?>ngo/available_food.php">Available Food</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $current_page === 'my_requests' ? 'active fw-bold' : '' ?>" href="<?= e(BASE_URL) ?>ngo/my_requests.php">My Requests</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $current_page === 'pickups' ? 'active fw-bold' : '' ?>" href="<?= e(BASE_URL) ?>ngo/pickups.php">Pickups</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $current_page === 'distributions' ? 'active fw-bold' : '' ?>" href="<?= e(BASE_URL) ?>ngo/distributions.php">Distributions</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $current_page === 'profile' ? 'active fw-bold' : '' ?>" href="<?= e(BASE_URL) ?>ngo/profile.php">Profile</a>
        </li>
      </ul>
      <span class="navbar-text text-white me-3">Hi, <?= e(current_user_name()) ?></span>
      <a href="<?= e(BASE_URL) ?>logout.php" class="btn btn-outline-light btn-sm">Logout</a>
    </div>
  </div>
</nav>
