<?php
// ADMIN DASHBOARD (protected: require_role('admin'))
// - Overview stats: total donors, NGOs, donations, pending requests, etc.
// - Links to all management pages below
require_once __DIR__ . '/../includes/role_check.php';
require_role('admin');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - <?= e(SITE_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <h2>Welcome, <?= e(current_user_name()) ?> (Admin)</h2>
    <p class="text-muted">You are logged in. Admin management pages will be built next.</p>
    <a href="<?= e(BASE_URL) ?>logout.php" class="btn btn-outline-danger btn-sm">Logout</a>
</div>
</body>
</html>
