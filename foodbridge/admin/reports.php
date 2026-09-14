<?php
// REPORTS (protected: require_role('admin'))
// =============================================================================
// Every number on this page is either a stat card (the 8 single-figure
// reports: total food donated, total food redistributed, total
// beneficiaries, completed donations, pending/approved/rejected requests,
// expired donations) or a detailed breakdown table (donations by category,
// donations by donor, food received by NGO). Each report's exact SQL is
// shown under it via a "View SQL" disclosure, using the very same string
// that was executed - never a hand-typed approximation that could drift
// from what actually ran.
//
// The four status-count cards (Completed/Expired donations, Pending/
// Approved/Rejected requests) are NOT four separate COUNT(*) queries -
// they're read off the same two GROUP BY queries the full status tables
// further down the page use, so the page never asks the database the
// same question twice.
// =============================================================================
require_once __DIR__ . '/../includes/role_check.php';
require_role('admin');

auto_expire_all_donations($pdo);

/** Render a collapsible "View SQL" box under a report, from the exact string that ran. */
function sql_box(string $sql): void
{
    echo '<details class="sql-box"><summary>View SQL</summary><pre class="mb-0"><code>'
        . e(trim($sql)) . '</code></pre></details>';
}

// ---- Donations grouped by status (feeds the Completed/Expired cards too) ----
$sql_by_status = "SELECT status, COUNT(*) AS cnt FROM donations GROUP BY status ORDER BY cnt DESC";
$by_status = $pdo->query($sql_by_status)->fetchAll();
$status_counts = array_column($by_status, 'cnt', 'status');

// ---- Requests grouped by status (feeds the Pending/Approved/Rejected cards) ----
$sql_requests_by_status = "SELECT status, COUNT(*) AS cnt FROM requests GROUP BY status ORDER BY cnt DESC";
$requests_by_status = $pdo->query($sql_requests_by_status)->fetchAll();
$request_status_counts = array_column($requests_by_status, 'cnt', 'status');

$completed_donations = (int) ($status_counts['Completed'] ?? 0);
$expired_donations    = (int) ($status_counts['Expired'] ?? 0);
$pending_requests     = (int) ($request_status_counts['Pending'] ?? 0);
$approved_requests    = (int) ($request_status_counts['Approved'] ?? 0);
$rejected_requests    = (int) ($request_status_counts['Rejected'] ?? 0);

// ---- Report 1: Total food donated (every donation ever listed, any status) ----
$sql_total_donations = "SELECT COUNT(*) FROM donations";
$total_donations_count = (int) $pdo->query($sql_total_donations)->fetchColumn();

$sql_donated_by_unit = "SELECT unit, SUM(quantity) AS total FROM donations GROUP BY unit ORDER BY total DESC";
$donated_by_unit = $pdo->query($sql_donated_by_unit)->fetchAll();

// ---- Report 2: Total food redistributed + Report 3: Total beneficiaries ----
$sql_distribution_totals = "SELECT COUNT(*) AS distribution_count, COALESCE(SUM(beneficiary_count), 0) AS total_beneficiaries
                             FROM distributions";
$distribution_totals = $pdo->query($sql_distribution_totals)->fetch();

$sql_distributed_by_unit = "SELECT d.unit, SUM(dist.quantity_distributed) AS total
                             FROM distributions dist
                             JOIN requests r ON r.request_id = dist.request_id
                             JOIN donations d ON d.donation_id = r.donation_id
                             GROUP BY d.unit";
$distributed_by_unit = $pdo->query($sql_distributed_by_unit)->fetchAll();

// ---- Report 4: Donations by category ----
$sql_by_category = "SELECT c.category_name, COUNT(d.donation_id) AS donation_count
                     FROM categories c
                     LEFT JOIN donations d ON d.category_id = c.category_id
                     GROUP BY c.category_id, c.category_name
                     ORDER BY donation_count DESC";
$by_category = $pdo->query($sql_by_category)->fetchAll();

