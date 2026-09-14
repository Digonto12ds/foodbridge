<?php
/**
 * FoodBridge - Role-based access control guard
 * ==============================================
 * Include this at the very TOP of every protected page (before any
 * HTML output) and call require_role() with the role that page
 * belongs to. It stops two things:
 *
 *   1. An anonymous visitor opening a protected page directly.
 *   2. A logged-in user opening a page for a role that isn't theirs
 *      (e.g. a donor trying to load admin/dashboard.php).
 *
 * Usage, e.g. in donor/dashboard.php:
 *     require_once __DIR__ . '/../includes/role_check.php';
 *     require_role('donor');
 *     // ... rest of the page, only reachable by a logged-in donor
 */

require_once __DIR__ . '/auth.php';

/**
 * Must be logged in (any role) or get bounced to login.php. Also kills
 * the session immediately if an admin deactivated this account after
 * it was already logged in - being logged out at the *next* login
 * attempt isn't good enough for a "deactivate this user" control.
 */
function require_login(): void
{
    if (!is_logged_in()) {
        redirect(BASE_URL . 'login.php?error=login_required');
    }

    global $pdo;
    $stmt = $pdo->prepare('SELECT is_active FROM users WHERE user_id = :id LIMIT 1');
    $stmt->execute([':id' => current_user_id()]);
    $row = $stmt->fetch();

    if (!$row || (int) $row['is_active'] === 0) {
        logout_user();
        redirect(BASE_URL . 'login.php?error=account_deactivated');
    }
}

/** Must be logged in AND hold the given role, or get redirected. */
function require_role(string $role): void
{
    require_login();

    if (current_user_role() !== $role) {
        // They're logged in, just not allowed here - send them to the
        // dashboard they DO belong on instead of a generic error page.
        redirect(dashboard_path_for_role(current_user_role()));
    }
}
