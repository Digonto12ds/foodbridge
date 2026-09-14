<?php
// DISTRIBUTION MANAGEMENT (protected: require_role('admin'))
// - Record quantity distributed, beneficiary count, location, notes for
//   an Approved request, and mark it completed in one action:
//     requests.status   Approved -> Completed
//     donations.status  Claimed  -> Completed
//     pickups.pickup_status -> Completed (the handover is done, so the
//                              pickup is necessarily done too)
// - Existing distribution records can be edited afterward (corrections)
require_once __DIR__ . '/../includes/role_check.php';
require_role('admin');

$errors = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'record') {
        $request_id         = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT);
        $quantity            = trim($_POST['quantity_distributed'] ?? '');
        $beneficiary_count   = trim($_POST['beneficiary_count'] ?? '');
        $location            = sanitize_input($_POST['location'] ?? '');
        $notes               = sanitize_input($_POST['notes'] ?? '');

        if (!is_numeric($quantity) || (float) $quantity <= 0) {
            $errors[] = 'Quantity distributed must be a number greater than 0.';
        }
        if (!ctype_digit($beneficiary_count) || (int) $beneficiary_count < 0) {
            $errors[] = 'Beneficiary count must be a whole number (0 or more).';
        }
        if ($location === '' || mb_strlen($location) > 255) {
            $errors[] = 'Location is required (max 255 characters).';
        }

        // Sanity check against the request's own claimed quantity.
        $req_stmt = $pdo->prepare(
            "SELECT r.requested_quantity, r.status AS request_status, d.donation_id
             FROM requests r JOIN donations d ON d.donation_id = r.donation_id
             WHERE r.request_id = :id LIMIT 1"
        );
        $req_stmt->execute([':id' => $request_id]);
        $req = $req_stmt->fetch();

        if (!$req || $req['request_status'] !== 'Approved') {
            $errors[] = 'This request is not in an approved state.';
        } elseif (!$errors && (float) $quantity > (float) $req['requested_quantity']) {
            $errors[] = 'Quantity distributed cannot exceed the requested quantity (' .
                rtrim(rtrim(number_format((float) $req['requested_quantity'], 2), '0'), '.') . ').';
        }

        if (!$errors) {
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare(
                    "INSERT INTO distributions (request_id, quantity_distributed, beneficiary_count, location, notes)
                     VALUES (:request_id, :quantity, :beneficiary_count, :location, :notes)"
                );
                $stmt->execute([
                    ':request_id'        => $request_id,
                    ':quantity'          => (float) $quantity,
                    ':beneficiary_count' => (int) $beneficiary_count,
                    ':location'          => $location,
                    ':notes'             => $notes !== '' ? $notes : null,
                ]);

                $stmt = $pdo->prepare("UPDATE requests SET status = 'Completed' WHERE request_id = :id AND status = 'Approved'");
                $stmt->execute([':id' => $request_id]);

                $stmt = $pdo->prepare("UPDATE donations SET status = 'Completed' WHERE donation_id = :id AND status = 'Claimed'");
                $stmt->execute([':id' => $req['donation_id']]);

                $stmt = $pdo->prepare("UPDATE pickups SET pickup_status = 'Completed' WHERE request_id = :id");
                $stmt->execute([':id' => $request_id]);

                $pdo->commit();
                $success = 'Distribution recorded and request marked completed.';

            } catch (PDOException $ex) {
                $pdo->rollBack();
                error_log('FoodBridge distribution record failed: ' . $ex->getMessage());
                $errors[] = ($ex->getCode() === '23000')
                    ? 'A distribution for this request already exists.'
                    : 'Could not record this distribution. Please try again.';
            }
        }

    } elseif ($action === 'edit') {
        $distribution_id   = filter_input(INPUT_POST, 'distribution_id', FILTER_VALIDATE_INT);
        $quantity           = trim($_POST['quantity_distributed'] ?? '');
        $beneficiary_count  = trim($_POST['beneficiary_count'] ?? '');
        $location           = sanitize_input($_POST['location'] ?? '');
        $notes              = sanitize_input($_POST['notes'] ?? '');

        if (!is_numeric($quantity) || (float) $quantity <= 0) {
            $errors[] = 'Quantity distributed must be a number greater than 0.';
        }
        if (!ctype_digit($beneficiary_count) || (int) $beneficiary_count < 0) {
            $errors[] = 'Beneficiary count must be a whole number (0 or more).';
        }
        if ($location === '' || mb_strlen($location) > 255) {
            $errors[] = 'Location is required (max 255 characters).';
        }

        if (!$errors && $distribution_id) {
            $stmt = $pdo->prepare(
                'UPDATE distributions
                 SET quantity_distributed = :quantity, beneficiary_count = :beneficiary_count,
                     location = :location, notes = :notes
                 WHERE distribution_id = :id'
            );
            $stmt->execute([
                ':quantity'          => (float) $quantity,
                ':beneficiary_count' => (int) $beneficiary_count,
                ':location'          => $location,
                ':notes'             => $notes !== '' ? $notes : null,
                ':id'                => $distribution_id,
            ]);
            $success = 'Distribution record updated.';
        }
    }
}

