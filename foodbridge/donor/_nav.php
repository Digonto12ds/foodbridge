<?php
/**
 * Shared top nav for the donor module. Included by every donor/*.php
 * page after require_role('donor'), so $current_page (optional) can be
 * set before including this to highlight the active link.
 */
$current_page = $current_page ?? '';
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-success mb-4">
  <div class="container">
    <a class="navbar-brand" href="<?= e(BASE_URL) ?>donor/dashboard.php"><?= e(SITE_NAME) ?> - Donor</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#donorNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="donorNav">
      <ul class="navbar-nav me-auto">
        <li class="nav-item">
          <a class="nav-link <?= $current_page === 'dashboard' ? 'active fw-bold' : '' ?>" href="<?= e(BASE_URL) ?>donor/dashboard.php">Dashboard</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $current_page === 'add_donation' ? 'active fw-bold' : '' ?>" href="<?= e(BASE_URL) ?>donor/add_donation.php">Add Donation</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $current_page === 'my_donations' ? 'active fw-bold' : '' ?>" href="<?= e(BASE_URL) ?>donor/my_donations.php">My Donations</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $current_page === 'profile' ? 'active fw-bold' : '' ?>" href="<?= e(BASE_URL) ?>donor/profile.php">Profile</a>
        </li>
      </ul>
      <span class="navbar-text text-white me-3">Hi, <?= e(current_user_name()) ?></span>
      <a href="<?= e(BASE_URL) ?>logout.php" class="btn btn-outline-light btn-sm">Logout</a>
    </div>
  </div>
</nav>
