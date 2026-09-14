<?php
// SUBMIT A FOOD REQUEST (protected: require_role('ngo'))
// - POST-only (state-changing action, never a plain GET link)
// - Validates requested_quantity <= available quantity
// - Re-checks the donation is still Available and not expired at the
//   moment of submission (not just when the page was first loaded)
// - INSERT into `requests` with status = 'Pending', and flips the
//   donation to status = 'Requested' so it drops off Available Food
//   and no second NGO can request the same donation
require_once __DIR__ . '/../includes/role_check.php';
require_role('ngo');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . 'ngo/available_food.php');
}

$ngo_id = get_ngo_id($pdo, current_user_id());
if ($ngo_id === null) {
    die('NGO profile not found for this account. Please contact support.');
}

$donation_id        = filter_input(INPUT_POST, 'donation_id', FILTER_VALIDATE_INT);
$requested_quantity = trim($_POST['requested_quantity'] ?? '');

if (!$donation_id) {
    redirect(BASE_URL . 'ngo/available_food.php');
}

$errors = [];

if (!is_numeric($requested_quantity) || (float) $requested_quantity <= 0) {
    $errors[] = 'Requested quantity must be a number greater than 0.';
}

// Fetch the current donation state fresh (not trusting anything from the form).
$stmt = $pdo->prepare('SELECT donation_id, quantity, status, expiry_time FROM donations WHERE donation_id = :id LIMIT 1');
$stmt->execute([':id' => $donation_id]);
$donation = $stmt->fetch();

if (!$donation) {
    redirect(BASE_URL . 'ngo/available_food.php');
}

if ($donation['status'] !== 'Available' || strtotime($donation['expiry_time']) <= time()) {
    $errors[] = 'This donation is no longer available to request.';
}

// "requested_quantity <= available quantity"
if (!$errors && (float) $requested_quantity > (float) $donation['quantity']) {
    $errors[] = 'Requested quantity cannot exceed the available quantity (' .
        rtrim(rtrim(number_format((float) $donation['quantity'], 2), '0'), '.') . ').';
}

if ($errors) {
    $_SESSION['food_details_errors'] = $errors;
    redirect(BASE_URL . 'ngo/food_details.php?id=' . $donation_id);
}

try {
    $pdo->beginTransaction();

    // Optimistic lock: only succeeds if the donation is still Available
    // right now. If another NGO's request beat this one to it, rowCount()
    // will be 0 and we roll back instead of creating an orphaned request.
    $stmt = $pdo->prepare(
        "UPDATE donations SET status = 'Requested'
         WHERE donation_id = :id AND status = 'Available' AND expiry_time > NOW()"
    );
    $stmt->execute([':id' => $donation_id]);

    if ($stmt->rowCount() === 0) {
        $pdo->rollBack();
        $_SESSION['food_details_errors'] = ['This donation was just claimed by another NGO. Please try a different one.'];
        redirect(BASE_URL . 'ngo/food_details.php?id=' . $donation_id);
    }

    $stmt = $pdo->prepare(
        "INSERT INTO requests (donation_id, ngo_id, requested_quantity, status)
         VALUES (:donation_id, :ngo_id, :requested_quantity, 'Pending')"
    );
    $stmt->execute([
        ':donation_id'        => $donation_id,
        ':ngo_id'             => $ngo_id,
        ':requested_quantity' => (float) $requested_quantity,
    ]);

    $pdo->commit();
    redirect(BASE_URL . 'ngo/my_requests.php?msg=requested');

} catch (PDOException $ex) {
    $pdo->rollBack();
    error_log('FoodBridge request_food failed: ' . $ex->getMessage());
    $_SESSION['food_details_errors'] = ['Could not submit your request. Please try again later.'];
    redirect(BASE_URL . 'ngo/food_details.php?id=' . $donation_id);
}
