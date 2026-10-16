<?php
require_once 'includes/auth.php';
require_login();

$days = EXPIRY_WARNING_DAYS;

$expired = $pdo->query('SELECT * FROM products WHERE expiry_date IS NOT NULL AND expiry_date < CURDATE() ORDER BY expiry_date')->fetchAll();
$near = $pdo->query("SELECT *, DATEDIFF(expiry_date, CURDATE()) AS days_left FROM products
                     WHERE expiry_date >= CURDATE() AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL $days DAY)
                     ORDER BY expiry_date")->fetchAll();

$pageTitle = 'Expiration';
require 'includes/header.php';
?>
<h2>Expiration Monitoring</h2>

<h3>Expired Products (<?= count($expired) ?>)</h3>
<p>These products are blocked from sales. Remove them from stock using <a href="inventory.php">Inventory</a>.</p>
<div class="table-wrap"><table>
    <tr><th>Product</th><th>Quantity</th><th>Expired on</th></tr>
    <?php foreach ($expired as $p): ?>
        <tr><td><?= e($p['name']) ?></td><td><?= $p['quantity'] ?></td>
            <td><span class="badge badge-expired"><?= e($p['expiry_date']) ?></span></td></tr>
    <?php endforeach; ?>
    <?php if (!$expired): ?><tr><td colspan="3">No expired products.</td></tr><?php endif; ?>
</table></div>

<h3>Expiring Within <?= $days ?> Days (<?= count($near) ?>)</h3>
<div class="table-wrap"><table>
    <tr><th>Product</th><th>Quantity</th><th>Expiry date</th><th>Days left</th></tr>
    <?php foreach ($near as $p): ?>
        <tr><td><?= e($p['name']) ?></td><td><?= $p['quantity'] ?></td><td><?= e($p['expiry_date']) ?></td>
            <td><span class="badge badge-near"><?= $p['days_left'] ?> days</span></td></tr>
    <?php endforeach; ?>
    <?php if (!$near): ?><tr><td colspan="4">No products near expiration.</td></tr><?php endif; ?>
</table></div>
<?php require 'includes/footer.php'; ?>
