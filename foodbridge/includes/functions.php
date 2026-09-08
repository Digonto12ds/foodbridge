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
