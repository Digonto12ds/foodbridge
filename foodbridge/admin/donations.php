<?php
// DONATION MANAGEMENT (protected: require_role('admin'))
// - View every donation from every donor, filter by status
// - ?id=X shows a full detail card at the top (doubles as "view details",
//   no separate file needed)
// - Admin can force-cancel a donation regardless of which donor owns it
//   (e.g. inappropriate content) - donor pages only allow a donor to
//   cancel their own; this is the global override
require_once __DIR__ . '/../includes/role_check.php';
require_role('admin');

auto_expire_all_donations($pdo);

$errors = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    $donation_id = filter_input(INPUT_POST, 'donation_id', FILTER_VALIDATE_INT);
    if ($donation_id) {
        $stmt = $pdo->prepare(
            "UPDATE donations SET status = 'Cancelled'
             WHERE donation_id = :id AND status NOT IN ('Completed', 'Cancelled')"
        );
        $stmt->execute([':id' => $donation_id]);
        $success = $stmt->rowCount() > 0
            ? 'Donation cancelled.'
            : null;
        if (!$success) {
            $errors[] = 'That donation could not be cancelled (already completed or cancelled).';
        }
    }
}

$valid_statuses = ['Available', 'Requested', 'Claimed', 'Completed', 'Expired', 'Cancelled'];
$status_filter = $_GET['status'] ?? 'all';
if ($status_filter !== 'all' && !in_array($status_filter, $valid_statuses, true)) {
    $status_filter = 'all';
}

$sql = "SELECT d.*, c.category_name, COALESCE(don.organization_name, u.name) AS donor_display_name
        FROM donations d
        JOIN categories c ON c.category_id = d.category_id
        JOIN donors don ON don.donor_id = d.donor_id
        JOIN users u ON u.user_id = don.user_id";
$params = [];
if ($status_filter !== 'all') {
    $sql .= ' WHERE d.status = :status';
    $params[':status'] = $status_filter;
}
$sql .= ' ORDER BY d.created_at DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$donations = $stmt->fetchAll();

// Optional detail card for one donation, via ?id=
$detail = null;
$view_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if ($view_id) {
    $stmt = $pdo->prepare(
        "SELECT d.*, c.category_name, COALESCE(don.organization_name, u.name) AS donor_display_name, u.phone AS donor_phone
         FROM donations d
         JOIN categories c ON c.category_id = d.category_id
         JOIN donors don ON don.donor_id = d.donor_id
         JOIN users u ON u.user_id = don.user_id
         WHERE d.donation_id = :id
         LIMIT 1"
    );
    $stmt->execute([':id' => $view_id]);
    $detail = $stmt->fetch();
}

$current_page = 'donations';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Donation Management - <?= e(SITE_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php require __DIR__ . '/_nav.php'; ?>

<div class="container-fluid pb-5">
    <h3 class="mb-3">Donation Management</h3>

    <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
    <?php if ($errors): ?>
      <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <?php if ($detail): ?>
      <div class="card shadow-sm mb-4">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start mb-2">
            <h5 class="mb-0"><?= e($detail['food_name']) ?> <small class="text-muted">#<?= (int) $detail['donation_id'] ?></small></h5>
            <span class="badge fs-6 <?= status_badge_class($detail['status']) ?>"><?= e($detail['status']) ?></span>
          </div>
          <dl class="row mb-2">
            <dt class="col-sm-2">Category</dt><dd class="col-sm-4"><?= e($detail['category_name']) ?></dd>
            <dt class="col-sm-2">Quantity</dt><dd class="col-sm-4"><?= e(format_qty((float) $detail['quantity'], $detail['unit'])) ?></dd>
            <dt class="col-sm-2">Donor</dt><dd class="col-sm-4"><?= e($detail['donor_display_name']) ?> (<?= e($detail['donor_phone']) ?>)</dd>
            <dt class="col-sm-2">Pickup Location</dt><dd class="col-sm-4"><?= e($detail['pickup_location']) ?></dd>
            <dt class="col-sm-2">Prepared</dt><dd class="col-sm-4"><?= $detail['prepared_time'] ? e(date('d M Y, h:i A', strtotime($detail['prepared_time']))) : '-' ?></dd>
            <dt class="col-sm-2">Expiry</dt><dd class="col-sm-4"><?= e(date('d M Y, h:i A', strtotime($detail['expiry_time']))) ?></dd>
            <dt class="col-sm-2">Description</dt><dd class="col-sm-10"><?= $detail['description'] !== null ? nl2br(e($detail['description'])) : '<span class="text-muted">-</span>' ?></dd>
          </dl>
          <div class="d-flex gap-2">
            <?php if (!in_array($detail['status'], ['Completed', 'Cancelled'], true)): ?>
              <form method="post" action="donations.php?status=<?= e($status_filter) ?>"
                    onsubmit="return confirm('Cancel this donation? Use this for inappropriate or problem listings.');">
                <input type="hidden" name="action" value="cancel">
                <input type="hidden" name="donation_id" value="<?= (int) $detail['donation_id'] ?>">
                <button type="submit" class="btn btn-outline-danger">Cancel Donation</button>
              </form>
            <?php endif; ?>
            <a href="donations.php?status=<?= e($status_filter) ?>" class="btn btn-outline-secondary">Close Details</a>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <ul class="nav nav-pills mb-3 flex-wrap">
      <?php foreach (array_merge(['all' => 'All'], array_combine($valid_statuses, $valid_statuses)) as $key => $label): ?>
        <li class="nav-item">
          <a class="nav-link <?= $status_filter === $key ? 'active' : '' ?>" href="donations.php?status=<?= e($key) ?>"><?= e($label) ?></a>
        </li>
      <?php endforeach; ?>
    </ul>

    <div class="table-responsive">
      <table class="table table-bordered bg-white align-middle">
        <thead class="table-light">
          <tr><th>Food Name</th><th>Category</th><th>Donor</th><th>Quantity</th><th>Expiry</th><th>Status</th><th>Created</th><th></th></tr>
        </thead>
        <tbody>
          <?php if (!$donations): ?>
            <tr><td colspan="8" class="text-center text-muted">No donations match this filter.</td></tr>
          <?php endif; ?>
          <?php foreach ($donations as $d): ?>
            <tr>
              <td><?= e($d['food_name']) ?></td>
              <td><?= e($d['category_name']) ?></td>
              <td><?= e($d['donor_display_name']) ?></td>
              <td><?= e(format_qty((float) $d['quantity'], $d['unit'])) ?></td>
              <td><?= e(date('d M Y, h:i A', strtotime($d['expiry_time']))) ?></td>
              <td><span class="badge <?= status_badge_class($d['status']) ?>"><?= e($d['status']) ?></span></td>
              <td><?= e(date('d M Y', strtotime($d['created_at']))) ?></td>
              <td><a href="donations.php?id=<?= (int) $d['donation_id'] ?>&status=<?= e($status_filter) ?>" class="btn btn-sm btn-outline-primary">View</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
</div>
</body>
</html>
