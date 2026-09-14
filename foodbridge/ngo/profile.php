<?php
// NGO PROFILE (protected: require_role('ngo'))
// - Shows account info from `users` + `ngos`
// - Lets the NGO update name/phone (users), and organization_name/
//   address/contact (ngos). Email, password, and registration_no are
//   not editable here - registration_no is the official identifier and
//   shouldn't be casually self-edited.
require_once __DIR__ . '/../includes/role_check.php';
require_role('ngo');

$user_id = current_user_id();
$ngo_id  = get_ngo_id($pdo, $user_id);
if ($ngo_id === null) {
    die('NGO profile not found for this account. Please contact support.');
}

$stmt = $pdo->prepare(
    'SELECT u.name, u.email, u.phone, n.organization_name, n.registration_no, n.address, n.contact
     FROM users u
     JOIN ngos n ON n.user_id = u.user_id
     WHERE u.user_id = :user_id
     LIMIT 1'
);
$stmt->execute([':user_id' => $user_id]);
$profile = $stmt->fetch();

$errors = [];
$success = false;

$old = [
    'name'              => $profile['name'],
    'phone'             => $profile['phone'],
    'organization_name' => $profile['organization_name'],
    'address'           => $profile['address'],
    'contact'           => $profile['contact'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old['name']              = sanitize_input($_POST['name'] ?? '');
    $old['phone']             = sanitize_input($_POST['phone'] ?? '');
    $old['organization_name'] = sanitize_input($_POST['organization_name'] ?? '');
    $old['address']           = sanitize_input($_POST['address'] ?? '');
    $old['contact']           = sanitize_input($_POST['contact'] ?? '');

    if ($old['name'] === '' || mb_strlen($old['name']) > 100) {
        $errors[] = 'Name is required (max 100 characters).';
    }
    if ($old['phone'] === '' || mb_strlen($old['phone']) < 7 || mb_strlen($old['phone']) > 15) {
        $errors[] = 'A valid phone number (7-15 characters) is required.';
    }
    if ($old['organization_name'] === '' || mb_strlen($old['organization_name']) > 150) {
        $errors[] = 'Organization name is required (max 150 characters).';
    }
    if ($old['address'] === '' || mb_strlen($old['address']) > 255) {
        $errors[] = 'Address is required (max 255 characters).';
    }
    if ($old['contact'] === '' || mb_strlen($old['contact']) < 7 || mb_strlen($old['contact']) > 15) {
        $errors[] = 'A valid contact number (7-15 characters) is required.';
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare('UPDATE users SET name = :name, phone = :phone WHERE user_id = :user_id');
            $stmt->execute([
                ':name'    => $old['name'],
                ':phone'   => $old['phone'],
                ':user_id' => $user_id,
            ]);

            $stmt = $pdo->prepare(
                'UPDATE ngos SET organization_name = :organization_name, address = :address, contact = :contact
                 WHERE ngo_id = :ngo_id'
            );
            $stmt->execute([
                ':organization_name' => $old['organization_name'],
                ':address'           => $old['address'],
                ':contact'           => $old['contact'],
                ':ngo_id'            => $ngo_id,
            ]);

            $pdo->commit();

            $_SESSION['name'] = $old['name'];
            $success = true;

        } catch (PDOException $ex) {
            $pdo->rollBack();
            error_log('FoodBridge ngo profile update failed: ' . $ex->getMessage());
            $errors[] = 'Could not save changes. Please try again later.';
        }
    }
}

$current_page = 'profile';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Profile - <?= e(SITE_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php require __DIR__ . '/_nav.php'; ?>

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
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Email</label>
                <input type="email" class="form-control" value="<?= e($profile['email']) ?>" disabled>
              </div>
              <div class="col-md-6">
                <label class="form-label">Registration Number</label>
                <input type="text" class="form-control" value="<?= e($profile['registration_no']) ?>" disabled>
              </div>

              <div class="col-md-6">
                <label for="name" class="form-label">Contact Person Name</label>
                <input type="text" class="form-control" id="name" name="name" maxlength="100"
                       value="<?= e($old['name']) ?>" required>
              </div>
              <div class="col-md-6">
                <label for="phone" class="form-label">Phone</label>
                <input type="text" class="form-control" id="phone" name="phone" maxlength="15"
                       value="<?= e($old['phone']) ?>" required>
              </div>

              <div class="col-md-6">
                <label for="organization_name" class="form-label">Organization Name</label>
                <input type="text" class="form-control" id="organization_name" name="organization_name" maxlength="150"
                       value="<?= e($old['organization_name']) ?>" required>
              </div>
              <div class="col-md-6">
                <label for="contact" class="form-label">Organization Contact Number</label>
                <input type="text" class="form-control" id="contact" name="contact" maxlength="15"
                       value="<?= e($old['contact']) ?>" required>
              </div>

              <div class="col-12">
                <label for="address" class="form-label">Address</label>
                <input type="text" class="form-control" id="address" name="address" maxlength="255"
                       value="<?= e($old['address']) ?>" required>
              </div>
            </div>

            <button type="submit" class="btn btn-primary mt-4">Save Changes</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>
