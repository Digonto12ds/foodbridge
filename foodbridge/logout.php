<?php
/**
 * FoodBridge - Logout
 * Destroys the current session (see logout_user() in includes/auth.php)
 * and sends the user back to the login page.
 */
require_once __DIR__ . '/includes/auth.php';

logout_user();
redirect(BASE_URL . 'login.php?loggedout=1');
