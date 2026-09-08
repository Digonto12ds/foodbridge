<?php
// NGO DASHBOARD (protected: require_role('ngo'))
// - Summary cards: total requests, pending, approved, completed pickups
// - Quick links to browse_donations.php and my_requests.php
require_once __DIR__ . '/../includes/role_check.php';
require_role('ngo');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>NGO Dashboard - <?= e(SITE_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <h2>Welcome, <?= e(current_user_name()) ?> (NGO)</h2>
    <p class="text-muted">You are logged in. NGO features (browse donations, my requests, etc.) will be built next.</p>
    <a href="<?= e(BASE_URL) ?>logout.php" class="btn btn-outline-danger btn-sm">Logout</a>
</div>
</body>
</html>
