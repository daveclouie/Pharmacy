<?php
// Database connection + helpers. Edit the credentials if yours differ.
// XAMPP defaults: user "root", empty password.
define('DB_HOST', 'localhost');
define('DB_NAME', 'ck_pharmacy');
define('DB_USER', 'root');
define('DB_PASS', '');

define('EXPIRY_WARNING_DAYS', 60); // products expiring within this many days are "near expiry"

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    die('Database connection failed. Is MySQL running in XAMPP and did you import sql/database.sql? <br><small>'
        . htmlspecialchars($e->getMessage()) . '</small>');
}

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function peso($amount)
{
    return '₱' . number_format((float)$amount, 2);
}

function flash($msg = null, $type = 'success')
{
    if ($msg !== null) {
        $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
        return;
    }
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return '<div class="alert alert-' . e($f['type']) . '">' . e($f['msg']) . '</div>';
    }
    return '';
}

function csrf_token()
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_field()
{
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

function csrf_check()
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (empty($_POST['csrf']) || !hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'])) {
            die('Invalid form submission. Go back and try again.');
        }
    }
}
