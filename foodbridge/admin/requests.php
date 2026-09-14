<?php
// REQUEST MANAGEMENT (protected: require_role('admin'))
// - Approve or reject NGO requests
//
// Approve (one atomic transaction):
//   requests.status   Pending -> Approved            (this file)
//   donations.status  Requested -> Claimed            (trg_requests_after_approve -
//                       see database/advanced_features.sql; the database
//                       enforces this cascade itself now, so it still
//                       holds even if some other future code path
//                       updates requests.status directly)
//   pickups            a new row is created (Scheduled), using the
//                       pickup date/time submitted with the approval -
//                       "create/schedule pickup" happens as part of
//                       approving, not as a separate step
//
// Reject:
//   requests.status   Pending -> Rejected
//   donations.status  Requested -> Available again (or Expired, if its
//                      expiry has since passed) - freeing it up so
//                      another NGO can request it. Nothing else in the
//                      system currently reverses this, so this is the
//                      one place a donation returns to circulation.
require_once __DIR__ . '/../includes/role_check.php';
require_role('admin');

$errors = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action     = $_POST['action'] ?? '';
    $request_id = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT);

    if ($action === 'approve' && $request_id) {
        $pickup_date = trim($_POST['pickup_date'] ?? '');
        $pickup_time = trim($_POST['pickup_time'] ?? '');

        $date_ok = DateTime::createFromFormat('Y-m-d', $pickup_date) !== false;
        $time_ok = DateTime::createFromFormat('H:i', $pickup_time) !== false;

        if (!$date_ok || !$time_ok) {
            $errors[] = 'A valid pickup date and time are required to approve a request.';
        } else {
            try {
                $pdo->beginTransaction();

                // Load the request + its donation, locking in current state.
                $stmt = $pdo->prepare(
                    "SELECT r.request_id, r.donation_id, r.status AS request_status, d.status AS donation_status
                     FROM requests r JOIN donations d ON d.donation_id = r.donation_id
                     WHERE r.request_id = :id LIMIT 1"
                );
                $stmt->execute([':id' => $request_id]);
                $row = $stmt->fetch();

                if (!$row || $row['request_status'] !== 'Pending') {
                    throw new RuntimeException('This request is no longer pending.');
                }

                // trg_requests_after_approve fires on this UPDATE and moves the
                // linked donation from Requested to Claimed itself; if that
                // donation is no longer Requested (e.g. an admin force-cancelled
                // it from admin/donations.php while this request sat Pending),
                // the trigger SIGNALs an error and this whole UPDATE is rolled
                // back - caught below as a PDOException with SQLSTATE 45000.
                $stmt = $pdo->prepare("UPDATE requests SET status = 'Approved' WHERE request_id = :id AND status = 'Pending'");
                $stmt->execute([':id' => $request_id]);
                if ($stmt->rowCount() === 0) {
                    throw new RuntimeException('This request is no longer pending.');
                }

                $stmt = $pdo->prepare(
                    "INSERT INTO pickups (request_id, pickup_date, pickup_time, pickup_status)
                     VALUES (:request_id, :pickup_date, :pickup_time, 'Scheduled')"
                );
                $stmt->execute([
                    ':request_id'  => $request_id,
                    ':pickup_date' => $pickup_date,
                    ':pickup_time' => $pickup_time,
                ]);

                $pdo->commit();
                $success = 'Request approved and pickup scheduled.';

            } catch (RuntimeException $ex) {
                $pdo->rollBack();
                $errors[] = $ex->getMessage();
            } catch (PDOException $ex) {
                $pdo->rollBack();
                error_log('FoodBridge request approval failed: ' . $ex->getMessage());
                // SQLSTATE 45000 is trg_requests_after_approve's own SIGNAL -
                // its message is already written for an end user to read.
                $errors[] = ($ex->getCode() === '45000')
                    ? 'The linked donation is no longer in a Requested state, so this request can no longer be approved.'
                    : 'Could not approve this request. Please try again.';
            }
        }

    } elseif ($action === 'reject' && $request_id) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                "SELECT donation_id FROM requests WHERE request_id = :id AND status = 'Pending' LIMIT 1"
            );
            $stmt->execute([':id' => $request_id]);
            $row = $stmt->fetch();

            if (!$row) {
                throw new RuntimeException('This request is no longer pending.');
            }

            $stmt = $pdo->prepare("UPDATE requests SET status = 'Rejected' WHERE request_id = :id AND status = 'Pending'");
            $stmt->execute([':id' => $request_id]);

            // Free the donation back up - Available if still within its
            // expiry window, Expired otherwise - so it can be requested again.
            $stmt = $pdo->prepare(
                "UPDATE donations
                 SET status = IF(expiry_time > NOW(), 'Available', 'Expired')
                 WHERE donation_id = :id AND status = 'Requested'"
            );
            $stmt->execute([':id' => $row['donation_id']]);

            $pdo->commit();
            $success = 'Request rejected. The donation is available again.';

        } catch (RuntimeException $ex) {
            $pdo->rollBack();
            $errors[] = $ex->getMessage();
        } catch (PDOException $ex) {
            $pdo->rollBack();
            error_log('FoodBridge request rejection failed: ' . $ex->getMessage());
            $errors[] = 'Could not reject this request. Please try again.';
        }
    }
}