// Approved requests with no distribution recorded yet - ready to be recorded.
$ready = $pdo->query(
    "SELECT r.request_id, r.requested_quantity, d.food_name, d.unit, d.pickup_location,
            COALESCE(n.organization_name, u.name) AS ngo_display_name
     FROM requests r
     JOIN donations d ON d.donation_id = r.donation_id
     JOIN ngos n ON n.ngo_id = r.ngo_id
     JOIN users u ON u.user_id = n.user_id
     WHERE r.status = 'Approved'
       AND NOT EXISTS (SELECT 1 FROM distributions WHERE request_id = r.request_id)
     ORDER BY r.request_date"
)->fetchAll();

// Existing distribution records.
$distributions = $pdo->query(
    "SELECT dist.*, d.food_name, d.unit,
            COALESCE(n.organization_name, u.name) AS ngo_display_name
     FROM distributions dist
     JOIN requests r ON r.request_id = dist.request_id
     JOIN donations d ON d.donation_id = r.donation_id
     JOIN ngos n ON n.ngo_id = r.ngo_id
     JOIN users u ON u.user_id = n.user_id
     ORDER BY dist.distribution_date DESC"
)->fetchAll();

$current_page = 'distributions';
$page_title = 'Distribution Management';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/_nav.php';
?>

<div class="container-fluid pb-5">
    <h3 class="mb-3">Distribution Management</h3>

    <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
    <?php if ($errors): ?>
      <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <h5 class="mt-4">Ready to Record</h5>
    <?php if (!$ready): ?>
      <div class="alert alert-light border">No approved requests are waiting on a distribution record.</div>
    <?php else: ?>
      <?php foreach ($ready as $r): ?>
        <div class="card shadow-sm mb-3">
          <div class="card-body">
            <h6 class="card-title"><?= e($r['food_name']) ?> - <?= e($r['ngo_display_name']) ?>
              <small class="text-muted">(requested <?= e(format_qty((float) $r['requested_quantity'], $r['unit'])) ?>)</small>
            </h6>
            <form method="post" action="distributions.php" class="row g-2 align-items-end">
              <input type="hidden" name="action" value="record">
              <input type="hidden" name="request_id" value="<?= (int) $r['request_id'] ?>">
              <div class="col-sm-2">
                <label class="form-label small">Qty Distributed</label>
                <input type="number" step="0.01" min="0.01" max="<?= e((string) $r['requested_quantity']) ?>"
                       name="quantity_distributed" class="form-control form-control-sm"
                       value="<?= e((string) $r['requested_quantity']) ?>" required>
              </div>
              <div class="col-sm-2">
                <label class="form-label small">Beneficiaries</label>
                <input type="number" step="1" min="0" name="beneficiary_count" class="form-control form-control-sm" required>
              </div>
              <div class="col-sm-4">
                <label class="form-label small">Location</label>
                <input type="text" name="location" class="form-control form-control-sm" maxlength="255"
                       value="<?= e($r['pickup_location']) ?>" required>
              </div>
              <div class="col-sm-3">
                <label class="form-label small">Notes (optional)</label>
                <input type="text" name="notes" class="form-control form-control-sm">
              </div>
              <div class="col-sm-1">
                <button type="submit" class="btn btn-sm btn-success w-100">Record</button>
              </div>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>

    <h5 class="mt-4">Recorded Distributions</h5>
    <?php if (!$distributions): ?>
      <div class="alert alert-light border">No distributions recorded yet.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-bordered bg-white align-middle">
          <thead class="table-light">
            <tr><th>Food</th><th>NGO</th><th>Qty Distributed</th><th>Beneficiaries</th><th>Location</th><th>Notes</th><th>Date</th><th></th></tr>
          </thead>
          <tbody>
            <?php foreach ($distributions as $d): ?>
              <?php $formid = 'dist-form-' . (int) $d['distribution_id']; ?>
              <tr>
                <td><?= e($d['food_name']) ?></td>
                <td><?= e($d['ngo_display_name']) ?></td>
                <td><input type="number" step="0.01" min="0.01" name="quantity_distributed" form="<?= e($formid) ?>"
                           class="form-control form-control-sm" value="<?= e((string) $d['quantity_distributed']) ?>" required></td>
                <td><input type="number" step="1" min="0" name="beneficiary_count" form="<?= e($formid) ?>"
                           class="form-control form-control-sm" value="<?= (int) $d['beneficiary_count'] ?>" required></td>
                <td><input type="text" name="location" form="<?= e($formid) ?>" maxlength="255"
                           class="form-control form-control-sm" value="<?= e($d['location']) ?>" required></td>
                <td><input type="text" name="notes" form="<?= e($formid) ?>"
                           class="form-control form-control-sm" value="<?= e($d['notes'] ?? '') ?>"></td>
                <td><?= e(date('d M Y', strtotime($d['distribution_date']))) ?></td>
                <td>
                  <form id="<?= e($formid) ?>" method="post" action="distributions.php">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="distribution_id" value="<?= (int) $d['distribution_id'] ?>">
                  </form>
                  <button type="submit" form="<?= e($formid) ?>" class="btn btn-sm btn-outline-primary">Save</button>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
</div>
</div><!-- /.admin-content -->
</div><!-- /.admin-main -->
</div><!-- /.admin-shell -->
<?php require __DIR__ . '/../includes/footer.php'; ?>
