<?php
// NGO MANAGEMENT (protected: require_role('admin'))
// - NGO-specific view: organization info + request activity, with the
//   same activate/deactivate control as users.php
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
            'activated'   => ['success', 'NGO activated.'],
            'deactivated' => ['success', 'NGO deactivated.'],
            'self'        => ['danger', 'You cannot deactivate your own account.'],
        };
    }
}

$stmt = $pdo->query(
    "SELECT u.user_id, u.name, u.email, u.is_active,
            n.ngo_id, n.organization_name, n.registration_no, n.contact, n.address,
            (SELECT COUNT(*) FROM requests WHERE ngo_id = n.ngo_id) AS request_count,
            (SELECT COUNT(*) FROM requests WHERE ngo_id = n.ngo_id AND status = 'Completed') AS completed_count
     FROM ngos n
     JOIN users u ON u.user_id = n.user_id
     ORDER BY n.ngo_id"
);
$ngos = $stmt->fetchAll();

$current_page = 'ngos';
$page_title = 'NGO Management';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/_nav.php';
?>

<div class="container-fluid pb-5">
    <h3 class="mb-3">NGO Management</h3>

    <?php if ($flash): ?>
      <div class="alert alert-<?= e($flash[0]) ?>"><?= e($flash[1]) ?></div>
    <?php endif; ?>

    <div class="table-responsive">
      <table class="table table-bordered bg-white align-middle">
        <thead class="table-light">
          <tr>
            <th>Contact Name</th><th>Email</th><th>Organization</th><th>Registration No.</th>
            <th>Contact No.</th><th>Requests</th><th>Completed</th><th>Status</th><th></th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$ngos): ?>
            <tr><td colspan="9" class="text-center text-muted">No NGOs yet.</td></tr>
          <?php endif; ?>
          <?php foreach ($ngos as $n): ?>
            <tr>
              <td><?= e($n['name']) ?></td>
              <td><?= e($n['email']) ?></td>
              <td><?= e($n['organization_name']) ?></td>
              <td><?= e($n['registration_no']) ?></td>
              <td><?= e($n['contact']) ?></td>
              <td><?= (int) $n['request_count'] ?></td>
              <td><?= (int) $n['completed_count'] ?></td>
              <td>
                <?php if ((int) $n['is_active'] === 1): ?>
                  <span class="badge bg-success">Active</span>
                <?php else: ?>
                  <span class="badge bg-danger">Deactivated</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ((int) $n['user_id'] === $admin_user_id): ?>
                  <span class="text-muted small">(you)</span>
                <?php else: ?>
                  <form method="post" action="ngos.php" class="d-inline"
                        onsubmit="return confirm('<?= (int) $n['is_active'] === 1 ? 'Deactivate' : 'Activate' ?> this NGO?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="toggle_active">
                    <input type="hidden" name="user_id" value="<?= (int) $n['user_id'] ?>">
                    <?php if ((int) $n['is_active'] === 1): ?>
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
