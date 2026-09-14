<?php
// AVAILABLE FOOD (protected: require_role('ngo'))
// - Donations where status = 'Available' AND expiry_time has not passed
// - Marketplace-wide (every donor), with search + category + expiry filters
// - Reads through v_donation_details (the donations+categories+donors+users
//   join, defined once in database/advanced_features.sql) and, for the
//   plain "browse everything, maybe one category" case, through
//   sp_get_available_donations() built on top of that same view
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

// ---- Fetch: the stored procedure for the common case, a query against
//      the same view for anything the procedure's fixed signature can't
//      express (free-text search, an expiry window) ----
if ($search === '' && $expiry === 'any') {
    // "Available, optionally one category" is the single most-run read
    // in the app - this is exactly sp_get_available_donations() (see
    // database/advanced_features.sql), so let the database do it.
    $stmt = $pdo->prepare('CALL sp_get_available_donations(:category_id)');
    $stmt->execute([':category_id' => $category_id]);
    $donations = $stmt->fetchAll();
    $stmt->closeCursor(); // required after CALL before this connection runs another query
} else {
    // Search and/or an expiry window is active - a fixed procedure
    // signature can't cleanly express arbitrary optional filters, so
    // build a normal parameterized query against the same view instead.
    $sql = "SELECT * FROM v_donation_details WHERE status = 'Available' AND expiry_time > NOW()";
    $params = [];

    if ($search !== '') {
        $sql .= ' AND food_name LIKE :search';
        // Escape LIKE wildcards the user typed so "50%" or "a_b" search literally.
        $escaped = addcslashes($search, '%_\\');
        $params[':search'] = '%' . $escaped . '%';
    }
    if ($category_id !== null) {
        $sql .= ' AND category_id = :category_id';
        $params[':category_id'] = $category_id;
    }
    if ($expiry !== 'any') {
        $sql .= ' AND expiry_time <= DATE_ADD(NOW(), INTERVAL :expiry_hours HOUR)';
        $params[':expiry_hours'] = $expiry_hours_map[$expiry];
    }

    $sql .= ' ORDER BY expiry_time ASC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $donations = $stmt->fetchAll();
}

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
