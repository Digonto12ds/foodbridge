<?php
// PICKUP MANAGEMENT (protected: require_role('admin'))
// - Every pickup is created automatically when a request is approved
//   (see requests.php); this page is for updating it afterward -
//   rescheduling date/time, or moving pickup_status forward
//   (Scheduled -> Picked Up -> Completed, or Cancelled)
require_once __DIR__ . '/../includes/role_check.php';
require_role('admin');

$errors = [];
$success = null;
$valid_pickup_statuses = ['Scheduled', 'Picked Up', 'Completed', 'Cancelled'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    verify_csrf();

    $pickup_id     = filter_input(INPUT_POST, 'pickup_id', FILTER_VALIDATE_INT);
    $pickup_date   = trim($_POST['pickup_date'] ?? '');
    $pickup_time   = trim($_POST['pickup_time'] ?? '');
    $pickup_status = $_POST['pickup_status'] ?? '';

    $date_ok = DateTime::createFromFormat('Y-m-d', $pickup_date) !== false;
    $time_ok = DateTime::createFromFormat('H:i', $pickup_time) !== false;

    if (!$pickup_id) {
        $errors[] = 'Invalid pickup.';
    } elseif (!$date_ok || !$time_ok) {
        $errors[] = 'A valid pickup date and time are required.';
    } elseif (!in_array($pickup_status, $valid_pickup_statuses, true)) {
        $errors[] = 'Invalid pickup status.';
    } else {
        $stmt = $pdo->prepare(
            'UPDATE pickups SET pickup_date = :date, pickup_time = :time, pickup_status = :status WHERE pickup_id = :id'
        );
        $stmt->execute([
            ':date'   => $pickup_date,
            ':time'   => $pickup_time,
            ':status' => $pickup_status,
            ':id'     => $pickup_id,
        ]);
        $success = 'Pickup updated.';
    }
}

$stmt = $pdo->query(
    "SELECT p.pickup_id, p.pickup_date, p.pickup_time, p.pickup_status,
            r.request_id, r.status AS request_status,
            d.food_name, d.unit, d.pickup_location,
            COALESCE(n.organization_name, u.name) AS ngo_display_name
     FROM pickups p
     JOIN requests r ON r.request_id = p.request_id
     JOIN donations d ON d.donation_id = r.donation_id
     JOIN ngos n ON n.ngo_id = r.ngo_id
     JOIN users u ON u.user_id = n.user_id
     ORDER BY p.pickup_date DESC, p.pickup_time DESC"
);
$pickups = $stmt->fetchAll();

$current_page = 'pickups';
$page_title = 'Pickup Management';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/_nav.php';
?>

<div class="container-fluid pb-5">
    <h3 class="mb-3">Pickup Management</h3>
    <p class="text-muted">Pickups are created automatically when a request is approved. Reschedule or update status here.</p>

    <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
    <?php if ($errors): ?>
      <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <?php if (!$pickups): ?>
      <div class="alert alert-light border">No pickups yet. Approve a request to schedule one.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-bordered bg-white align-middle">
          <thead class="table-light">
            <tr><th>Food</th><th>NGO</th><th>Pickup Location</th><th>Date</th><th>Time</th><th>Status</th><th>Request</th><th></th></tr>
          </thead>
          <tbody>
            <?php foreach ($pickups as $p): ?>
              <?php $formid = 'pickup-form-' . (int) $p['pickup_id']; ?>
              <tr>
                <td><?= e($p['food_name']) ?></td>
                <td><?= e($p['ngo_display_name']) ?></td>
                <td><?= e($p['pickup_location']) ?></td>
                <td><input type="date" name="pickup_date" form="<?= e($formid) ?>" class="form-control form-control-sm" value="<?= e($p['pickup_date']) ?>" required></td>
                <td><input type="time" name="pickup_time" form="<?= e($formid) ?>" class="form-control form-control-sm" value="<?= e(substr($p['pickup_time'], 0, 5)) ?>" required></td>
                <td>
                  <select name="pickup_status" form="<?= e($formid) ?>" class="form-select form-select-sm">
                    <?php foreach ($valid_pickup_statuses as $ps): ?>
                      <option value="<?= e($ps) ?>" <?= $p['pickup_status'] === $ps ? 'selected' : '' ?>><?= e($ps) ?></option>
                    <?php endforeach; ?>
                  </select>
                </td>
                <td><span class="badge <?= status_badge_class($p['request_status']) ?>"><?= e($p['request_status']) ?></span></td>
                <td>
                  <!-- Table cells can't contain a <form> that spans multiple <td>s
                       (invalid HTML), so this empty form lives here and every
                       control above references it via the form="..." attribute. -->
                  <form id="<?= e($formid) ?>" method="post" action="pickups.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="pickup_id" value="<?= (int) $p['pickup_id'] ?>">
                  </form>
                  <button type="submit" form="<?= e($formid) ?>" class="btn btn-sm btn-outline-primary">Save</button>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
</div>
</div><!-- /.admin-content -->
</div><!-- /.admin-main -->
</div><!-- /.admin-shell -->
<?php require __DIR__ . '/../includes/footer.php'; ?>
