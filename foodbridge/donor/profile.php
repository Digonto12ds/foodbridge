<?php
// DONOR PROFILE (protected: require_role('donor'))
// - Shows account info from `users` + `donors`
// - Lets the donor update name/phone/address (users) and
//   organization_name/donor_type (donors). Email and password are not
//   editable here (that's a separate concern, not part of this module).
require_once __DIR__ . '/../includes/role_check.php';
require_role('donor');

$user_id  = current_user_id();
$donor_id = get_donor_id($pdo, $user_id);
if ($donor_id === null) {
    die('Donor profile not found for this account. Please contact support.');
}

$stmt = $pdo->prepare(
    'SELECT u.name, u.email, u.phone, u.address, d.organization_name, d.donor_type
     FROM users u
     JOIN donors d ON d.user_id = u.user_id
     WHERE u.user_id = :user_id
     LIMIT 1'
);
$stmt->execute([':user_id' => $user_id]);
$profile = $stmt->fetch();

$valid_donor_types = ['Restaurant', 'Hotel', 'Bakery', 'Supermarket', 'Individual'];
$errors = [];
$success = false;

$old = [
    'name'              => $profile['name'],
    'phone'             => $profile['phone'],
    'address'           => $profile['address'] ?? '',
    'organization_name' => $profile['organization_name'] ?? '',
    'donor_type'        => $profile['donor_type'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old['name']              = sanitize_input($_POST['name'] ?? '');
    $old['phone']             = sanitize_input($_POST['phone'] ?? '');
    $old['address']           = sanitize_input($_POST['address'] ?? '');
    $old['organization_name'] = sanitize_input($_POST['organization_name'] ?? '');
    $old['donor_type']        = $_POST['donor_type'] ?? '';

    if ($old['name'] === '' || mb_strlen($old['name']) > 100) {
        $errors[] = 'Name is required (max 100 characters).';
    }
    if ($old['phone'] === '' || mb_strlen($old['phone']) < 7 || mb_strlen($old['phone']) > 15) {
        $errors[] = 'A valid phone number (7-15 characters) is required.';
    }
    if ($old['address'] === '' || mb_strlen($old['address']) > 255) {
        $errors[] = 'Address is required (max 255 characters).';
    }
    if (!in_array($old['donor_type'], $valid_donor_types, true)) {
        $errors[] = 'Please select a valid donor type.';
    }
    if ($old['donor_type'] !== 'Individual' && $old['organization_name'] === '') {
        $errors[] = 'Organization name is required unless the donor type is Individual.';
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                'UPDATE users SET name = :name, phone = :phone, address = :address WHERE user_id = :user_id'
            );
            $stmt->execute([
                ':name'    => $old['name'],
                ':phone'   => $old['phone'],
                ':address' => $old['address'],
                ':user_id' => $user_id,
            ]);

            $org_name = ($old['donor_type'] === 'Individual' && $old['organization_name'] === '')
                ? null
                : $old['organization_name'];

            $stmt = $pdo->prepare(
                'UPDATE donors SET organization_name = :organization_name, donor_type = :donor_type WHERE donor_id = :donor_id'
            );
            $stmt->execute([
                ':organization_name' => $org_name,
                ':donor_type'        => $old['donor_type'],
                ':donor_id'          => $donor_id,
            ]);

            $pdo->commit();

            // Session stores the display name, so keep it in sync immediately.
            $_SESSION['name'] = $old['name'];
            $success = true;

        } catch (PDOException $ex) {
            $pdo->rollBack();
            error_log('FoodBridge profile update failed: ' . $ex->getMessage());
            $errors[] = 'Could not save changes. Please try again later.';
        }
    }
}

$current_page = 'profile';
$page_title = 'My Profile';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/_nav.php';
?>

<div class="container pb-5">
  <div class="row justify-content-center">
    <div class="col-lg-7">
      <div class="card shadow-sm">
        <div class="card-body p-4">
          <h3 class="mb-3">My Profile</h3>

          <?php if ($success): ?>
            <div class="alert alert-success">Profile updated successfully.</div>
          <?php endif; ?>
          <?php if ($errors): ?>
            <div class="alert alert-danger">
              <ul class="mb-0">
                <?php foreach ($errors as $err): ?>
                  <li><?= e($err) ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>

          <form method="post" action="profile.php" novalidate>
            <div class="mb-3">
              <label class="form-label">Email</label>
              <input type="email" class="form-control" value="<?= e($profile['email']) ?>" disabled>
              <div class="form-text">Email cannot be changed here.</div>
            </div>

            <div class="row g-3">
              <div class="col-md-6">
                <label for="name" class="form-label">Full Name</label>
                <input type="text" class="form-control" id="name" name="name" maxlength="100"
                       value="<?= e($old['name']) ?>" required>
              </div>
              <div class="col-md-6">
                <label for="phone" class="form-label">Phone</label>
                <input type="text" class="form-control" id="phone" name="phone" maxlength="15"
                       value="<?= e($old['phone']) ?>" required>
              </div>
              <div class="col-12">
                <label for="address" class="form-label">Address</label>
                <input type="text" class="form-control" id="address" name="address" maxlength="255"
                       value="<?= e($old['address']) ?>" required>
              </div>
              <div class="col-md-6">
                <label for="donor_type" class="form-label">Donor Type</label>
                <select class="form-select" id="donor_type" name="donor_type">
                  <?php foreach ($valid_donor_types as $type): ?>
                    <option value="<?= e($type) ?>" <?= $old['donor_type'] === $type ? 'selected' : '' ?>><?= e($type) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6">
                <label for="organization_name" class="form-label">
                  Organization Name <small class="text-muted">(optional if Individual)</small>
                </label>
                <input type="text" class="form-control" id="organization_name" name="organization_name" maxlength="150"
                       value="<?= e($old['organization_name']) ?>">
              </div>
            </div>

            <button type="submit" class="btn btn-success mt-4">Save Changes</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
