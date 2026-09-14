<?php
// DONOR MANAGEMENT (protected: require_role('admin'))
// - Donor-specific view: organization info + donation activity, with
//   the same activate/deactivate control as users.php (acts on the
//   underlying user account, since donors are 1:1 with users)
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
            'activated'   => ['success', 'Donor activated.'],
            'deactivated' => ['success', 'Donor deactivated.'],
            'self'        => ['danger', 'You cannot deactivate your own account.'],
        };
    }
}

$stmt = $pdo->query(
    "SELECT u.user_id, u.name, u.email, u.phone, u.address, u.is_active,
            d.donor_id, d.organization_name, d.donor_type,
            (SELECT COUNT(*) FROM donations WHERE donor_id = d.donor_id) AS donation_count,
            (SELECT COUNT(*) FROM donations WHERE donor_id = d.donor_id AND status = 'Completed') AS completed_count
     FROM donors d
     JOIN users u ON u.user_id = d.user_id
     ORDER BY d.donor_id"
);
$donors = $stmt->fetchAll();

$current_page = 'donors';
$page_title = 'Donor Management';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/_nav.php';
?>

<div class="container-fluid pb-5">
    <h3 class="mb-3">Donor Management</h3>

    <?php if ($flash): ?>
      <div class="alert alert-<?= e($flash[0]) ?>"><?= e($flash[1]) ?></div>
    <?php endif; ?>

    <div class="table-responsive">
      <table class="table table-bordered bg-white align-middle">
        <thead class="table-light">
          <tr>
            <th>Name</th><th>Email</th><th>Phone</th><th>Organization</th><th>Type</th>
            <th>Donations</th><th>Completed</th><th>Status</th><th></th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$donors): ?>
            <tr><td colspan="9" class="text-center text-muted">No donors yet.</td></tr>
          <?php endif; ?>
          <?php foreach ($donors as $d): ?>
            <tr>
              <td><?= e($d['name']) ?></td>
              <td><?= e($d['email']) ?></td>
              <td><?= e($d['phone']) ?></td>
              <td><?= $d['organization_name'] !== null ? e($d['organization_name']) : '<span class="text-muted">Individual</span>' ?></td>
              <td><?= e($d['donor_type']) ?></td>
              <td><?= (int) $d['donation_count'] ?></td>
              <td><?= (int) $d['completed_count'] ?></td>
              <td>
                <?php if ((int) $d['is_active'] === 1): ?>
                  <span class="badge bg-success">Active</span>
                <?php else: ?>
                  <span class="badge bg-danger">Deactivated</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ((int) $d['user_id'] === $admin_user_id): ?>
                  <span class="text-muted small">(you)</span>
                <?php else: ?>
                  <form method="post" action="donors.php" class="d-inline"
                        onsubmit="return confirm('<?= (int) $d['is_active'] === 1 ? 'Deactivate' : 'Activate' ?> this donor?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="toggle_active">
                    <input type="hidden" name="user_id" value="<?= (int) $d['user_id'] ?>">
                    <?php if ((int) $d['is_active'] === 1): ?>
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
