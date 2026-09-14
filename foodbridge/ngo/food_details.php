<?php
// FOOD DETAILS (protected: require_role('ngo'))
// - Full donation record, for any donation (not just Available ones, so
//   this page also works when linked from my_requests.php for history)
// - If the donation is still Available and not expired, shows a
//   "Request This Food" form that posts to request_food.php
require_once __DIR__ . '/../includes/role_check.php';
require_role('ngo');

$ngo_id = get_ngo_id($pdo, current_user_id());
if ($ngo_id === null) {
    die('NGO profile not found for this account. Please contact support.');
}

$donation_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$donation_id) {
    redirect(BASE_URL . 'ngo/available_food.php');
}

auto_expire_all_donations($pdo);

// v_donation_details (database/advanced_features.sql) is the donations +
// categories + donors + users join, defined once and reused across the
// donor-facing pages instead of hand-written here.
$stmt = $pdo->prepare('SELECT * FROM v_donation_details WHERE donation_id = :id LIMIT 1');
$stmt->execute([':id' => $donation_id]);
$donation = $stmt->fetch();

if (!$donation) {
    redirect(BASE_URL . 'ngo/available_food.php');
}

$can_request = $donation['status'] === 'Available' && strtotime($donation['expiry_time']) > time();

$errors = $_SESSION['food_details_errors'] ?? [];
unset($_SESSION['food_details_errors']);

$current_page = 'available_food';
$page_title = 'Food Details';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/_nav.php';
?>

<div class="container pb-5">
  <div class="row justify-content-center">
    <div class="col-lg-8">
      <div class="card shadow-sm">
        <div class="card-body p-4">
          <div class="d-flex justify-content-between align-items-start mb-3">
            <h3 class="mb-0"><?= e($donation['food_name']) ?></h3>
            <span class="badge fs-6 <?= status_badge_class($donation['status']) ?>"><?= e($donation['status']) ?></span>
          </div>

          <?php if ($errors): ?>
            <div class="alert alert-danger">
              <ul class="mb-0">
                <?php foreach ($errors as $err): ?>
                  <li><?= e($err) ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>

          <dl class="row mb-0">
            <dt class="col-sm-4">Category</dt>
            <dd class="col-sm-8"><?= e($donation['category_name']) ?></dd>

            <dt class="col-sm-4">Description</dt>
            <dd class="col-sm-8"><?= $donation['description'] !== null ? nl2br(e($donation['description'])) : '<span class="text-muted">-</span>' ?></dd>

            <dt class="col-sm-4">Quantity</dt>
            <dd class="col-sm-8"><?= e(rtrim(rtrim(number_format((float) $donation['quantity'], 2), '0'), '.')) ?> <?= e($donation['unit']) ?></dd>

            <dt class="col-sm-4">Prepared Time</dt>
            <dd class="col-sm-8">
              <?= $donation['prepared_time'] !== null ? e(date('d M Y, h:i A', strtotime($donation['prepared_time']))) : '<span class="text-muted">-</span>' ?>
            </dd>

            <dt class="col-sm-4">Expiry Time</dt>
            <dd class="col-sm-8"><?= e(date('d M Y, h:i A', strtotime($donation['expiry_time']))) ?></dd>

            <dt class="col-sm-4">Pickup Location</dt>
            <dd class="col-sm-8"><?= e($donation['pickup_location']) ?></dd>

            <dt class="col-sm-4">Donor</dt>
            <dd class="col-sm-8"><?= e($donation['donor_display_name']) ?> (<?= e($donation['donor_phone']) ?>)</dd>
          </dl>

          <hr>

          <?php if ($can_request): ?>
            <h5>Request This Food</h5>
            <form action="request_food.php" method="post" class="row g-2 align-items-end">
              <?= csrf_field() ?>
              <input type="hidden" name="donation_id" value="<?= (int) $donation['donation_id'] ?>">
              <div class="col-sm-5">
                <label for="requested_quantity" class="form-label">
                  Quantity to request <small class="text-muted">(max <?= e(rtrim(rtrim(number_format((float) $donation['quantity'], 2), '0'), '.')) ?> <?= e($donation['unit']) ?>)</small>
                </label>
                <input type="number" step="0.01" min="0.01" max="<?= e((string) $donation['quantity']) ?>"
                       class="form-control" id="requested_quantity" name="requested_quantity"
                       value="<?= e((string) $donation['quantity']) ?>" required>
              </div>
              <div class="col-sm-3">
                <button type="submit" class="btn btn-primary">Submit Request</button>
              </div>
            </form>
          <?php else: ?>
            <div class="alert alert-secondary mb-0">
              This donation is no longer available to request (status: <?= e($donation['status']) ?>).
            </div>
          <?php endif; ?>

          <a href="available_food.php" class="btn btn-outline-secondary mt-4">Back to Available Food</a>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
