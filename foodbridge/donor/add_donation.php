<?php
// ADD NEW FOOD DONATION FORM (protected: require_role('donor'))
// - Form fields: food name, category, description, quantity, unit,
//   prepared time, expiry time, pickup location
// - On submit: INSERT into `donations` with this donor's donor_id,
//   status hardcoded to 'Available'
require_once __DIR__ . '/../includes/role_check.php';
require_role('donor');

$donor_id = get_donor_id($pdo, current_user_id());
if ($donor_id === null) {
    die('Donor profile not found for this account. Please contact support.');
}

// Categories come from the DB so the dropdown can never offer an invalid one.
$categories = $pdo->query('SELECT category_id, category_name FROM categories ORDER BY category_name')->fetchAll();

$errors = [];
$old = [
    'category_id'     => '',
    'food_name'       => '',
    'description'     => '',
    'quantity'        => '',
    'unit'            => '',
    'prepared_time'   => '',
    'expiry_time'     => '',
    'pickup_location' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old['category_id']     = $_POST['category_id'] ?? '';
    $old['food_name']       = sanitize_input($_POST['food_name'] ?? '');
    $old['description']     = sanitize_input($_POST['description'] ?? '');
    $old['quantity']        = trim($_POST['quantity'] ?? '');
    $old['unit']            = sanitize_input($_POST['unit'] ?? '');
    $old['prepared_time']   = trim($_POST['prepared_time'] ?? '');
    $old['expiry_time']     = trim($_POST['expiry_time'] ?? '');
    $old['pickup_location'] = sanitize_input($_POST['pickup_location'] ?? '');

    // ---- Validation ----
    if ($old['food_name'] === '' || mb_strlen($old['food_name']) > 100) {
        $errors[] = 'Food name is required (max 100 characters).';
    }

    $category_id = filter_var($old['category_id'], FILTER_VALIDATE_INT);
    $valid_category_ids = array_column($categories, 'category_id');
    if ($category_id === false || !in_array($category_id, $valid_category_ids, true)) {
        $errors[] = 'Please select a valid category.';
    }

    // "Do not allow invalid quantities."
    if (!is_numeric($old['quantity']) || (float) $old['quantity'] <= 0) {
        $errors[] = 'Quantity must be a number greater than 0.';
    }

    if ($old['unit'] === '' || mb_strlen($old['unit']) > 20) {
        $errors[] = 'Unit is required (e.g. kg, pieces, packets - max 20 characters).';
    }

    if ($old['pickup_location'] === '' || mb_strlen($old['pickup_location']) > 255) {
        $errors[] = 'Pickup location is required (max 255 characters).';
    }

    // Prepared time is optional; expiry time is required.
    $prepared_sql = null;
    if ($old['prepared_time'] !== '') {
        $prepared_sql = parse_datetime_local($old['prepared_time']);
        if ($prepared_sql === null) {
            $errors[] = 'Prepared time is not a valid date/time.';
        }
    }

    $expiry_sql = null;
    if ($old['expiry_time'] === '') {
        $errors[] = 'Expiry time is required.';
    } else {
        $expiry_sql = parse_datetime_local($old['expiry_time']);
        if ($expiry_sql === null) {
            $errors[] = 'Expiry time is not a valid date/time.';
        }
    }

    // "Expiry time must be later than prepared time."
    if ($prepared_sql !== null && $expiry_sql !== null && $expiry_sql <= $prepared_sql) {
        $errors[] = 'Expiry time must be later than prepared time.';
    }

    // A brand-new donation shouldn't already be expired.
    if ($expiry_sql !== null && $expiry_sql <= date('Y-m-d H:i:s')) {
        $errors[] = 'Expiry time must be in the future.';
    }

    if (!$errors) {
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO donations
                    (donor_id, category_id, food_name, description, quantity, unit,
                     prepared_time, expiry_time, pickup_location, status)
                 VALUES
                    (:donor_id, :category_id, :food_name, :description, :quantity, :unit,
                     :prepared_time, :expiry_time, :pickup_location, 'Available')"
            );
            $stmt->execute([
                ':donor_id'        => $donor_id,
                ':category_id'     => $category_id,
                ':food_name'       => $old['food_name'],
                ':description'     => $old['description'] !== '' ? $old['description'] : null,
                ':quantity'        => (float) $old['quantity'],
                ':unit'            => $old['unit'],
                ':prepared_time'   => $prepared_sql,
                ':expiry_time'     => $expiry_sql,
                ':pickup_location' => $old['pickup_location'],
            ]);

            redirect(BASE_URL . 'donor/my_donations.php?msg=added');

        } catch (PDOException $ex) {
            error_log('FoodBridge add_donation failed: ' . $ex->getMessage());
            $errors[] = 'Could not save the donation. Please check your input and try again.';
        }
    }
}

