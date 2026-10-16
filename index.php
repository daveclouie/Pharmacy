<?php
// Landing page + login. This is the starting page of the system.
require_once 'includes/config.php';

if (!empty($_SESSION['user'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ?');
    $stmt->execute([trim($_POST['username'] ?? '')]);
    $user = $stmt->fetch();

    if ($user && password_verify($_POST['password'] ?? '', $user['password'])) {
        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id' => $user['id'],
            'username' => $user['username'],
            'full_name' => $user['full_name'],
            'role' => $user['role'],
        ];
        header('Location: dashboard.php');
        exit;
    }
    $error = 'Invalid username or password.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CK Pharmacy</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="landing">

<header class="landing-bar">
    <div class="brand"><span class="brand-cross">+</span> CK Pharmacy</div>
</header>

<main class="landing-main">
    <section class="landing-info">
        <p class="eyebrow">Your trusted neighborhood pharmacy</p>
        <h1>Quality medicines and medical supplies, <span>when you need them.</span></h1>
        <p class="lead">CK Pharmacy keeps your prescriptions, over-the-counter medicines, vitamins, and everyday health essentials stocked, checked, and ready.</p>

        <ul class="highlights">
            <li><strong>Medicines</strong><span>Prescription and over-the-counter</span></li>
            <li><strong>Medical Supplies</strong><span>First aid and everyday essentials</span></li>
            <li><strong>Vitamins &amp; Care</strong><span>Supplements and personal care</span></li>
            <li><strong>Fresh Stock</strong><span>Expiration dates monitored</span></li>
        </ul>
    </section>

    <aside class="login-card">
        <h2>Welcome back</h2>
        <p class="muted">Sign in to the pharmacy system.</p>

        <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

        <form method="post" autocomplete="off">
            <?= csrf_field() ?>
            <p><label for="username">Username</label>
               <input type="text" id="username" name="username" required autofocus></p>
            <p><label for="password">Password</label>
               <input type="password" id="password" name="password" required></p>
            <button class="btn btn-block" type="submit">Login</button>
        </form>
        <p class="login-note">Authorized admin and pharmacy staff only.</p>
    </aside>
</main>

<footer class="footer">
    <p>&copy; <?= date('Y') ?> CK Pharmacy. All rights reserved.</p>
</footer>

</body>
</html>
