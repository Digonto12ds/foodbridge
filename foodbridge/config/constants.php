<?php
/**
 * FoodBridge - App-wide constants.
 * Included (indirectly) by every page via includes/auth.php.
 */

define('SITE_NAME', 'FoodBridge');

// Absolute site path from the domain root (no scheme/host, so it works
// on localhost or any future domain). Used to build reliable redirect
// URLs regardless of which subfolder (donor/, ngo/, admin/) a page runs
// from. Update this only if the project folder is renamed or moved.
define('BASE_URL', '/Project/foodbridge/');
