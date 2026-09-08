<?php
// CANCEL A DONATION (protected: require_role('donor'))
// - POST-only action (state-changing, so never a plain GET link)
// - UPDATE donations SET status = 'Cancelled' WHERE donation_id = ? AND donor_id = ?
// - Only allowed while status is 'Available' or 'Requested'
//   (not already Claimed/Completed/Expired/Cancelled)
require_once __DIR__ . '/../includes/role_check.php';
require_role('donor');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . 'donor/my_donations.php');
}

$donor_id = get_donor_id($pdo, current_user_id());
if ($donor_id === null) {
    die('Donor profile not found for this account. Please contact support.');
}

$donation_id = filter_input(INPUT_POST, 'donation_id', FILTER_VALIDATE_INT);
if (!$donation_id) {
    redirect(BASE_URL . 'donor/my_donations.php?error=not_found');
}

try {
    // The donor_id + status conditions inside the WHERE clause are what
    // actually enforce the rules - not just the button being hidden in
    // the UI - so a tampered request can't cancel someone else's
    // donation or one that's already been claimed/completed.
    $stmt = $pdo->prepare(
        "UPDATE donations
         SET status = 'Cancelled'
         WHERE donation_id = :id AND donor_id = :donor_id AND status IN ('Available', 'Requested')"
    );
    $stmt->execute([':id' => $donation_id, ':donor_id' => $donor_id]);

    if ($stmt->rowCount() === 0) {
        redirect(BASE_URL . 'donor/my_donations.php?error=not_allowed');
    }

    redirect(BASE_URL . 'donor/my_donations.php?msg=cancelled');

} catch (PDOException $ex) {
    error_log('FoodBridge cancel_donation failed: ' . $ex->getMessage());
    redirect(BASE_URL . 'donor/my_donations.php?error=not_allowed');
}