$current_page = 'add_donation';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Add Donation - <?= e(SITE_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php require __DIR__ . '/_nav.php'; ?>

<div class="container pb-5">
  <div class="row justify-content-center">
    <div class="col-lg-8">
      <div class="card shadow-sm">
        <div class="card-body p-4">
          <h3 class="mb-3">Add a Food Donation</h3>

          <?php if ($errors): ?>
            <div class="alert alert-danger">
              <ul class="mb-0">
                <?php foreach ($errors as $err): ?>
                  <li><?= e($err) ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>

          <form method="post" action="add_donation.php" novalidate>
            <div class="row g-3">
              <div class="col-md-6">
                <label for="food_name" class="form-label">Food Name</label>
                <input type="text" class="form-control" id="food_name" name="food_name" maxlength="100"
                       value="<?= e($old['food_name']) ?>" required>
              </div>
              <div class="col-md-6">
                <label for="category_id" class="form-label">Category</label>
                <select class="form-select" id="category_id" name="category_id" required>
                  <option value="">-- Select category --</option>
                  <?php foreach ($categories as $cat): ?>
                    <option value="<?= (int) $cat['category_id'] ?>"
                      <?= (string) $old['category_id'] === (string) $cat['category_id'] ? 'selected' : '' ?>>
                      <?= e($cat['category_name']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="col-12">
                <label for="description" class="form-label">Description <small class="text-muted">(optional)</small></label>
                <textarea class="form-control" id="description" name="description" rows="2"><?= e($old['description']) ?></textarea>
              </div>

              <div class="col-md-4">
                <label for="quantity" class="form-label">Quantity</label>
                <input type="number" step="0.01" min="0.01" class="form-control" id="quantity" name="quantity"
                       value="<?= e($old['quantity']) ?>" required>
              </div>
              <div class="col-md-4">
                <label for="unit" class="form-label">Unit</label>
                <input type="text" class="form-control" id="unit" name="unit" maxlength="20" placeholder="kg, pieces, packets..."
                       value="<?= e($old['unit']) ?>" required>
              </div>
              <div class="col-md-4">
                <label for="pickup_location" class="form-label">Pickup Location</label>
                <input type="text" class="form-control" id="pickup_location" name="pickup_location" maxlength="255"
                       value="<?= e($old['pickup_location']) ?>" required>
              </div>

              <div class="col-md-6">
                <label for="prepared_time" class="form-label">Prepared Time <small class="text-muted">(optional)</small></label>
                <input type="datetime-local" class="form-control" id="prepared_time" name="prepared_time"
                       value="<?= e(str_replace(' ', 'T', $old['prepared_time'])) ?>">
              </div>
              <div class="col-md-6">
                <label for="expiry_time" class="form-label">Expiry Time</label>
                <input type="datetime-local" class="form-control" id="expiry_time" name="expiry_time"
                       value="<?= e(str_replace(' ', 'T', $old['expiry_time'])) ?>" required>
              </div>
            </div>

            <button type="submit" class="btn btn-success mt-4">Add Donation</button>
            <a href="my_donations.php" class="btn btn-outline-secondary mt-4">Cancel</a>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>
