<?php
/**
 * Admin layout shell: a responsive sidebar + topbar, opened here and
 * closed at the bottom of each admin/*.php page (see the closing
 * </div></div></div> before includes/footer.php in every admin file).
 * Included by every admin/*.php page after require_role('admin').
 * Set $current_page before including this to highlight the active link,
 * and $page_title (used by header.php) to label the topbar.
 */
$current_page = $current_page ?? '';
$links = [
    'dashboard'     => ['Dashboard', '📊'],
    'users'         => ['Users', '👤'],
    'donors'        => ['Donors', '🍱'],
    'ngos'          => ['NGOs', '🏛️'],
    'categories'    => ['Categories', '🏷️'],
    'donations'     => ['Donations', '🥡'],
    'requests'      => ['Requests', '📥'],
    'pickups'       => ['Pickups', '🚚'],
    'distributions' => ['Distributions', '📦'],
    'reports'       => ['Reports', '📈'],
];
?>
<div class="admin-shell">
  <aside class="admin-sidebar" id="adminSidebar">
    <a class="sidebar-brand" href="<?= e(BASE_URL) ?>admin/dashboard.php">
      <span class="brand-mark">🌉</span> <?= e(SITE_NAME) ?>
    </a>
    <nav>
      <?php foreach ($links as $page => [$label, $icon]): ?>
        <a class="nav-link <?= $current_page === $page ? 'active' : '' ?>" href="<?= e(BASE_URL) ?>admin/<?= e($page) ?>.php">
          <span aria-hidden="true"><?= $icon ?></span> <?= e($label) ?>
        </a>
      <?php endforeach; ?>
    </nav>
    <div class="sidebar-foot">
      <span class="who"><?= e(current_user_name()) ?></span>
      <a href="<?= e(BASE_URL) ?>logout.php" class="text-decoration-none">Logout</a>
    </div>
  </aside>

  <div class="admin-backdrop"></div>

  <div class="admin-main">
    <div class="admin-topbar">
      <button type="button" class="sidebar-toggle-btn" data-sidebar-toggle aria-controls="adminSidebar" aria-expanded="false" aria-label="Toggle navigation">
        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M3 5h14M3 10h14M3 15h14" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
      </button>
      <span class="fw-semibold"><?= e($page_title ?? ucfirst($current_page)) ?></span>
      <div class="ms-auto d-flex align-items-center gap-2">
        <span class="small text-muted d-none d-sm-inline">Hi, <?= e(current_user_name()) ?></span>
        <a href="<?= e(BASE_URL) ?>logout.php" class="btn btn-sm btn-outline-danger">Logout</a>
      </div>
    </div>
    <div class="admin-content">
