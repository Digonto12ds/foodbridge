<?php
/**
 * FoodBridge - Reusable helper functions.
 * Included by includes/auth.php, so any page that includes auth.php
 * (directly or via role_check.php) automatically has these available.
 */

/** Trim whitespace from user input before validating/storing it. */
function sanitize_input(string $value): string
{
    return trim($value);
}

/** Escape a value for safe HTML output (prevents XSS in echoed data). */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/** Simple email format check. */
function is_valid_email(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/** Redirect the browser to $url and stop executing this script. */
function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/**
 * Look up the donor_id (donors table) that belongs to a logged-in user.
 * The `donations` table is keyed by donor_id, not user_id, so every
 * donor page needs this to scope queries to "only my own donations".
 */
function get_donor_id(PDO $pdo, int $user_id): ?int
{
    $stmt = $pdo->prepare('SELECT donor_id FROM donors WHERE user_id = :user_id LIMIT 1');
    $stmt->execute([':user_id' => $user_id]);
    $row = $stmt->fetch();

    return $row ? (int) $row['donor_id'] : null;
}

/**
 * Business rule: "Expired donations cannot be requested."
 * Flips any of this donor's still-'Available' donations whose expiry
 * has passed over to 'Expired', so status is always accurate before
 * it's displayed or acted on. Cheap to call on every donor page load
 * since it's scoped to one donor_id.
 */
function auto_expire_donations(PDO $pdo, int $donor_id): void
{
    $stmt = $pdo->prepare(
        "UPDATE donations
         SET status = 'Expired'
         WHERE donor_id = :donor_id AND status = 'Available' AND expiry_time <= NOW()"
    );
    $stmt->execute([':donor_id' => $donor_id]);
}

/**
 * Look up the ngo_id (ngos table) that belongs to a logged-in user.
 * The `requests` table is keyed by ngo_id, not user_id, so every ngo
 * page needs this to scope queries to "only my own requests".
 */
function get_ngo_id(PDO $pdo, int $user_id): ?int
{
    $stmt = $pdo->prepare('SELECT ngo_id FROM ngos WHERE user_id = :user_id LIMIT 1');
    $stmt->execute([':user_id' => $user_id]);
    $row = $stmt->fetch();

    return $row ? (int) $row['ngo_id'] : null;
}

/**
 * Same auto-expire rule as auto_expire_donations(), but across every
 * donor's donations. NGOs browse the whole marketplace, not one
 * donor's donations, so there's no single donor_id to scope this to.
 */
function auto_expire_all_donations(PDO $pdo): void
{
    $pdo->exec(
        "UPDATE donations
         SET status = 'Expired'
         WHERE status = 'Available' AND expiry_time <= NOW()"
    );
}

// Business rules for which donor actions make sense for a given status:
// - Edit: only while the donation hasn't been requested/claimed/etc yet.
// - Cancel: only while it's still Available or Requested (not already
//   Claimed/Completed/Expired/Cancelled). "Cancelled donations cannot be
//   requested" - once cancelled there is nothing further to do with them.
function can_edit_donation(string $status): bool
{
    return $status === 'Available';
}

function can_cancel_donation(string $status): bool
{
    return in_array($status, ['Available', 'Requested'], true);
}

/**
 * Bootstrap badge class for a status pill. Shared across donation
 * statuses (Available/Requested/Claimed/Completed/Expired/Cancelled),
 * request statuses (Pending/Approved/Rejected/Completed/Cancelled), and
 * pickup statuses (Scheduled/Picked Up/Completed/Cancelled) - the keys
 * don't collide, and where they overlap (Completed, Cancelled) the same
 * color is the right choice anyway.
 */
function status_badge_class(string $status): string
{
    $map = [
        'Available'  => 'bg-success',
        'Requested'  => 'bg-warning text-dark',
        'Claimed'    => 'bg-info text-dark',
        'Completed'  => 'bg-primary',
        'Expired'    => 'bg-secondary',
        'Cancelled'  => 'bg-danger',
        'Pending'    => 'bg-warning text-dark',
        'Approved'   => 'bg-info text-dark',
        'Rejected'   => 'bg-danger',
        'Scheduled'  => 'bg-info text-dark',
        'Picked Up'  => 'bg-primary',
    ];

    return $map[$status] ?? 'bg-secondary';
}

/** Format a quantity + unit for display, trimming trailing zeros ("12.50" -> "12.5", "10.00" -> "10"). */
function format_qty(float $qty, string $unit): string
{
    return rtrim(rtrim(number_format($qty, 2), '0'), '.') . ' ' . $unit;
}

/**
 * Flip a user's is_active flag (admin-only action). Refuses to let an
 * admin deactivate their own account, which would otherwise lock them
 * out with no other admin necessarily available to undo it.
 *
 * @return string a short result code: 'activated', 'deactivated', or 'self'
 */
function toggle_user_active(PDO $pdo, int $target_user_id, int $current_admin_user_id): string
{
    if ($target_user_id === $current_admin_user_id) {
        return 'self';
    }

    $stmt = $pdo->prepare('SELECT is_active FROM users WHERE user_id = :id LIMIT 1');
    $stmt->execute([':id' => $target_user_id]);
    $row = $stmt->fetch();

    if (!$row) {
        return 'self'; // not found - treat as a no-op the same way as blocked
    }

    $new_status = (int) $row['is_active'] === 1 ? 0 : 1;

    $stmt = $pdo->prepare('UPDATE users SET is_active = :is_active WHERE user_id = :id');
    $stmt->execute([':is_active' => $new_status, ':id' => $target_user_id]);

    return $new_status === 1 ? 'activated' : 'deactivated';
}

/**
 * Parse an HTML datetime-local value ("Y-m-d\TH:i" or with seconds) into
 * MySQL DATETIME format ("Y-m-d H:i:s"). Returns null if the value is
 * missing or not a valid date/time, so callers can turn that into a
 * validation error instead of inserting garbage.
 */
function parse_datetime_local(string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    $formats = ['Y-m-d\TH:i:s', 'Y-m-d\TH:i', 'Y-m-d H:i:s', 'Y-m-d H:i'];
    foreach ($formats as $format) {
        $dt = DateTime::createFromFormat($format, $value);
        if ($dt !== false) {
            return $dt->format('Y-m-d H:i:s');
        }
    }

    return null;
}
