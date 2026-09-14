<?php
// REPORTS (protected: require_role('admin'))
// - Read-only aggregate statistics, all via COUNT/SUM/AVG/MIN/MAX + GROUP BY:
//   donations by status/category, requests by status, most active
//   donors/NGOs, donation size and beneficiary-count spread, distribution totals
require_once __DIR__ . '/../includes/role_check.php';
require_role('admin');

auto_expire_all_donations($pdo);

$by_status = $pdo->query(
    "SELECT status, COUNT(*) AS cnt FROM donations GROUP BY status ORDER BY cnt DESC"
)->fetchAll();

$by_category = $pdo->query(
    "SELECT c.category_name, COUNT(d.donation_id) AS donation_count
     FROM categories c
     LEFT JOIN donations d ON d.category_id = c.category_id
     GROUP BY c.category_id, c.category_name
     ORDER BY donation_count DESC"
)->fetchAll();

$requests_by_status = $pdo->query(
    "SELECT status, COUNT(*) AS cnt FROM requests GROUP BY status ORDER BY cnt DESC"
)->fetchAll();

// Donation-size stats per unit (mixing kg with pieces with packets into one
// AVG/MIN/MAX would be meaningless, so this groups by unit first).
$donation_size_stats = $pdo->query(
    "SELECT unit, COUNT(*) AS cnt, AVG(quantity) AS avg_qty, MIN(quantity) AS min_qty, MAX(quantity) AS max_qty
     FROM donations
     GROUP BY unit
     ORDER BY cnt DESC"
)->fetchAll();

$beneficiary_stats = $pdo->query(
    "SELECT COUNT(*) AS cnt, AVG(beneficiary_count) AS avg_ben, MIN(beneficiary_count) AS min_ben, MAX(beneficiary_count) AS max_ben
     FROM distributions"
)->fetch();

$top_donors = $pdo->query(
    "SELECT COALESCE(don.organization_name, u.name) AS donor_display_name,
            COUNT(d.donation_id) AS donation_count,
            SUM(d.status = 'Completed') AS completed_count
     FROM donors don
     JOIN users u ON u.user_id = don.user_id
     LEFT JOIN donations d ON d.donor_id = don.donor_id
     GROUP BY don.donor_id, donor_display_name
     ORDER BY donation_count DESC
     LIMIT 5"
)->fetchAll();

$top_ngos = $pdo->query(
    "SELECT COALESCE(n.organization_name, u.name) AS ngo_display_name,
            COUNT(r.request_id) AS request_count,
            SUM(r.status = 'Completed') AS completed_count
     FROM ngos n
     JOIN users u ON u.user_id = n.user_id
     LEFT JOIN requests r ON r.ngo_id = n.ngo_id
     GROUP BY n.ngo_id, ngo_display_name
     ORDER BY request_count DESC
     LIMIT 5"
)->fetchAll();

$distribution_totals = $pdo->query(
    "SELECT COUNT(*) AS distribution_count, COALESCE(SUM(beneficiary_count), 0) AS total_beneficiaries
     FROM distributions"
)->fetch();

$distributed_by_unit = $pdo->query(
    "SELECT d.unit, SUM(dist.quantity_distributed) AS total
     FROM distributions dist
     JOIN requests r ON r.request_id = dist.request_id
     JOIN donations d ON d.donation_id = r.donation_id
     GROUP BY d.unit"
)->fetchAll();

