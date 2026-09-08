<?php
// DONATION DETAILS (protected: require_role('donor'))
// - Shows the full record for one donation, identified by ?id=
// - Ownership check: only visible if it belongs to the logged-in donor
//   ("Donor can only manage their own donations")
require_once __DIR__ . '/../includes/role_check.php';
require_role('donor');

$donor_id = get_donor_id($pdo, current_user_id());
if ($donor_id === null) {
    die('Donor profile not found for this account. Please contact support.');
}

$donation_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$donation_id) {
    redirect(BASE_URL . 'donor/my_donations.php?error=not_found');
}

auto_expire_donations($pdo, $donor_id);

// The donor_id condition is what enforces "only your own donations" -
// a donation_id belonging to another donor simply returns no row.
$stmt = $pdo->prepare(
    'SELECT d.*, c.category_name
     FROM donations d
     JOIN categories c ON c.category_id = d.category_id
     WHERE d.donation_id = :donation_id AND d.donor_id = :donor_id
     LIMIT 1'
);
$stmt->execute([':donation_id' => $donation_id, ':donor_id' => $donor_id]);
$donation = $stmt->fetch();

if (!$donation) {
    redirect(BASE_URL . 'donor/my_donations.php?error=not_found');
}

$current_page = 'my_donations';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Donation Details - <?= e(SITE_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php require __DIR__ . '/_nav.php'; ?>

<div class="container pb-5">
  <div class="row justify-content-center">
    <div class="col-lg-8">
      <div class="card shadow-sm">
        <div class="card-body p-4">
          <div class="d-flex justify-content-between align-items-start mb-3">
            <h3 class="mb-0"><?= e($donation['food_name']) ?></h3>
            <span class="badge fs-6 <?= status_badge_class($donation['status']) ?>"><?= e($donation['status']) ?></span>
          </div>

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

            <dt class="col-sm-4">Created</dt>
            <dd class="col-sm-8"><?= e(date('d M Y, h:i A', strtotime($donation['created_at']))) ?></dd>
          </dl>

          <div class="mt-4 d-flex gap-2">
            <a href="my_donations.php" class="btn btn-outline-secondary">Back to My Donations</a>
            <?php if (can_edit_donation($donation['status'])): ?>
              <a href="edit_donation.php?id=<?= (int) $donation['donation_id'] ?>" class="btn btn-outline-primary">Edit</a>
            <?php endif; ?>
            <?php if (can_cancel_donation($donation['status'])): ?>
              <form action="cancel_donation.php" method="post" class="d-inline"
                    onsubmit="return confirm('Cancel this donation? This cannot be undone.');">
                <input type="hidden" name="donation_id" value="<?= (int) $donation['donation_id'] ?>">
                <button type="submit" class="btn btn-outline-danger">Cancel Donation</button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>
