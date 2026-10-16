<?php
require_once 'includes/auth.php';
require_login();

$days = EXPIRY_WARNING_DAYS;
$totalProducts = $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
$lowStock = $pdo->query('SELECT COUNT(*) FROM products WHERE quantity <= min_stock')->fetchColumn();
$expired = $pdo->query('SELECT COUNT(*) FROM products WHERE expiry_date IS NOT NULL AND expiry_date < CURDATE()')->fetchColumn();
$near = $pdo->query("SELECT COUNT(*) FROM products WHERE expiry_date >= CURDATE() AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL $days DAY)")->fetchColumn();
$todaySales = $pdo->query('SELECT COALESCE(SUM(total),0) FROM sales WHERE DATE(created_at) = CURDATE()')->fetchColumn();

$lowList = $pdo->query('SELECT name, quantity, min_stock FROM products WHERE quantity <= min_stock ORDER BY quantity ASC LIMIT 8')->fetchAll();

$pageTitle = 'Dashboard';
require 'includes/header.php';
?>
<h2>Dashboard</h2>
<div class="stats">
    <div class="stat"><div class="num"><?= $totalProducts ?></div><div class="label">Total products</div></div>
    <div class="stat"><div class="num"><?= peso($todaySales) ?></div><div class="label">Sales today</div></div>
    <div class="stat warning"><div class="num"><?= $lowStock ?></div><div class="label">Low-stock products</div></div>
    <div class="stat warning"><div class="num"><?= $near ?></div><div class="label">Near expiry (<?= $days ?> days)</div></div>
    <div class="stat danger"><div class="num"><?= $expired ?></div><div class="label">Expired products</div></div>
</div>

<h3>Low Stock Alerts</h3>
<?php if (!$lowList): ?>
    <p>All products are sufficiently stocked.</p>
<?php else: ?>
<div class="table-wrap"><table>
    <tr><th>Product</th><th>Quantity</th><th>Minimum</th></tr>
    <?php foreach ($lowList as $p): ?>
        <tr><td><?= e($p['name']) ?></td>
            <td><span class="badge <?= $p['quantity'] == 0 ? 'badge-out' : 'badge-low' ?>"><?= $p['quantity'] ?></span></td>
            <td><?= $p['min_stock'] ?></td></tr>
    <?php endforeach; ?>
</table></div>
<?php endif; ?>

<?php require 'includes/footer.php'; ?>
