<?php
/**
 * FoodBridge - Shared <head> + opening <body>
 * ================================================
 * Included at the top of nearly every page, right after the page's own
 * PHP logic (role checks, queries, etc.) and right before its HTML.
 * Optionally set $page_title first:
 *
 *     $page_title = 'Donor Dashboard';
 *     require __DIR__ . '/../includes/header.php';
 *
 * Requires e(), SITE_NAME, and BASE_URL to already be defined, which any
 * page that has included auth.php (directly, or via role_check.php)
 * already has.
 */
$page_title = $page_title ?? '';
$full_title = $page_title !== ''
    ? $page_title . ' - ' . SITE_NAME
    : SITE_NAME . ' - Food Donation & Redistribution';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="FoodBridge connects surplus food donors with NGOs to redistribute food before it goes to waste.">
    <title><?= e($full_title) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= e(BASE_URL) ?>css/style.css" rel="stylesheet">
</head>
<body>