// ---- Report 5: Donations by donor ----
$sql_by_donor = "SELECT COALESCE(don.organization_name, u.name) AS donor_display_name,
                         COUNT(d.donation_id) AS donation_count,
                         SUM(d.status = 'Completed') AS completed_count
                  FROM donors don
                  JOIN users u ON u.user_id = don.user_id
                  LEFT JOIN donations d ON d.donor_id = don.donor_id
                  GROUP BY don.donor_id, donor_display_name
                  ORDER BY donation_count DESC";
$by_donor = $pdo->query($sql_by_donor)->fetchAll();

// ---- Report 6: Food received by NGO ----
// Two queries merged in PHP rather than one join, because joining requests
// (1:many per NGO) together with a per-unit distribution total (1:few per
// NGO) in a single query would fan out and double-count the request/
// completed totals - a classic multi-join aggregation trap. Two simple,
// obviously-correct queries beat one clever, wrong one.
$sql_ngo_requests = "SELECT n.ngo_id, COALESCE(n.organization_name, u.name) AS ngo_display_name,
                             COUNT(r.request_id) AS request_count,
                             SUM(r.status = 'Completed') AS completed_count
                      FROM ngos n
                      JOIN users u ON u.user_id = n.user_id
                      LEFT JOIN requests r ON r.ngo_id = n.ngo_id
                      GROUP BY n.ngo_id, ngo_display_name
                      ORDER BY request_count DESC";
$ngo_requests = $pdo->query($sql_ngo_requests)->fetchAll();

$sql_ngo_received_by_unit = "SELECT r.ngo_id, d.unit, SUM(dist.quantity_distributed) AS total
                              FROM distributions dist
                              JOIN requests r ON r.request_id = dist.request_id
                              JOIN donations d ON d.donation_id = r.donation_id
                              GROUP BY r.ngo_id, d.unit";
$ngo_received_rows = $pdo->query($sql_ngo_received_by_unit)->fetchAll();

$ngo_received_by_ngo = [];
foreach ($ngo_received_rows as $row) {
    $ngo_received_by_ngo[(int) $row['ngo_id']][] = format_qty((float) $row['total'], $row['unit']);
}

// ---- Extra detail: donation size + beneficiary spread (AVG/MIN/MAX) ----
$sql_donation_size_stats = "SELECT unit, COUNT(*) AS cnt, AVG(quantity) AS avg_qty, MIN(quantity) AS min_qty, MAX(quantity) AS max_qty
                             FROM donations
                             GROUP BY unit
                             ORDER BY cnt DESC";
$donation_size_stats = $pdo->query($sql_donation_size_stats)->fetchAll();

$sql_beneficiary_stats = "SELECT COUNT(*) AS cnt, AVG(beneficiary_count) AS avg_ben, MIN(beneficiary_count) AS min_ben, MAX(beneficiary_count) AS max_ben
                           FROM distributions";
$beneficiary_stats = $pdo->query($sql_beneficiary_stats)->fetch();

