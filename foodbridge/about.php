<?php
// PUBLIC ABOUT PAGE
// - Mission, the problem FoodBridge addresses, and the three roles
require_once __DIR__ . '/includes/auth.php';

$page_title = 'About';
$current_page = 'about';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/navbar.php';
?>

<section class="fb-hero py-5">
  <div class="container py-4 text-center">
    <div class="col-lg-8 mx-auto">
      <h1 class="display-6 mb-3">Good food is too valuable to waste.</h1>
      <p class="lead text-muted">FoodBridge exists to close the gap between food that's about to go uneaten and the people who could use it - reliably, and with a clear record of what happened to it.</p>
    </div>
  </div>
</section>

<section class="py-5">
  <div class="container py-3">
    <div class="row g-5 align-items-center mb-5">
      <div class="col-lg-6">
        <h2 class="mb-3">The problem</h2>
        <p class="text-muted">Restaurants, bakeries, supermarkets, and home cooks end up with good, safe surplus food more often than not - and most of it never reaches anyone. Meanwhile, NGOs feeding people in their communities are often working with far less than they need, and have no easy way to know what's available nearby, right now.</p>
      </div>
      <div class="col-lg-6">
        <h2 class="mb-3">What FoodBridge does</h2>
        <p class="text-muted">It's a direct line between the two: a donor lists what they have and where to pick it up, an NGO requests exactly what it can use, and the handoff - approval, pickup, and final distribution - is tracked the whole way through, so nothing quietly falls through the cracks.</p>
      </div>
    </div>
  </div>
</section>

<section class="py-5 bg-white border-top">
  <div class="container py-3">
    <div class="row text-center mb-5">
      <div class="col-lg-7 mx-auto">
        <h2 class="mb-2">Three roles, one platform</h2>
        <p class="text-muted">Everyone on FoodBridge has exactly the tools their part of the job needs.</p>
      </div>
    </div>
    <div class="row g-4">
      <div class="col-md-4">
        <div class="card h-100 p-3">
          <div class="card-body">
            <div class="fb-icon-tile">🍱</div>
            <h5>Donors</h5>
            <p class="text-muted mb-0">Restaurants, hotels, bakeries, supermarkets, and individuals who list surplus food - with a full history of everything they've given.</p>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card h-100 p-3">
          <div class="card-body">
            <div class="fb-icon-tile">🏛️</div>
            <h5>NGOs</h5>
            <p class="text-muted mb-0">Registered charities that browse what's currently available, request what they can distribute, and track every pickup through to delivery.</p>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card h-100 p-3">
          <div class="card-body">
            <div class="fb-icon-tile">🛡️</div>
            <h5>Admins</h5>
            <p class="text-muted mb-0">Review and approve requests, schedule pickups, and keep a clean record of every distribution - quantity, beneficiaries, and location.</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="py-5">
  <div class="container py-3 text-center">
    <h2 class="mb-3">Want to see the process end to end?</h2>
    <a href="how-it-works.php" class="btn btn-outline-success btn-lg px-4 me-2">How It Works</a>
    <a href="register.php" class="btn btn-success btn-lg px-4">Join FoodBridge</a>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
