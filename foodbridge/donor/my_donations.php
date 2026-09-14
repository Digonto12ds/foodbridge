<?php
// LIST OF DONOR'S OWN DONATIONS (protected: require_role('donor'))
// - SELECT * FROM donations WHERE donor_id = <logged-in donor>, newest first
// - Shows status badge; links to donation_details.php, edit_donation.php,
//   and a Cancel form, per row - only for donations that belong to this donor
require_once __DIR__ . '/../includes/role_check.php';
require_role('donor');

$donor_id = get_donor_id($pdo, current_user_id());
if ($donor_id === null) {
    die('Donor profile not found for this account. Please contact support.');
}

auto_expire_donations($pdo, $donor_id);

$stmt = $pdo->prepare(
    'SELECT d.donation_id, d.food_name, d.quantity, d.unit, d.expiry_time,
            d.status, d.created_at, c.category_name
     FROM donations d
     JOIN categories c ON c.category_id = d.category_id
     WHERE d.donor_id = :donor_id
     ORDER BY d.created_at DESC'
);
$stmt->execute([':donor_id' => $donor_id]);
$donations = $stmt->fetchAll();

$flash = [
    'added'     => ['type' => 'success', 'text' => 'Donation added successfully.'],
    'updated'   => ['type' => 'success', 'text' => 'Donation updated successfully.'],
    'cancelled' => ['type' => 'success', 'text' => 'Donation cancelled.'],
];
$msg = $flash[$_GET['msg'] ?? ''] ?? null;
$error = $_GET['error'] ?? '';

$current_page = 'my_donations';
$page_title = 'My Donations';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/_nav.php';
?>

<div class="container pb-5">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">My Donations</h3>
        <a href="add_donation.php" class="btn btn-success btn-sm">+ Add Donation</a>
    </div>

    <?php if ($msg): ?>
      <div class="alert alert-<?= e($msg['type']) ?>"><?= e($msg['text']) ?></div>
    <?php endif; ?>
    <?php if ($error === 'not_allowed'): ?>
      <div class="alert alert-danger">That action isn't allowed for this donation's current status.</div>
    <?php elseif ($error === 'not_found'): ?>
      <div class="alert alert-danger">Donation not found.</div>
    <?php endif; ?>

    <?php if (!$donations): ?>
      <div class="alert alert-light border">You haven't added any donations yet. <a href="add_donation.php">Add your first one</a>.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-bordered bg-white align-middle">
          <thead class="table-light">
            <tr>
              <th>Food Name</th>
              <th>Category</th>
              <th>Quantity</th>
              <th>Expiry</th>
              <th>Status</th>
              <th>Created</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($donations as $d): ?>
              <tr>
                <td><?= e($d['food_name']) ?></td>
                <td><?= e($d['category_name']) ?></td>
                <td><?= e(rtrim(rtrim(number_format((float) $d['quantity'], 2), '0'), '.')) ?> <?= e($d['unit']) ?></td>
                <td><?= e(date('d M Y, h:i A', strtotime($d['expiry_time']))) ?></td>
                <td><span class="badge <?= status_badge_class($d['status']) ?>"><?= e($d['status']) ?></span></td>
                <td><?= e(date('d M Y', strtotime($d['created_at']))) ?></td>
                <td class="text-nowrap">
                  <a href="donation_details.php?id=<?= (int) $d['donation_id'] ?>" class="btn btn-sm btn-outline-primary">View</a>
                  <?php if (can_edit_donation($d['status'])): ?>
                    <a href="edit_donation.php?id=<?= (int) $d['donation_id'] ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                  <?php endif; ?>
                  <?php if (can_cancel_donation($d['status'])): ?>
                    <form action="cancel_donation.php" method="post" class="d-inline"
                          onsubmit="return confirm('Cancel this donation? This cannot be undone.');">
                      <?= csrf_field() ?>
                      <input type="hidden" name="donation_id" value="<?= (int) $d['donation_id'] ?>">
                      <button type="submit" class="btn btn-sm btn-outline-danger">Cancel</button>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
