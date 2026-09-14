<?php
// DONOR DASHBOARD (protected: require_role('donor'))
// - Summary cards: total donations, available, requested, completed, expired
// - Quick links to add_donation.php and my_donations.php
require_once __DIR__ . '/../includes/role_check.php';
require_role('donor');

$donor_id = get_donor_id($pdo, current_user_id());

// A donor row should always exist for a 'donor' role account (created at
// registration), but guard anyway rather than trust that blindly.
if ($donor_id === null) {
    die('Donor profile not found for this account. Please contact support.');
}

// Keep status accurate before counting ("Expired donations cannot be requested").
auto_expire_donations($pdo, $donor_id);

// One grouped query for all status counts, instead of five separate queries.
$stmt = $pdo->prepare(
    'SELECT status, COUNT(*) AS cnt FROM donations WHERE donor_id = :donor_id GROUP BY status'
);
$stmt->execute([':donor_id' => $donor_id]);

$counts = [
    'Available' => 0,
    'Requested' => 0,
    'Claimed'   => 0,
    'Completed' => 0,
    'Expired'   => 0,
    'Cancelled' => 0,
];
$total = 0;
foreach ($stmt->fetchAll() as $row) {
    $counts[$row['status']] = (int) $row['cnt'];
    $total += (int) $row['cnt'];
}

$current_page = 'dashboard';
$page_title = 'Donor Dashboard';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/_nav.php';
?>

<div class="container pb-5">
    <h2 class="mb-1">Welcome, <?= e(current_user_name()) ?></h2>
    <p class="text-muted mb-4">Here's an overview of your donations.</p>

    <div class="row g-3">
        <div class="col-6 col-md-4 col-lg-2">
            <div class="card text-center shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Total Donations</div>
                    <div class="fs-3 fw-bold"><?= $total ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <div class="card text-center shadow-sm h-100 border-success">
                <div class="card-body">
                    <div class="text-muted small">Available</div>
                    <div class="fs-3 fw-bold text-success"><?= $counts['Available'] ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <div class="card text-center shadow-sm h-100 border-warning">
                <div class="card-body">
                    <div class="text-muted small">Requested</div>
                    <div class="fs-3 fw-bold text-warning"><?= $counts['Requested'] ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <div class="card text-center shadow-sm h-100 border-primary">
                <div class="card-body">
                    <div class="text-muted small">Completed</div>
                    <div class="fs-3 fw-bold text-primary"><?= $counts['Completed'] ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <div class="card text-center shadow-sm h-100 border-secondary">
                <div class="card-body">
                    <div class="text-muted small">Expired</div>
                    <div class="fs-3 fw-bold text-secondary"><?= $counts['Expired'] ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <div class="card text-center shadow-sm h-100 border-danger">
                <div class="card-body">
                    <div class="text-muted small">Cancelled</div>
                    <div class="fs-3 fw-bold text-danger"><?= $counts['Cancelled'] ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-4 d-flex gap-2">
        <a href="add_donation.php" class="btn btn-success">+ Add Donation</a>
        <a href="my_donations.php" class="btn btn-outline-success">View My Donations</a>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
