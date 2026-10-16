<?php
require_once 'includes/auth.php';
require_admin();

$tables = ['users', 'suppliers', 'products', 'stock_log', 'sales', 'sale_items'];

// ---- Download backup ----
if (isset($_GET['download'])) {
    $out = "-- CK Pharmacy backup " . date('Y-m-d H:i:s') . "\n";
    $out .= "SET FOREIGN_KEY_CHECKS=0;\n";
    foreach (array_reverse($tables) as $t) {
        $out .= "DELETE FROM `$t`;\n";
    }
    foreach ($tables as $t) {
        foreach ($pdo->query("SELECT * FROM `$t`") as $row) {
            $cols = '`' . implode('`,`', array_keys($row)) . '`';
            $vals = array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote($v), array_values($row));
            $out .= "INSERT INTO `$t` ($cols) VALUES (" . implode(',', $vals) . ");\n";
        }
    }
    $out .= "SET FOREIGN_KEY_CHECKS=1;\n";

    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="ck_pharmacy_backup_' . date('Ymd_His') . '.sql"');
    echo $out;
    exit;
}

// ---- Restore backup ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (!isset($_FILES['backup']) || $_FILES['backup']['error'] !== UPLOAD_ERR_OK) {
        flash('Please choose a backup .sql file.', 'error');
    } elseif (strtolower(pathinfo($_FILES['backup']['name'], PATHINFO_EXTENSION)) !== 'sql') {
        flash('Only .sql backup files made by this system are allowed.', 'error');
    } else {
        $sql = file_get_contents($_FILES['backup']['tmp_name']);
        // Only accept files that look like our own backups
        if (strpos($sql, '-- CK Pharmacy backup') !== 0) {
            flash('This is not a CK Pharmacy backup file.', 'error');
        } else {
            try {
                foreach (preg_split('/;\r?\n/', $sql) as $stmt) {
                    $stmt = trim($stmt);
                    if ($stmt === '' || strpos($stmt, '--') === 0 && strpos($stmt, "\n") === false) continue;
                    // strip the leading comment line from the first statement
                    $stmt = preg_replace('/^--[^\n]*\n/', '', $stmt);
                    if (trim($stmt) !== '') $pdo->exec($stmt);
                }
                flash('Backup restored. You may need to log in again.');
            } catch (PDOException $e) {
                flash('Restore failed: ' . $e->getMessage(), 'error');
            }
        }
    }
    header('Location: backup.php');
    exit;
}

$pageTitle = 'Backup';
require 'includes/header.php';
?>
<h2>Backup &amp; Restore</h2>

<div class="form-box">
    <h3 style="margin-top:0">Download Backup</h3>
    <p>Saves all products, suppliers, sales, stock history and users into one .sql file. Keep copies on a flash drive or cloud storage.</p>
    <a class="btn" href="backup.php?download=1">Download Backup Now</a>
</div>

<form method="post" enctype="multipart/form-data" class="form-box" onsubmit="return confirm('Restoring REPLACES all current data. Continue?')">
    <?= csrf_field() ?>
    <h3 style="margin-top:0">Restore Backup</h3>
    <p><strong>Warning:</strong> restoring replaces all current data with the contents of the file.</p>
    <p><input type="file" name="backup" accept=".sql" required></p>
    <button class="btn btn-danger" type="submit">Restore</button>
</form>
<?php require 'includes/footer.php'; ?>
