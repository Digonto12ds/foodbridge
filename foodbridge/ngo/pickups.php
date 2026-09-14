<?php
// PICKUPS (protected: require_role('ngo'))
// - Approved requests belonging to this NGO, with pickup info where a
//   pickup has been scheduled (LEFT JOIN, since scheduling a pickup is
//   a future donor/admin action, not something this module creates)
require_once __DIR__ . '/../includes/role_check.php';
require_role('ngo');

$ngo_id = get_ngo_id($pdo, current_user_id());
if ($ngo_id === null) {
    die('NGO profile not found for this account. Please contact support.');
}

$stmt = $pdo->prepare(
    "SELECT r.request_id, r.requested_quantity, r.status AS request_status,
            d.food_name, d.unit, d.pickup_location,
            p.pickup_date, p.pickup_time, p.pickup_status
     FROM requests r
     JOIN donations d ON d.donation_id = r.donation_id
     LEFT JOIN pickups p ON p.request_id = r.request_id
     WHERE r.ngo_id = :ngo_id AND r.status IN ('Approved', 'Completed')
     ORDER BY r.request_date DESC"
);
$stmt->execute([':ngo_id' => $ngo_id]);
$pickups = $stmt->fetchAll();

$current_page = 'pickups';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pickups - <?= e(SITE_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php require __DIR__ . '/_nav.php'; ?>

<div class="container pb-5">
    <h3 class="mb-3">Pickups</h3>
    <p class="text-muted">Approved requests and their pickup scheduling status.</p>

    <?php if (!$pickups): ?>
      <div class="alert alert-light border">No approved requests yet. Once a donor approves one of your requests, it will appear here.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-bordered bg-white align-middle">
          <thead class="table-light">
            <tr>
              <th>Food</th>
              <th>Quantity</th>
              <th>Pickup Location</th>
              <th>Pickup Date/Time</th>
              <th>Pickup Status</th>
              <th>Request Status</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($pickups as $p): ?>
              <tr>
                <td><?= e($p['food_name']) ?></td>
                <td><?= e(rtrim(rtrim(number_format((float) $p['requested_quantity'], 2), '0'), '.')) ?> <?= e($p['unit']) ?></td>
                <td><?= e($p['pickup_location']) ?></td>
                <td>
                  <?php if ($p['pickup_date'] !== null): ?>
                    <?= e(date('d M Y', strtotime($p['pickup_date']))) ?> at <?= e(date('h:i A', strtotime($p['pickup_time']))) ?>
                  <?php else: ?>
                    <span class="text-muted">Not yet scheduled</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ($p['pickup_status'] !== null): ?>
                    <span class="badge <?= status_badge_class($p['pickup_status']) ?>"><?= e($p['pickup_status']) ?></span>
                  <?php else: ?>
                    <span class="text-muted">-</span>
                  <?php endif; ?>
                </td>
                <td><span class="badge <?= status_badge_class($p['request_status']) ?>"><?= e($p['request_status']) ?></span></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
</div>
</body>
</html>
