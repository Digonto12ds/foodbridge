<?php
/**
 * FoodBridge - Authentication core
 * =================================
 * One place for: starting the session, checking who (if anyone) is
 * logged in, logging a user in/out, and knowing which dashboard a
 * role belongs on. Every other auth file (login.php, logout.php,
 * register.php, includes/role_check.php) includes THIS file instead
 * of repeating session/DB logic.
 *
 * Include with:
 *     require_once __DIR__ . '/includes/auth.php';        // from root pages
 *     require_once __DIR__ . '/../includes/auth.php';     // from a subfolder
 *
 * That single line also pulls in $pdo (config/database.php), the
 * BASE_URL / SITE_NAME constants, and the helpers in functions.php.
 */

require_once __DIR__ . '/../config/database.php';   // gives us $pdo
require_once __DIR__ . '/../config/constants.php';  // BASE_URL, SITE_NAME
require_once __DIR__ . '/functions.php';             // e(), redirect(), etc.

// Start the session exactly once, no matter how many included files call this.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/** Is anyone currently logged in? */
function is_logged_in(): bool
{
    return isset($_SESSION['user_id'], $_SESSION['role']);
}

function current_user_id(): ?int
{
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
}

function current_user_role(): ?string
{
    return $_SESSION['role'] ?? null;
}

function current_user_name(): ?string
{
    return $_SESSION['name'] ?? null;
}

/** Where should this role land after login / when it hits the wrong page? */
function dashboard_path_for_role(?string $role): string
{
    switch ($role) {
        case 'donor': return BASE_URL . 'donor/dashboard.php';
        case 'ngo':   return BASE_URL . 'ngo/dashboard.php';
        case 'admin': return BASE_URL . 'admin/dashboard.php';
        default:      return BASE_URL . 'login.php';
    }
}

/**
 * Verify email + password against the `users` table and, on success,
 * start an authenticated session.
 *
 * @return array|string|false the user row on success, false on invalid
 *         credentials, or the string 'inactive' if the credentials are
 *         correct but an admin has deactivated the account.
 */
function attempt_login(PDO $pdo, string $email, string $password)
{
    $stmt = $pdo->prepare(
        'SELECT user_id, name, email, password, role, is_active FROM users WHERE email = :email LIMIT 1'
    );
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    // Same generic failure whether the email doesn't exist or the password
    // is wrong - never reveal which one it was.
    if (!$user || !password_verify($password, $user['password'])) {
        return false;
    }

    if ((int) $user['is_active'] === 0) {
        return 'inactive';
    }

    // Regenerate the session ID on login to prevent session fixation attacks.
    session_regenerate_id(true);

    $_SESSION['user_id'] = (int) $user['user_id'];
    $_SESSION['name']    = $user['name'];
    $_SESSION['role']    = $user['role'];

    return $user;
}

/** Destroy the current session completely (used by logout.php). */
function logout_user(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}
