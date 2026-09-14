<?php
// PUBLIC LANDING PAGE
// - Hero, feature highlights, live impact stats pulled from the real
//   database (read-only, no sensitive data), and a call to register/login
require_once __DIR__ . '/includes/auth.php';

// Impact stats: safe to show publicly (aggregate counts only, no PII).
$stats = $pdo->query(
    "SELECT
        (SELECT COUNT(*) FROM donors) AS donor_count,
        (SELECT COUNT(*) FROM ngos) AS ngo_count,
        (SELECT COUNT(*) FROM donations) AS donation_count,
        (SELECT COALESCE(SUM(beneficiary_count), 0) FROM distributions) AS beneficiary_count"
)->fetch();

$page_title = 'Home';
$current_page = 'home';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/navbar.php';
?>

<section class="fb-hero py-5">
  <div class="container py-4">
    <div class="row align-items-center g-5">
      <div class="col-lg-7">
        <span class="badge rounded-pill text-bg-success mb-3">Food rescue, made simple</span>
        <h1 class="display-5 mb-3">Surplus food shouldn't go to waste when people are going hungry.</h1>
        <p class="lead text-muted mb-4">FoodBridge connects restaurants, bakeries, and home cooks with local NGOs, so extra food finds its way to people who need it - tracked from donation to pickup to distribution.</p>
        <div class="d-flex flex-wrap gap-2">
          <a href="register.php" class="btn btn-success btn-lg px-4">Get Started</a>
          <a href="how-it-works.php" class="btn btn-outline-success btn-lg px-4">See How It Works</a>
        </div>
      </div>
      <div class="col-lg-5">
        <div class="card shadow-sm">
          <div class="card-body p-4">
            <h6 class="text-uppercase text-muted small mb-3">Our impact so far</h6>
            <div class="row g-3 text-center">
              <div class="col-6">
                <div class="fs-2 fw-bold text-success"><?= (int) $stats['donation_count'] ?></div>
                <div class="small text-muted">Food donations listed</div>
              </div>
              <div class="col-6">
                <div class="fs-2 fw-bold text-success"><?= (int) $stats['beneficiary_count'] ?></div>
                <div class="small text-muted">People reached</div>
              </div>
              <div class="col-6">
                <div class="fs-2 fw-bold text-success"><?= (int) $stats['donor_count'] ?></div>
                <div class="small text-muted">Active donors</div>
              </div>
              <div class="col-6">
                <div class="fs-2 fw-bold text-success"><?= (int) $stats['ngo_count'] ?></div>
                <div class="small text-muted">Partner NGOs</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="py-5">
  <div class="container py-4">
    <div class="row text-center mb-5">
      <div class="col-lg-8 mx-auto">
        <h2 class="mb-2">Built for both sides of food rescue</h2>
        <p class="text-muted">Whichever side you're on, FoodBridge gives you exactly the tools you need - nothing more.</p>
      </div>
    </div>
    <div class="row g-4">
      <div class="col-md-4">
        <div class="card h-100 p-3">
          <div class="card-body">
            <div class="fb-icon-tile">🍱</div>
            <h5>For Donors</h5>
            <p class="text-muted">List surplus food in minutes - restaurants, bakeries, supermarkets, or an individual with a big pot of extra biryani. Track every donation's status from listing to pickup.</p>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card h-100 p-3">
          <div class="card-body">
            <div class="fb-icon-tile">🏛️</div>
            <h5>For NGOs</h5>
            <p class="text-muted">Browse available food nearby, filter by category and how soon it expires, and request exactly what your organization can use and distribute.</p>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card h-100 p-3">
          <div class="card-body">
            <div class="fb-icon-tile">✅</div>
            <h5>Full Accountability</h5>
            <p class="text-muted">Every request is reviewed, every pickup is scheduled, and every distribution is recorded - quantity, beneficiaries, and location, start to finish.</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="py-5 bg-white border-top">
  <div class="container py-4 text-center">
    <h2 class="mb-3">Ready to make surplus food count?</h2>
    <p class="text-muted mb-4">Registration takes less than two minutes, whether you're donating or receiving.</p>
    <a href="register.php" class="btn btn-success btn-lg px-5">Create Your Account</a>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
