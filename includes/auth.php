<?php
// Include at the top of every protected page.
require_once __DIR__ . '/config.php';

function require_login()
{
    if (empty($_SESSION['user'])) {
        header('Location: index.php');
        exit;
    }
}

function require_admin()
{
    require_login();
    if ($_SESSION['user']['role'] !== 'admin') {
        flash('Access denied. Admin only.', 'error');
        header('Location: dashboard.php');
        exit;
    }
}

function is_admin()
{
    return !empty($_SESSION['user']) && $_SESSION['user']['role'] === 'admin';
}

function current_user_id()
{
    return $_SESSION['user']['id'] ?? null;
}
