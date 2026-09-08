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

/** Bootstrap badge class for a donation status, for consistent status pills. */
function status_badge_class(string $status): string
{
    $map = [
        'Available' => 'bg-success',
        'Requested' => 'bg-warning text-dark',
        'Claimed'   => 'bg-info text-dark',
        'Completed' => 'bg-primary',
        'Expired'   => 'bg-secondary',
        'Cancelled' => 'bg-danger',
    ];

    return $map[$status] ?? 'bg-secondary';
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
