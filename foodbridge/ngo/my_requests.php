<?php
// MY REQUESTS (protected: require_role('ngo'))
// - Requests belonging only to the logged-in NGO
// - Display: Food, Requested quantity, Request date, Status
require_once __DIR__ . '/../includes/role_check.php';
require_role('ngo');

$ngo_id = get_ngo_id($pdo, current_user_id());
if ($ngo_id === null) {
    die('NGO profile not found for this account. Please contact support.');
}

$stmt = $pdo->prepare(
    'SELECT r.request_id, r.requested_quantity, r.request_date, r.status,
            d.donation_id, d.food_name, d.unit
     FROM requests r
     JOIN donations d ON d.donation_id = r.donation_id
     WHERE r.ngo_id = :ngo_id
     ORDER BY r.request_date DESC'
);
$stmt->execute([':ngo_id' => $ngo_id]);
$requests = $stmt->fetchAll();

$msg = ($_GET['msg'] ?? '') === 'requested' ? 'Request submitted successfully. Awaiting the donor\'s approval.' : null;

$current_page = 'my_requests';
$page_title = 'My Requests';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/_nav.php';
?>

<div class="container pb-5">
    <h3 class="mb-3">My Requests</h3>

    <?php if ($msg): ?>
      <div class="alert alert-success"><?= e($msg) ?></div>
    <?php endif; ?>

    <?php if (!$requests): ?>
      <div class="alert alert-light border">You haven't requested any food yet. <a href="available_food.php">Browse available food</a>.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-bordered bg-white align-middle">
          <thead class="table-light">
            <tr>
              <th>Food</th>
              <th>Requested Quantity</th>
              <th>Request Date</th>
              <th>Status</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($requests as $r): ?>
              <tr>
                <td><?= e($r['food_name']) ?></td>
                <td><?= e(rtrim(rtrim(number_format((float) $r['requested_quantity'], 2), '0'), '.')) ?> <?= e($r['unit']) ?></td>
                <td><?= e(date('d M Y, h:i A', strtotime($r['request_date']))) ?></td>
                <td><span class="badge <?= status_badge_class($r['status']) ?>"><?= e($r['status']) ?></span></td>
                <td><a href="food_details.php?id=<?= (int) $r['donation_id'] ?>" class="btn btn-sm btn-outline-primary">View Food</a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
