<?php
// DONOR DASHBOARD (protected: require_role('donor'))
// - Summary cards: total donations, pending, picked up, cancelled
// - Quick links to add_donation.php and my_donations.php
require_once __DIR__ . '/../includes/role_check.php';
require_role('donor');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Donor Dashboard - <?= e(SITE_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <h2>Welcome, <?= e(current_user_name()) ?> (Donor)</h2>
    <p class="text-muted">You are logged in. Donor features (add donation, donation history, etc.) will be built next.</p>
    <a href="<?= e(BASE_URL) ?>logout.php" class="btn btn-outline-danger btn-sm">Logout</a>
</div>
</body>
</html>
