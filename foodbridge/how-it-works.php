<?php
// PUBLIC HOW IT WORKS PAGE
// - User-facing walkthrough of the donation -> request -> approval ->
//   pickup -> distribution pipeline (no internal implementation detail)
require_once __DIR__ . '/includes/auth.php';

$page_title = 'How It Works';
$current_page = 'how';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/navbar.php';
?>

<section class="fb-hero py-5">
  <div class="container py-4 text-center">
    <div class="col-lg-8 mx-auto">
      <h1 class="display-6 mb-3">From a kitchen to someone's plate, in five steps.</h1>
      <p class="lead text-muted">Every donation on FoodBridge follows the same accountable path - here's exactly what happens, and who does what.</p>
    </div>
  </div>
</section>

<section class="py-5">
  <div class="container py-3">
    <div class="col-lg-8 mx-auto">

      <div class="fb-step mb-5">
        <div class="fb-step-num">1</div>
        <h4>A donor lists surplus food</h4>
        <p class="text-muted">A restaurant, bakery, supermarket, or individual signs up as a Donor and lists what they have - food name, category, quantity, when it was prepared, when it expires, and where to pick it up. It's immediately visible to NGOs as <span class="badge bg-success">Available</span>.</p>
      </div>

      <div class="fb-step mb-5">
        <div class="fb-step-num">2</div>
        <h4>An NGO finds it and requests it</h4>
        <p class="text-muted">Registered NGOs browse everything currently available, searching by name, filtering by category, or narrowing to what's expiring soonest. When one fits, the NGO requests a quantity - the donation is marked <span class="badge bg-warning text-dark">Requested</span> so it isn't claimed twice.</p>
      </div>

      <div class="fb-step mb-5">
        <div class="fb-step-num">3</div>
        <h4>An admin reviews and approves</h4>
        <p class="text-muted">An administrator checks the request and either approves it - scheduling a pickup date and time in the same step - or rejects it, which frees the donation back up for another NGO to request. An approved request moves the donation to <span class="badge bg-info text-dark">Claimed</span>.</p>
      </div>

      <div class="fb-step mb-5">
        <div class="fb-step-num">4</div>
        <h4>The food is picked up</h4>
        <p class="text-muted">The NGO collects the food at the scheduled time and location. The pickup is tracked from <span class="badge bg-info text-dark">Scheduled</span> to <span class="badge bg-primary">Picked Up</span> to <span class="badge bg-primary">Completed</span>, so everyone can see exactly where things stand.</p>
      </div>

      <div class="fb-step">
        <div class="fb-step-num">5</div>
        <h4>It reaches the people who need it</h4>
        <p class="text-muted">Once the food has been distributed, the admin records what actually happened - how much was given out, how many people it reached, and where - closing out both the request and the original donation as <span class="badge bg-primary">Completed</span>.</p>
      </div>

    </div>
  </div>
</section>

<section class="py-5 bg-white border-top text-center">
  <div class="container py-3">
    <h2 class="mb-3">Which side are you on?</h2>
    <p class="text-muted mb-4">Register as a Donor to start giving, or as an NGO to start requesting.</p>
    <a href="register.php" class="btn btn-success btn-lg px-4 me-2">Register</a>
    <a href="login.php" class="btn btn-outline-success btn-lg px-4">Log In</a>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