$current_page = 'reports';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reports - <?= e(SITE_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php require __DIR__ . '/_nav.php'; ?>

<div class="container-fluid pb-5">
    <h3 class="mb-4">Reports</h3>

    <div class="row g-4">
        <div class="col-lg-6">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <h5 class="card-title">Donations by Status</h5>
              <table class="table table-sm mb-0">
                <thead><tr><th>Status</th><th class="text-end">Count</th></tr></thead>
                <tbody>
                  <?php foreach ($by_status as $row): ?>
                    <tr>
                      <td><span class="badge <?= status_badge_class($row['status']) ?>"><?= e($row['status']) ?></span></td>
                      <td class="text-end"><?= (int) $row['cnt'] ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <div class="col-lg-6">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <h5 class="card-title">Requests by Status</h5>
              <table class="table table-sm mb-0">
                <thead><tr><th>Status</th><th class="text-end">Count</th></tr></thead>
                <tbody>
                  <?php if (!$requests_by_status): ?>
                    <tr><td colspan="2" class="text-muted text-center">No requests yet.</td></tr>
                  <?php endif; ?>
                  <?php foreach ($requests_by_status as $row): ?>
                    <tr>
                      <td><span class="badge <?= status_badge_class($row['status']) ?>"><?= e($row['status']) ?></span></td>
                      <td class="text-end"><?= (int) $row['cnt'] ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <div class="col-lg-6">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <h5 class="card-title">Donation Size by Unit</h5>
              <p class="text-muted small mb-2">Average / smallest / largest donation, grouped by unit since a kg and a piece can't be averaged together.</p>
              <table class="table table-sm mb-0">
                <thead><tr><th>Unit</th><th class="text-end">Count</th><th class="text-end">Avg</th><th class="text-end">Min</th><th class="text-end">Max</th></tr></thead>
                <tbody>
                  <?php if (!$donation_size_stats): ?>
                    <tr><td colspan="5" class="text-muted text-center">No donations yet.</td></tr>
                  <?php endif; ?>
                  <?php foreach ($donation_size_stats as $row): ?>
                    <tr>
                      <td><?= e($row['unit']) ?></td>
                      <td class="text-end"><?= (int) $row['cnt'] ?></td>
                      <td class="text-end"><?= e(number_format((float) $row['avg_qty'], 2)) ?></td>
                      <td class="text-end"><?= e(rtrim(rtrim(number_format((float) $row['min_qty'], 2), '0'), '.')) ?></td>
                      <td class="text-end"><?= e(rtrim(rtrim(number_format((float) $row['max_qty'], 2), '0'), '.')) ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <div class="col-lg-6">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <h5 class="card-title">Donations by Category</h5>
              <table class="table table-sm mb-0">
                <thead><tr><th>Category</th><th class="text-end">Donations</th></tr></thead>
                <tbody>
                  <?php foreach ($by_category as $row): ?>
                    <tr>
                      <td><?= e($row['category_name']) ?></td>
                      <td class="text-end"><?= (int) $row['donation_count'] ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <div class="col-lg-6">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <h5 class="card-title">Top 5 Donors</h5>
              <table class="table table-sm mb-0">
                <thead><tr><th>Donor</th><th class="text-end">Donations</th><th class="text-end">Completed</th></tr></thead>
                <tbody>
                  <?php if (!$top_donors): ?>
                    <tr><td colspan="3" class="text-muted text-center">No donors yet.</td></tr>
                  <?php endif; ?>
                  <?php foreach ($top_donors as $row): ?>
                    <tr>
                      <td><?= e($row['donor_display_name']) ?></td>
                      <td class="text-end"><?= (int) $row['donation_count'] ?></td>
                      <td class="text-end"><?= (int) $row['completed_count'] ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <div class="col-lg-6">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <h5 class="card-title">Top 5 NGOs</h5>
              <table class="table table-sm mb-0">
                <thead><tr><th>NGO</th><th class="text-end">Requests</th><th class="text-end">Completed</th></tr></thead>
                <tbody>
                  <?php if (!$top_ngos): ?>
                    <tr><td colspan="3" class="text-muted text-center">No NGOs yet.</td></tr>
                  <?php endif; ?>
                  <?php foreach ($top_ngos as $row): ?>
                    <tr>
                      <td><?= e($row['ngo_display_name']) ?></td>
                      <td class="text-end"><?= (int) $row['request_count'] ?></td>
                      <td class="text-end"><?= (int) $row['completed_count'] ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <div class="col-lg-6">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <h5 class="card-title">Distribution Summary</h5>
              <p class="mb-1">Total distributions recorded: <strong><?= (int) $distribution_totals['distribution_count'] ?></strong></p>
              <p class="mb-1">Total beneficiaries reached: <strong><?= (int) $distribution_totals['total_beneficiaries'] ?></strong></p>
              <p class="mb-1">Total quantity distributed:
                <?php if (!$distributed_by_unit): ?>
                  <span class="text-muted">None yet</span>
                <?php else: ?>
                  <?php $parts = []; foreach ($distributed_by_unit as $row) {
                      $parts[] = format_qty((float) $row['total'], $row['unit']);
                  } ?>
                  <strong><?= e(implode(', ', $parts)) ?></strong>
                <?php endif; ?>
              </p>
              <p class="mb-0">Beneficiaries per distribution:
                <?php if ((int) $beneficiary_stats['cnt'] === 0): ?>
                  <span class="text-muted">None yet</span>
                <?php else: ?>
                  avg <strong><?= e(number_format((float) $beneficiary_stats['avg_ben'], 1)) ?></strong>,
                  smallest <strong><?= (int) $beneficiary_stats['min_ben'] ?></strong>,
                  largest <strong><?= (int) $beneficiary_stats['max_ben'] ?></strong>
                <?php endif; ?>
              </p>
            </div>
          </div>
        </div>
    </div>
</div>
</body>
</html>
