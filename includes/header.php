<?php
/**
 * Shared site header.
 * Expects optional $page_title to be set before include.
 * Works from both root pages and /admin pages via $base_path.
 */
$base_path = $base_path ?? '';
$page_title = $page_title ?? 'BU Bank Ltd — Bank Loan Management System';
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($page_title) ?></title>
<link rel="icon" href="<?= $base_path ?>assets/images/logo.png">
<link rel="stylesheet" href="<?= $base_path ?>assets/css/style.css">
</head>
<body>
<header class="site-header">
  <div class="container">
    <a href="<?= $base_path ?>index.php" class="brand">
      <span class="mark"><img src="<?= $base_path ?>assets/images/logo.png" alt="BU Bank Logo"></span>
      <span>BU Bank LTD<span class="tag">Online Loan System</span></span>
    </a>
    <nav class="main-nav">
      <?php if (is_logged_in() && !is_admin()): ?>
        <a href="<?= $base_path ?>index.php">Home</a>
        <a href="<?= $base_path ?>dashboard.php">Dashboard</a>
        <a href="<?= $base_path ?>loan_apply.php">Apply for loan</a>
        <a href="<?= $base_path ?>loan_history.php">Loan history</a>
        <a href="<?= $base_path ?>logout.php" class="btn btn-ghost btn-sm">Log out</a>
      <?php elseif (is_admin()): ?>
        <a href="<?= $base_path ?>index.php">Home</a>
        <a href="<?= $base_path ?>admin/dashboard.php">Admin dashboard</a>
        <a href="<?= $base_path ?>admin/search_customer.php">Search customers</a>
        <a href="<?= $base_path ?>admin/reports.php">Reports</a>
        <a href="<?= $base_path ?>logout.php" class="btn btn-ghost btn-sm">Log out</a>
      <?php else: ?>
        <a href="<?= $base_path ?>index.php">Home</a>
        <a href="<?= $base_path ?>index.php#guidance">Banking Guide</a>
        <a href="<?= $base_path ?>index.php#governor-messages">Governor Messages</a>
        <a href="<?= $base_path ?>index.php#services">Our Services</a>
        <a href="<?= $base_path ?>login.php">Log in</a>
        <a href="<?= $base_path ?>register.php" class="btn btn-brass btn-sm">Open an account</a>
      <?php endif; ?>
    </nav>
  </div>
</header>