$valid_statuses = ['Pending', 'Approved', 'Rejected', 'Completed', 'Cancelled'];
$status_filter = $_GET['status'] ?? 'Pending';
if (!in_array($status_filter, array_merge(['all'], $valid_statuses), true)) {
    $status_filter = 'Pending';
}

$sql = "SELECT r.request_id, r.requested_quantity, r.request_date, r.status,
               d.donation_id, d.food_name, d.unit, d.quantity AS donation_quantity, d.pickup_location,
               COALESCE(n.organization_name, u.name) AS ngo_display_name
        FROM requests r
        JOIN donations d ON d.donation_id = r.donation_id
        JOIN ngos n ON n.ngo_id = r.ngo_id
        JOIN users u ON u.user_id = n.user_id";
$params = [];
if ($status_filter !== 'all') {
    $sql .= ' WHERE r.status = :status';
    $params[':status'] = $status_filter;
}
$sql .= ' ORDER BY r.request_date DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$requests = $stmt->fetchAll();

$today = date('Y-m-d');
$current_page = 'requests';
$page_title = 'Request Management';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/_nav.php';
?>

<div class="container-fluid pb-5">
    <h3 class="mb-3">Request Management</h3>

    <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
    <?php if ($errors): ?>
      <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <ul class="nav nav-pills mb-3 flex-wrap">
      <?php foreach (array_merge(['all' => 'All'], array_combine($valid_statuses, $valid_statuses)) as $key => $label): ?>
        <li class="nav-item">
          <a class="nav-link <?= $status_filter === $key ? 'active' : '' ?>" href="requests.php?status=<?= e($key) ?>"><?= e($label) ?></a>
        </li>
      <?php endforeach; ?>
    </ul>

    <div class="table-responsive">
      <table class="table table-bordered bg-white align-middle">
        <thead class="table-light">
          <tr><th>Food</th><th>NGO</th><th>Qty Requested</th><th>Request Date</th><th>Status</th><th style="min-width: 320px;">Action</th></tr>
        </thead>
        <tbody>
          <?php if (!$requests): ?>
            <tr><td colspan="6" class="text-center text-muted">No requests match this filter.</td></tr>
          <?php endif; ?>
          <?php foreach ($requests as $r): ?>
            <tr>
              <td><?= e($r['food_name']) ?> <small class="text-muted">(<?= e(format_qty((float) $r['donation_quantity'], $r['unit'])) ?> total)</small></td>
              <td><?= e($r['ngo_display_name']) ?></td>
              <td><?= e(format_qty((float) $r['requested_quantity'], $r['unit'])) ?></td>
              <td><?= e(date('d M Y, h:i A', strtotime($r['request_date']))) ?></td>
              <td><span class="badge <?= status_badge_class($r['status']) ?>"><?= e($r['status']) ?></span></td>
              <td>
                <?php if ($r['status'] === 'Pending'): ?>
                  <form method="post" action="requests.php?status=<?= e($status_filter) ?>" class="d-flex gap-1 align-items-center flex-wrap">
                    <?= csrf_field() ?>
                    <input type="hidden" name="request_id" value="<?= (int) $r['request_id'] ?>">
                    <input type="date" name="pickup_date" class="form-control form-control-sm" style="width: 145px;" min="<?= e($today) ?>" required>
                    <input type="time" name="pickup_time" class="form-control form-control-sm" style="width: 110px;" required>
                    <button type="submit" name="action" value="approve" class="btn btn-sm btn-success">Approve</button>
                    <button type="submit" name="action" value="reject" class="btn btn-sm btn-outline-danger" formnovalidate
                            onclick="return confirm('Reject this request? The donation will become available again.');">Reject</button>
                  </form>
                <?php else: ?>
                  <span class="text-muted small">No action available</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
</div>
</div><!-- /.admin-content -->
</div><!-- /.admin-main -->
</div><!-- /.admin-shell -->
<?php require __DIR__ . '/../includes/footer.php'; ?>
