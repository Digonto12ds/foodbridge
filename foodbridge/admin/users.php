<?php
// USER MANAGEMENT (protected: require_role('admin'))
// - View all users, filter by role (donor/ngo/admin)
// - Activate/deactivate accounts (blocks login immediately - see
//   toggle_user_active() and the is_active check in role_check.php)
require_once __DIR__ . '/../includes/role_check.php';
require_role('admin');

$admin_user_id = current_user_id();
$flash = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_active') {
    verify_csrf();

    $target_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    if ($target_id) {
        $result = toggle_user_active($pdo, $target_id, $admin_user_id);
        $flash = match ($result) {
            'activated'   => ['success', 'User activated.'],
            'deactivated' => ['success', 'User deactivated.'],
            'self'        => ['danger', 'You cannot deactivate your own account.'],
        };
    }
}

$role_filter = $_GET['role'] ?? 'all';
if (!in_array($role_filter, ['all', 'donor', 'ngo', 'admin'], true)) {
    $role_filter = 'all';
}

$sql = "SELECT u.user_id, u.name, u.email, u.phone, u.role, u.is_active, u.created_at,
               COALESCE(d.organization_name, n.organization_name) AS organization_name
        FROM users u
        LEFT JOIN donors d ON d.user_id = u.user_id
        LEFT JOIN ngos n ON n.user_id = u.user_id";
$params = [];
if ($role_filter !== 'all') {
    $sql .= ' WHERE u.role = :role';
    $params[':role'] = $role_filter;
}
$sql .= ' ORDER BY u.user_id';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$current_page = 'users';
$page_title = 'User Management';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/_nav.php';
?>

<div class="container-fluid pb-5">
    <h3 class="mb-3">User Management</h3>

    <?php if ($flash): ?>
      <div class="alert alert-<?= e($flash[0]) ?>"><?= e($flash[1]) ?></div>
    <?php endif; ?>

    <ul class="nav nav-pills mb-3">
        <?php foreach (['all' => 'All', 'donor' => 'Donors', 'ngo' => 'NGOs', 'admin' => 'Admins'] as $key => $label): ?>
          <li class="nav-item">
            <a class="nav-link <?= $role_filter === $key ? 'active' : '' ?>" href="users.php?role=<?= e($key) ?>"><?= e($label) ?></a>
          </li>
        <?php endforeach; ?>
    </ul>

    <div class="table-responsive">
      <table class="table table-bordered bg-white align-middle">
        <thead class="table-light">
          <tr>
            <th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Role</th>
            <th>Organization</th><th>Status</th><th>Joined</th><th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $u): ?>
            <tr>
              <td><?= (int) $u['user_id'] ?></td>
              <td><?= e($u['name']) ?></td>
              <td><?= e($u['email']) ?></td>
              <td><?= e($u['phone']) ?></td>
              <td><span class="badge bg-secondary text-uppercase"><?= e($u['role']) ?></span></td>
              <td><?= $u['organization_name'] !== null ? e($u['organization_name']) : '<span class="text-muted">-</span>' ?></td>
              <td>
                <?php if ((int) $u['is_active'] === 1): ?>
                  <span class="badge bg-success">Active</span>
                <?php else: ?>
                  <span class="badge bg-danger">Deactivated</span>
                <?php endif; ?>
              </td>
              <td><?= e(date('d M Y', strtotime($u['created_at']))) ?></td>
              <td>
                <?php if ((int) $u['user_id'] === $admin_user_id): ?>
                  <span class="text-muted small">(you)</span>
                <?php else: ?>
                  <form method="post" action="users.php" class="d-inline"
                        onsubmit="return confirm('<?= (int) $u['is_active'] === 1 ? 'Deactivate' : 'Activate' ?> this user?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="toggle_active">
                    <input type="hidden" name="user_id" value="<?= (int) $u['user_id'] ?>">
                    <?php if ((int) $u['is_active'] === 1): ?>
                      <button type="submit" class="btn btn-sm btn-outline-danger">Deactivate</button>
                    <?php else: ?>
                      <button type="submit" class="btn btn-sm btn-outline-success">Activate</button>
                    <?php endif; ?>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
</div>
</div><!-- /.admin-content -->
</div><!-- /.admin-main -->
</div><!-- /.admin-shell -->
<?php require __DIR__ . '/../includes/footer.php'; ?>
