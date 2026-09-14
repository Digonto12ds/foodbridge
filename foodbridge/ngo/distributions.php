<?php
// DISTRIBUTIONS (protected: require_role('ngo'))
// - Completed food distributions belonging to this NGO
require_once __DIR__ . '/../includes/role_check.php';
require_role('ngo');

$ngo_id = get_ngo_id($pdo, current_user_id());
if ($ngo_id === null) {
    die('NGO profile not found for this account. Please contact support.');
}

$stmt = $pdo->prepare(
    'SELECT dist.distribution_id, dist.quantity_distributed, dist.distribution_date,
            dist.beneficiary_count, dist.location, dist.notes,
            d.food_name, d.unit
     FROM distributions dist
     JOIN requests r ON r.request_id = dist.request_id
     JOIN donations d ON d.donation_id = r.donation_id
     WHERE r.ngo_id = :ngo_id
     ORDER BY dist.distribution_date DESC'
);
$stmt->execute([':ngo_id' => $ngo_id]);
$distributions = $stmt->fetchAll();

$current_page = 'distributions';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Distributions - <?= e(SITE_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php require __DIR__ . '/_nav.php'; ?>

<div class="container pb-5">
    <h3 class="mb-3">Distributions</h3>
    <p class="text-muted">Food your organization has received and distributed to beneficiaries.</p>

    <?php if (!$distributions): ?>
      <div class="alert alert-light border">No completed distributions yet.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-bordered bg-white align-middle">
          <thead class="table-light">
            <tr>
              <th>Food</th>
              <th>Quantity Distributed</th>
              <th>Distribution Date</th>
              <th>Beneficiaries</th>
              <th>Location</th>
              <th>Notes</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($distributions as $d): ?>
              <tr>
                <td><?= e($d['food_name']) ?></td>
                <td><?= e(rtrim(rtrim(number_format((float) $d['quantity_distributed'], 2), '0'), '.')) ?> <?= e($d['unit']) ?></td>
                <td><?= e(date('d M Y, h:i A', strtotime($d['distribution_date']))) ?></td>
                <td><?= (int) $d['beneficiary_count'] ?></td>
                <td><?= e($d['location']) ?></td>
                <td><?= $d['notes'] !== null ? e($d['notes']) : '<span class="text-muted">-</span>' ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
</div>
</body>
</html>
