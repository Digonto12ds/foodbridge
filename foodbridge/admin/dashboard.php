<?php
// ADMIN DASHBOARD (protected: require_role('admin'))
// - System-wide statistics across every table
require_once __DIR__ . '/../includes/role_check.php';
require_role('admin');

auto_expire_all_donations($pdo);

$stats = [];
$stats['total_users']  = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$stats['total_donors'] = (int) $pdo->query('SELECT COUNT(*) FROM donors')->fetchColumn();
$stats['total_ngos']   = (int) $pdo->query('SELECT COUNT(*) FROM ngos')->fetchColumn();

$stats['total_donations']     = (int) $pdo->query('SELECT COUNT(*) FROM donations')->fetchColumn();
$stats['available_donations'] = (int) $pdo->query("SELECT COUNT(*) FROM donations WHERE status = 'Available'")->fetchColumn();
$stats['completed_donations'] = (int) $pdo->query("SELECT COUNT(*) FROM donations WHERE status = 'Completed'")->fetchColumn();

$stats['pending_requests']  = (int) $pdo->query("SELECT COUNT(*) FROM requests WHERE status = 'Pending'")->fetchColumn();
$stats['approved_requests'] = (int) $pdo->query("SELECT COUNT(*) FROM requests WHERE status = 'Approved'")->fetchColumn();

$stats['total_beneficiaries'] = (int) $pdo->query('SELECT COALESCE(SUM(beneficiary_count), 0) FROM distributions')->fetchColumn();

$current_page = 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Dashboard - <?= e(SITE_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php require __DIR__ . '/_nav.php'; ?>

<div class="container-fluid pb-5">
    <h2 class="mb-1">Welcome, <?= e(current_user_name()) ?></h2>
    <p class="text-muted mb-4">System-wide overview.</p>

    <div class="row g-3">
        <?php
        $cards = [
            ['Total Users', $stats['total_users'], 'dark'],
            ['Total Donors', $stats['total_donors'], 'success'],
            ['Total NGOs', $stats['total_ngos'], 'primary'],
            ['Total Donations', $stats['total_donations'], 'dark'],
            ['Available Donations', $stats['available_donations'], 'success'],
            ['Pending Requests', $stats['pending_requests'], 'warning'],
            ['Approved Requests', $stats['approved_requests'], 'info'],
            ['Completed Donations', $stats['completed_donations'], 'primary'],
            ['Total Beneficiaries', $stats['total_beneficiaries'], 'secondary'],
        ];
        foreach ($cards as [$label, $value, $color]):
        ?>
        <div class="col-6 col-md-4 col-lg-3">
            <div class="card text-center shadow-sm h-100 border-<?= e($color) ?>">
                <div class="card-body">
                    <div class="text-muted small"><?= e($label) ?></div>
                    <div class="fs-3 fw-bold text-<?= e($color) ?>"><?= $value ?></div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="mt-4 d-flex flex-wrap gap-2">
        <a href="requests.php" class="btn btn-warning">Review Pending Requests</a>
        <a href="donations.php" class="btn btn-outline-dark">View All Donations</a>
        <a href="users.php" class="btn btn-outline-dark">Manage Users</a>
        <a href="reports.php" class="btn btn-outline-dark">View Reports</a>
    </div>
</div>
</body>
</html>
