<?php
/**
 * FoodBridge - Registration (Donor / NGO only; Admin is never self-registered)
 *
 * Flow: form submitted -> validate -> INSERT into users -> INSERT into
 * donors or ngos using the new user_id -> redirect to login.php.
 * The users + donors/ngos inserts are wrapped in one DB transaction so
 * a failure halfway through never leaves an orphaned `users` row.
 */
require_once __DIR__ . '/includes/auth.php';

// Already logged in? No reason to see the registration form.
if (is_logged_in()) {
    redirect(dashboard_path_for_role(current_user_role()));
}

$errors = [];
$old = [
    'role'              => 'donor',
    'name'              => '',
    'email'             => '',
    'phone'             => '',
    'address'           => '',
    'organization_name' => '',
    'donor_type'        => 'Individual',
    'registration_no'   => '',
    'contact'           => '',
];

$valid_donor_types = ['Restaurant', 'Hotel', 'Bakery', 'Supermarket', 'Individual'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $old['role']              = $_POST['role'] ?? '';
    $old['name']              = sanitize_input($_POST['name'] ?? '');
    $old['email']             = sanitize_input($_POST['email'] ?? '');
    $old['phone']             = sanitize_input($_POST['phone'] ?? '');
    $old['address']           = sanitize_input($_POST['address'] ?? '');
    $old['organization_name'] = sanitize_input($_POST['organization_name'] ?? '');
    $old['donor_type']        = $_POST['donor_type'] ?? '';
    $old['registration_no']   = sanitize_input($_POST['registration_no'] ?? '');
    $old['contact']           = sanitize_input($_POST['contact'] ?? '');
    $password         = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // ---- Shared validation (goes into `users`) ----
    if (!in_array($old['role'], ['donor', 'ngo'], true)) {
        $errors[] = 'Please choose whether you are registering as a Donor or an NGO.';
    }
    if ($old['name'] === '' || mb_strlen($old['name']) > 100) {
        $errors[] = 'Name is required (max 100 characters).';
    }
    if ($old['email'] === '' || !is_valid_email($old['email']) || mb_strlen($old['email']) > 100) {
        $errors[] = 'A valid email address is required.';
    }
    if ($password === '' || mb_strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    }
    if ($password !== $confirm_password) {
        $errors[] = 'Password and Confirm Password do not match.';
    }
    if ($old['phone'] === '' || mb_strlen($old['phone']) < 7 || mb_strlen($old['phone']) > 15) {
        $errors[] = 'A valid phone number (7-15 characters) is required.';
    }
    if ($old['address'] === '' || mb_strlen($old['address']) > 255) {
        $errors[] = 'Address is required (max 255 characters).';
    }

    // ---- Role-specific validation ----
    if ($old['role'] === 'donor') {
        if (!in_array($old['donor_type'], $valid_donor_types, true)) {
            $errors[] = 'Please select a valid donor type.';
        }
        if ($old['donor_type'] !== 'Individual' && $old['organization_name'] === '') {
            $errors[] = 'Organization name is required unless the donor type is Individual.';
        }
    } elseif ($old['role'] === 'ngo') {
        if ($old['organization_name'] === '' || mb_strlen($old['organization_name']) > 150) {
            $errors[] = 'Organization name is required (max 150 characters).';
        }
        if ($old['registration_no'] === '' || mb_strlen($old['registration_no']) > 50) {
            $errors[] = 'Registration number is required (max 50 characters).';
        }
        if ($old['contact'] === '' || mb_strlen($old['contact']) < 7 || mb_strlen($old['contact']) > 15) {
            $errors[] = 'A valid contact number (7-15 characters) is required.';
        }
    }

    // ---- Friendly duplicate-email check (the DB's UNIQUE constraint is the real guard) ----
    if (!$errors) {
        $stmt = $pdo->prepare('SELECT user_id FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $old['email']]);
        if ($stmt->fetch()) {
            $errors[] = 'An account with this email already exists. Try logging in instead.';
        }
    }

    // ---- Insert (users, then donors/ngos, in one transaction) ----
    if (!$errors) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                'INSERT INTO users (name, email, password, phone, address, role)
                 VALUES (:name, :email, :password, :phone, :address, :role)'
            );
            $stmt->execute([
                ':name'     => $old['name'],
                ':email'    => $old['email'],
                ':password' => password_hash($password, PASSWORD_DEFAULT), // never store plain text
                ':phone'    => $old['phone'],
                ':address'  => $old['address'],
                ':role'     => $old['role'],
            ]);

            $user_id = (int) $pdo->lastInsertId();

            if ($old['role'] === 'donor') {
                // Schema allows NULL organization_name only for Individual donors.
                $org_name = ($old['donor_type'] === 'Individual' && $old['organization_name'] === '')
                    ? null
                    : $old['organization_name'];

                $stmt = $pdo->prepare(
                    'INSERT INTO donors (user_id, organization_name, donor_type)
                     VALUES (:user_id, :organization_name, :donor_type)'
                );
                $stmt->execute([
                    ':user_id'           => $user_id,
                    ':organization_name' => $org_name,
                    ':donor_type'        => $old['donor_type'],
                ]);
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO ngos (user_id, organization_name, registration_no, address, contact)
                     VALUES (:user_id, :organization_name, :registration_no, :address, :contact)'
                );
                $stmt->execute([
                    ':user_id'           => $user_id,
                    ':organization_name' => $old['organization_name'],
                    ':registration_no'   => $old['registration_no'],
                    ':address'           => $old['address'],
                    ':contact'           => $old['contact'],
                ]);
            }

            $pdo->commit();
            redirect(BASE_URL . 'login.php?registered=1');

        } catch (PDOException $ex) {
            $pdo->rollBack();
            error_log('FoodBridge registration failed: ' . $ex->getMessage());
            $errors[] = ($ex->getCode() === '23000')
                ? 'An account with this email or registration number already exists.'
                : 'Registration failed. Please try again later.';
        }
    }
}
$page_title = 'Register';
require __DIR__ . '/includes/header.php';
?>
<div class="fb-hero flex-grow-1 py-5">
<div class="container py-4">
  <div class="row justify-content-center">
    <div class="col-md-8 col-lg-7">
      <div class="card shadow">
        <div class="card-body p-4">
          <a href="index.php" class="text-decoration-none d-inline-flex align-items-center gap-2 mb-3">
            <span class="brand-mark d-inline-flex align-items-center justify-content-center" style="width:34px;height:34px;border-radius:9px;background:var(--fb-primary);color:#fff;">🌉</span>
            <span class="fw-brand fs-5" style="color:var(--fb-primary);"><?= e(SITE_NAME) ?></span>
          </a>
          <h2 class="mb-1 h4">Create your account</h2>
          <p class="text-muted mb-4">Register as a Donor or an NGO</p>

          <?php if ($errors): ?>
            <div class="alert alert-danger">
              <ul class="mb-0">
                <?php foreach ($errors as $err): ?>
                  <li><?= e($err) ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>

          <form method="post" action="register.php" novalidate>
            <?= csrf_field() ?>

            <fieldset class="mb-3">
              <legend class="col-form-label pt-0">Register as</legend>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="role" id="role_donor" value="donor"
                       onchange="toggleRoleFields()" <?= $old['role'] === 'donor' ? 'checked' : '' ?>>
                <label class="form-check-label" for="role_donor">Donor</label>
              </div>
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="role" id="role_ngo" value="ngo"
                       onchange="toggleRoleFields()" <?= $old['role'] === 'ngo' ? 'checked' : '' ?>>
                <label class="form-check-label" for="role_ngo">NGO</label>
              </div>
            </fieldset>

            <div class="row g-3">
              <div class="col-md-6">
                <label for="name" class="form-label">Full Name</label>
                <input type="text" class="form-control" id="name" name="name" maxlength="100"
                       value="<?= e($old['name']) ?>" required>
              </div>
              <div class="col-md-6">
                <label for="email" class="form-label">Email</label>
                <input type="email" class="form-control" id="email" name="email" maxlength="100"
                       value="<?= e($old['email']) ?>" required>
              </div>

              <div class="col-md-6">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" minlength="8" required>
              </div>
              <div class="col-md-6">
                <label for="confirm_password" class="form-label">Confirm Password</label>
                <input type="password" class="form-control" id="confirm_password" name="confirm_password" minlength="8" required>
              </div>

              <div class="col-md-6">
                <label for="phone" class="form-label">Phone</label>
                <input type="text" class="form-control" id="phone" name="phone" maxlength="15"
                       value="<?= e($old['phone']) ?>" required>
              </div>
              <div class="col-md-6">
                <label for="address" class="form-label">Address</label>
                <input type="text" class="form-control" id="address" name="address" maxlength="255"
                       value="<?= e($old['address']) ?>" required>
              </div>

              <div class="col-md-6">
                <label for="organization_name" class="form-label">
                  Organization Name
                  <small class="text-muted">(required for NGOs; optional for Individual donors)</small>
                </label>
                <input type="text" class="form-control" id="organization_name" name="organization_name" maxlength="150"
                       value="<?= e($old['organization_name']) ?>">
              </div>

              <!-- Donor-only field -->
              <div class="col-md-6" id="donor-fields">
                <label for="donor_type" class="form-label">Donor Type</label>
                <select class="form-select" id="donor_type" name="donor_type">
                  <?php foreach ($valid_donor_types as $type): ?>
                    <option value="<?= e($type) ?>" <?= $old['donor_type'] === $type ? 'selected' : '' ?>><?= e($type) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <!-- NGO-only fields -->
              <div class="col-md-6" id="ngo-fields-1">
                <label for="registration_no" class="form-label">Registration Number</label>
                <input type="text" class="form-control" id="registration_no" name="registration_no" maxlength="50"
                       value="<?= e($old['registration_no']) ?>">
              </div>
              <div class="col-md-6" id="ngo-fields-2">
                <label for="contact" class="form-label">Contact Number</label>
                <input type="text" class="form-control" id="contact" name="contact" maxlength="15"
                       value="<?= e($old['contact']) ?>">
              </div>
            </div>

            <button type="submit" class="btn btn-success w-100 mt-4">Create Account</button>
          </form>

          <p class="text-center mt-3 mb-0">
            Already have an account? <a href="login.php">Log in</a>
          </p>
        </div>
      </div>
    </div>
  </div>
</div>
</div>

<script>
function toggleRoleFields() {
  var role = document.querySelector('input[name="role"]:checked').value;
  var isDonor = role === 'donor';

  document.getElementById('donor-fields').style.display = isDonor ? '' : 'none';
  document.getElementById('ngo-fields-1').style.display = isDonor ? 'none' : '';
  document.getElementById('ngo-fields-2').style.display = isDonor ? 'none' : '';

  document.getElementById('registration_no').required = !isDonor;
  document.getElementById('contact').required = !isDonor;
}
toggleRoleFields();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
