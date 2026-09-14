<?php
// NGO DASHBOARD (protected: require_role('ngo'))
// - Summary cards: available food (marketplace-wide), my pending/approved/
//   completed requests, total food received
require_once __DIR__ . '/../includes/role_check.php';
require_role('ngo');

$ngo_id = get_ngo_id($pdo, current_user_id());
if ($ngo_id === null) {
    die('NGO profile not found for this account. Please contact support.');
}

auto_expire_all_donations($pdo);

// Available food is marketplace-wide (every donor), not scoped to this NGO.
$available_food_count = (int) $pdo->query(
    "SELECT COUNT(*) FROM donations WHERE status = 'Available' AND expiry_time > NOW()"
)->fetchColumn();

// This NGO's own request counts, grouped in one query.
$stmt = $pdo->prepare('SELECT status, COUNT(*) AS cnt FROM requests WHERE ngo_id = :ngo_id GROUP BY status');
$stmt->execute([':ngo_id' => $ngo_id]);
$request_counts = ['Pending' => 0, 'Approved' => 0, 'Rejected' => 0, 'Completed' => 0, 'Cancelled' => 0];
foreach ($stmt->fetchAll() as $row) {
    $request_counts[$row['status']] = (int) $row['cnt'];
}

// Total food received: sum of quantity_distributed, grouped by unit
// (units differ per donation - kg, pieces, packets - so they can't be
// added together into one number).
$stmt = $pdo->prepare(
    'SELECT don.unit, SUM(dist.quantity_distributed) AS total
     FROM distributions dist
     JOIN requests r ON r.request_id = dist.request_id
     JOIN donations don ON don.donation_id = r.donation_id
     WHERE r.ngo_id = :ngo_id
     GROUP BY don.unit'
);
$stmt->execute([':ngo_id' => $ngo_id]);
$received_by_unit = $stmt->fetchAll();

$current_page = 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>NGO Dashboard - <?= e(SITE_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php require __DIR__ . '/_nav.php'; ?>

<div class="container pb-5">
    <h2 class="mb-1">Welcome, <?= e(current_user_name()) ?></h2>
    <p class="text-muted mb-4">Here's an overview of food available and your requests.</p>

    <div class="row g-3">
        <div class="col-6 col-md-4 col-lg-2">
            <div class="card text-center shadow-sm h-100 border-success">
                <div class="card-body">
                    <div class="text-muted small">Available Food</div>
                    <div class="fs-3 fw-bold text-success"><?= $available_food_count ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <div class="card text-center shadow-sm h-100 border-warning">
                <div class="card-body">
                    <div class="text-muted small">My Pending</div>
                    <div class="fs-3 fw-bold text-warning"><?= $request_counts['Pending'] ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <div class="card text-center shadow-sm h-100 border-info">
                <div class="card-body">
                    <div class="text-muted small">Approved</div>
                    <div class="fs-3 fw-bold text-info"><?= $request_counts['Approved'] ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <div class="card text-center shadow-sm h-100 border-primary">
                <div class="card-body">
                    <div class="text-muted small">Completed</div>
                    <div class="fs-3 fw-bold text-primary"><?= $request_counts['Completed'] ?></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-4">
            <div class="card text-center shadow-sm h-100 border-secondary">
                <div class="card-body">
                    <div class="text-muted small">Total Food Received</div>
                    <?php if (!$received_by_unit): ?>
                        <div class="fs-5 text-muted">None yet</div>
                    <?php else: ?>
                        <div class="fs-5 fw-bold">
                            <?php foreach ($received_by_unit as $r): ?>
                                <?= e(rtrim(rtrim(number_format((float) $r['total'], 2), '0'), '.')) ?> <?= e($r['unit']) ?><br>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-4 d-flex gap-2">
        <a href="available_food.php" class="btn btn-primary">Browse Available Food</a>
        <a href="my_requests.php" class="btn btn-outline-primary">View My Requests</a>
    </div>
</div>
</body>
</html>
