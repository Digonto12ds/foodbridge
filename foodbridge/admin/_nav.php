<?php
/**
 * Shared top nav for the admin module. Included by every admin/*.php
 * page after require_role('admin'). Set $current_page before including
 * this to highlight the active link.
 */
$current_page = $current_page ?? '';
$links = [
    'dashboard'     => 'Dashboard',
    'users'         => 'Users',
    'donors'        => 'Donors',
    'ngos'          => 'NGOs',
    'categories'    => 'Categories',
    'donations'     => 'Donations',
    'requests'      => 'Requests',
    'pickups'       => 'Pickups',
    'distributions' => 'Distributions',
    'reports'       => 'Reports',
];
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
  <div class="container-fluid">
    <a class="navbar-brand" href="<?= e(BASE_URL) ?>admin/dashboard.php"><?= e(SITE_NAME) ?> - Admin</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="adminNav">
      <ul class="navbar-nav me-auto flex-wrap">
        <?php foreach ($links as $page => $label): ?>
          <li class="nav-item">
            <a class="nav-link <?= $current_page === $page ? 'active fw-bold' : '' ?>" href="<?= e(BASE_URL) ?>admin/<?= e($page) ?>.php"><?= e($label) ?></a>
          </li>
        <?php endforeach; ?>
      </ul>
      <span class="navbar-text text-white me-3">Hi, <?= e(current_user_name()) ?></span>
      <a href="<?= e(BASE_URL) ?>logout.php" class="btn btn-outline-light btn-sm">Logout</a>
    </div>
  </div>
</nav>
