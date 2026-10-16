<?php
// Shared header + navigation for logged-in pages.
// Set $pageTitle before including this file.
$pageTitle = $pageTitle ?? 'CK Pharmacy';
$current = basename($_SERVER['PHP_SELF']);
$nav = [
    'dashboard.php'  => 'Dashboard',
    'products.php'   => 'Products',
    'inventory.php'  => 'Inventory',
    'sales.php'      => 'Sales',
    'expiry.php'     => 'Expiration',
    'suppliers.php'  => 'Suppliers',
    'reports.php'    => 'Reports',
];
if (is_admin()) {
    $nav['users.php']  = 'Users';
    $nav['backup.php'] = 'Backup';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> | CK Pharmacy</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<header class="hero hero-small">
    <h1>CK Pharmacy</h1>
    <p>Sales, Inventory, and Management System</p>
</header>
<nav class="topnav">
    <div class="topnav-links">
        <?php foreach ($nav as $file => $label): ?>
            <a href="<?= $file ?>" class="<?= $current === $file ? 'active' : '' ?>"><?= $label ?></a>
        <?php endforeach; ?>
    </div>
    <div class="topnav-user">
        <?= e($_SESSION['user']['full_name']) ?> (<?= e($_SESSION['user']['role']) ?>)
        <a href="logout.php" class="btn btn-small btn-light">Logout</a>
    </div>
</nav>
<main class="container">
    <?= flash() ?>