$current_page = 'reports';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reports - <?= e(SITE_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .stat-card .stat-num { font-size: 1.9rem; font-weight: 700; line-height: 1.1; }
        .stat-card .stat-label { font-size: .8rem; color: #6c757d; }
        .stat-card .stat-detail { font-size: .78rem; color: #6c757d; min-height: 1.1em; }
        .sql-box { margin-top: 8px; }
        .sql-box summary { cursor: pointer; font-size: .75rem; color: #6c757d; user-select: none; }
        .sql-box pre { background: #f1f3f5; border-radius: 6px; padding: 8px 10px; margin-top: 6px; font-size: .74rem; white-space: pre-wrap; }
    </style>
</head>
<body class="bg-light">
<?php require __DIR__ . '/_nav.php'; ?>

<div class="container-fluid pb-5">
    <h3 class="mb-1">Reports</h3>
    <p class="text-muted mb-4">Every figure below is read live from the current database - nothing here is cached or precomputed.</p>

    <!-- ============ STAT CARDS: reports 1, 2, 3, 7, 8, 9, 10, 11 ============ -->
    <div class="row g-3 mb-4">

        <div class="col-6 col-lg-3">
            <div class="card shadow-sm h-100 stat-card">
                <div class="card-body">
                    <div class="stat-label">Total Food Donated</div>
                    <div class="stat-num"><?= $total_donations_count ?></div>
                    <div class="stat-detail">
                        <?= $donated_by_unit ? e(implode(', ', array_map(fn($r) => format_qty((float) $r['total'], $r['unit']), $donated_by_unit))) : 'No donations yet' ?>
                    </div>
                    <?php sql_box($sql_total_donations . ";\n" . $sql_donated_by_unit); ?>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="card shadow-sm h-100 stat-card">
                <div class="card-body">
                    <div class="stat-label">Total Food Redistributed</div>
                    <div class="stat-num"><?= (int) $distribution_totals['distribution_count'] ?></div>
                    <div class="stat-detail">
                        <?= $distributed_by_unit ? e(implode(', ', array_map(fn($r) => format_qty((float) $r['total'], $r['unit']), $distributed_by_unit))) : 'None yet' ?>
                    </div>
                    <?php sql_box($sql_distribution_totals . ";\n" . $sql_distributed_by_unit); ?>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="card shadow-sm h-100 stat-card">
                <div class="card-body">
                    <div class="stat-label">Total Beneficiaries</div>
                    <div class="stat-num"><?= (int) $distribution_totals['total_beneficiaries'] ?></div>
                    <div class="stat-detail">people reached across all distributions</div>
                    <?php sql_box($sql_distribution_totals); ?>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <a href="donations.php?status=Completed" class="text-decoration-none text-reset">
            <div class="card shadow-sm h-100 stat-card border-primary">
                <div class="card-body">
                    <div class="stat-label">Completed Donations</div>
                    <div class="stat-num text-primary"><?= $completed_donations ?></div>
                    <div class="stat-detail">click to view &rarr;</div>
                    <?php sql_box($sql_by_status); ?>
                </div>
            </div>
            </a>
        </div>

        <div class="col-6 col-lg-3">
            <a href="requests.php?status=Pending" class="text-decoration-none text-reset">
            <div class="card shadow-sm h-100 stat-card border-warning">
                <div class="card-body">
                    <div class="stat-label">Pending Requests</div>
                    <div class="stat-num text-warning"><?= $pending_requests ?></div>
                    <div class="stat-detail">click to view &rarr;</div>
                    <?php sql_box($sql_requests_by_status); ?>
                </div>
            </div>
            </a>
        </div>

        <div class="col-6 col-lg-3">
            <a href="requests.php?status=Approved" class="text-decoration-none text-reset">
            <div class="card shadow-sm h-100 stat-card border-info">
                <div class="card-body">
                    <div class="stat-label">Approved Requests</div>
                    <div class="stat-num text-info"><?= $approved_requests ?></div>
                    <div class="stat-detail">click to view &rarr;</div>
                    <?php sql_box($sql_requests_by_status); ?>
                </div>
            </div>
            </a>
        </div>

        <div class="col-6 col-lg-3">
            <a href="requests.php?status=Rejected" class="text-decoration-none text-reset">
            <div class="card shadow-sm h-100 stat-card border-danger">
                <div class="card-body">
                    <div class="stat-label">Rejected Requests</div>
                    <div class="stat-num text-danger"><?= $rejected_requests ?></div>
                    <div class="stat-detail">click to view &rarr;</div>
                    <?php sql_box($sql_requests_by_status); ?>
                </div>
            </div>
            </a>
        </div>

        <div class="col-6 col-lg-3">
            <a href="donations.php?status=Expired" class="text-decoration-none text-reset">
            <div class="card shadow-sm h-100 stat-card border-secondary">
                <div class="card-body">
                    <div class="stat-label">Expired Donations</div>
                    <div class="stat-num text-secondary"><?= $expired_donations ?></div>
                    <div class="stat-detail">click to view &rarr;</div>
                    <?php sql_box($sql_by_status); ?>
                </div>
            </div>
            </a>
        </div>

    </div>

    <!-- ============ DETAIL TABLES: reports 4, 5, 6 ============ -->
    <div class="row g-4 mb-2">

        <div class="col-lg-4">
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
              <?php sql_box($sql_by_category); ?>
            </div>
          </div>
        </div>

        <div class="col-lg-4">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <h5 class="card-title">Donations by Donor</h5>
              <table class="table table-sm mb-0">
                <thead><tr><th>Donor</th><th class="text-end">Donations</th><th class="text-end">Completed</th></tr></thead>
                <tbody>
                  <?php if (!$by_donor): ?>
                    <tr><td colspan="3" class="text-muted text-center">No donors yet.</td></tr>
                  <?php endif; ?>
                  <?php foreach ($by_donor as $row): ?>
                    <tr>
                      <td><?= e($row['donor_display_name']) ?></td>
                      <td class="text-end"><?= (int) $row['donation_count'] ?></td>
                      <td class="text-end"><?= (int) $row['completed_count'] ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
              <?php sql_box($sql_by_donor); ?>
            </div>
          </div>
        </div>

        <div class="col-lg-4">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <h5 class="card-title">Food Received by NGO</h5>
              <table class="table table-sm mb-0">
                <thead><tr><th>NGO</th><th class="text-end">Requests</th><th class="text-end">Completed</th><th>Food Received</th></tr></thead>
                <tbody>
                  <?php if (!$ngo_requests): ?>
                    <tr><td colspan="4" class="text-muted text-center">No NGOs yet.</td></tr>
                  <?php endif; ?>
                  <?php foreach ($ngo_requests as $row): ?>
                    <tr>
                      <td><?= e($row['ngo_display_name']) ?></td>
                      <td class="text-end"><?= (int) $row['request_count'] ?></td>
                      <td class="text-end"><?= (int) $row['completed_count'] ?></td>
                      <td class="small">
                        <?= isset($ngo_received_by_ngo[(int) $row['ngo_id']])
                            ? e(implode(', ', $ngo_received_by_ngo[(int) $row['ngo_id']]))
                            : '<span class="text-muted">None yet</span>' ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
              <?php sql_box($sql_ngo_requests . ";\n" . $sql_ngo_received_by_unit); ?>
            </div>
          </div>
        </div>

    </div>

    <!-- ============ MORE DETAIL: full status breakdowns + AVG/MIN/MAX ============ -->
    <h5 class="mt-4 mb-3 text-muted">More Detail</h5>
    <div class="row g-4">

        <div class="col-lg-6">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <h5 class="card-title">Donations by Status <small class="text-muted">(all statuses)</small></h5>
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
              <?php sql_box($sql_by_status); ?>
            </div>
          </div>
        </div>

        <div class="col-lg-6">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <h5 class="card-title">Requests by Status <small class="text-muted">(all statuses)</small></h5>
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
              <?php sql_box($sql_requests_by_status); ?>
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
              <?php sql_box($sql_donation_size_stats); ?>
            </div>
          </div>
        </div>

        <div class="col-lg-6">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <h5 class="card-title">Beneficiaries per Distribution</h5>
              <p class="mb-0">
                <?php if ((int) $beneficiary_stats['cnt'] === 0): ?>
                  <span class="text-muted">None yet</span>
                <?php else: ?>
                  avg <strong><?= e(number_format((float) $beneficiary_stats['avg_ben'], 1)) ?></strong>,
                  smallest <strong><?= (int) $beneficiary_stats['min_ben'] ?></strong>,
                  largest <strong><?= (int) $beneficiary_stats['max_ben'] ?></strong>
                  (across <?= (int) $beneficiary_stats['cnt'] ?> distributions)
                <?php endif; ?>
              </p>
              <?php sql_box($sql_beneficiary_stats); ?>
            </div>
          </div>
        </div>

    </div>
</div>
</body>
</html>
