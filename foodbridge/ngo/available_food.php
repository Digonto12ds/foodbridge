<?php
// AVAILABLE FOOD (protected: require_role('ngo'))
// - Donations where status = 'Available' AND expiry_time has not passed
// - Marketplace-wide (every donor), with search + category + expiry filters
require_once __DIR__ . '/../includes/role_check.php';
require_role('ngo');

auto_expire_all_donations($pdo);

$categories = $pdo->query('SELECT category_id, category_name FROM categories ORDER BY category_name')->fetchAll();

// ---- Read + validate filters from the query string ----
$search      = sanitize_input($_GET['search'] ?? '');
$category_id = filter_input(INPUT_GET, 'category_id', FILTER_VALIDATE_INT) ?: null;
$expiry      = $_GET['expiry'] ?? 'any';

$valid_category_ids = array_column($categories, 'category_id');
if ($category_id !== null && !in_array($category_id, $valid_category_ids, true)) {
    $category_id = null; // ignore a tampered/unknown category_id rather than error
}

$expiry_hours_map = ['24h' => 24, '3d' => 72, '7d' => 168];
$valid_expiry = array_merge(['any'], array_keys($expiry_hours_map));
if (!in_array($expiry, $valid_expiry, true)) {
    $expiry = 'any';
}

// ---- Build the query safely: fixed SQL fragments + bound parameters only ----
$sql = "SELECT d.donation_id, d.food_name, d.quantity, d.unit, d.expiry_time, d.pickup_location,
               c.category_name, COALESCE(don.organization_name, u.name) AS donor_display_name
        FROM donations d
        JOIN categories c ON c.category_id = d.category_id
        JOIN donors don ON don.donor_id = d.donor_id
        JOIN users u ON u.user_id = don.user_id
        WHERE d.status = 'Available' AND d.expiry_time > NOW()";
$params = [];

if ($search !== '') {
    $sql .= ' AND d.food_name LIKE :search';
    // Escape LIKE wildcards the user typed so "50%" or "a_b" search literally.
    $escaped = addcslashes($search, '%_\\');
    $params[':search'] = '%' . $escaped . '%';
}
if ($category_id !== null) {
    $sql .= ' AND d.category_id = :category_id';
    $params[':category_id'] = $category_id;
}
if ($expiry !== 'any') {
    $sql .= ' AND d.expiry_time <= DATE_ADD(NOW(), INTERVAL :expiry_hours HOUR)';
    $params[':expiry_hours'] = $expiry_hours_map[$expiry];
}

$sql .= ' ORDER BY d.expiry_time ASC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$donations = $stmt->fetchAll();

$current_page = 'available_food';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Available Food - <?= e(SITE_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php require __DIR__ . '/_nav.php'; ?>

<div class="container pb-5">
    <h3 class="mb-3">Available Food</h3>

    <form method="get" action="available_food.php" class="row g-2 mb-4">
        <div class="col-md-5">
            <input type="text" class="form-control" name="search" placeholder="Search food name..."
                   value="<?= e($search) ?>">
        </div>
        <div class="col-md-3">
            <select class="form-select" name="category_id">
                <option value="">All categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= (int) $cat['category_id'] ?>"
                        <?= $category_id === (int) $cat['category_id'] ? 'selected' : '' ?>>
                        <?= e($cat['category_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <select class="form-select" name="expiry">
                <option value="any" <?= $expiry === 'any' ? 'selected' : '' ?>>Any expiry</option>
                <option value="24h" <?= $expiry === '24h' ? 'selected' : '' ?>>Expiring within 24 hours</option>
                <option value="3d"  <?= $expiry === '3d'  ? 'selected' : '' ?>>Expiring within 3 days</option>
                <option value="7d"  <?= $expiry === '7d'  ? 'selected' : '' ?>>Expiring within 7 days</option>
            </select>
        </div>
        <div class="col-md-1">
            <button type="submit" class="btn btn-primary w-100">Go</button>
        </div>
    </form>

    <?php if (!$donations): ?>
      <div class="alert alert-light border">No available food matches your filters right now.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-bordered bg-white align-middle">
          <thead class="table-light">
            <tr>
              <th>Food Name</th>
              <th>Category</th>
              <th>Quantity</th>
              <th>Expiry</th>
              <th>Pickup Location</th>
              <th>Donor</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($donations as $d): ?>
              <tr>
                <td><?= e($d['food_name']) ?></td>
                <td><?= e($d['category_name']) ?></td>
                <td><?= e(rtrim(rtrim(number_format((float) $d['quantity'], 2), '0'), '.')) ?> <?= e($d['unit']) ?></td>
                <td><?= e(date('d M Y, h:i A', strtotime($d['expiry_time']))) ?></td>
                <td><?= e($d['pickup_location']) ?></td>
                <td><?= e($d['donor_display_name']) ?></td>
                <td><a href="food_details.php?id=<?= (int) $d['donation_id'] ?>" class="btn btn-sm btn-primary">View / Request</a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
</div>
</body>
</html>
