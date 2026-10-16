<?php
require_once 'includes/auth.php';
require_login();

// ---- Stock in / stock out / adjust ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $pid = (int)$_POST['product_id'];
    $qty = (int)$_POST['qty'];
    $type = $_POST['type'] ?? 'add';
    $note = trim($_POST['note'] ?? '');

    if ($pid <= 0 || $qty <= 0) {
        flash('Choose a product and enter a quantity above zero.', 'error');
    } else {
        $s = $pdo->prepare('SELECT quantity FROM products WHERE id = ?');
        $s->execute([$pid]);
        $current = $s->fetchColumn();

        if ($current === false) {
            flash('Product not found.', 'error');
        } elseif ($type === 'remove' && $qty > $current) {
            flash('Cannot remove more than the current stock (' . $current . ').', 'error');
        } else {
            $delta = $type === 'add' ? $qty : -$qty;
            $pdo->prepare('UPDATE products SET quantity = quantity + ? WHERE id = ?')->execute([$delta, $pid]);

            // Optionally update expiry when receiving a new batch
            if ($type === 'add' && !empty($_POST['expiry_date'])) {
                $pdo->prepare('UPDATE products SET expiry_date = ? WHERE id = ?')->execute([$_POST['expiry_date'], $pid]);
            }
            $pdo->prepare('INSERT INTO stock_log (product_id, change_qty, type, note, user_id) VALUES (?,?,?,?,?)')
                ->execute([$pid, $delta, $type, $note, current_user_id()]);
            flash($type === 'add' ? 'Stock added.' : 'Stock removed.');
        }
    }
    header('Location: inventory.php');
    exit;
}

$products = $pdo->query('SELECT p.*, s.name AS supplier FROM products p LEFT JOIN suppliers s ON s.id = p.supplier_id ORDER BY p.name')->fetchAll();
$filter = $_GET['filter'] ?? 'all';
$shown = $filter === 'low' ? array_filter($products, fn($p) => $p['quantity'] <= $p['min_stock']) : $products;

$log = $pdo->query('SELECT l.*, p.name, u.full_name FROM stock_log l
                    JOIN products p ON p.id = l.product_id LEFT JOIN users u ON u.id = l.user_id
                    ORDER BY l.id DESC LIMIT 20')->fetchAll();

$pageTitle = 'Inventory';
require 'includes/header.php';
?>
<h2>Inventory Tracking</h2>

<form method="post" class="form-box">
    <?= csrf_field() ?>
    <h3 style="margin-top:0">Update Stock</h3>
    <div class="form-grid">
        <div><label>Product</label>
            <select name="product_id" required>
                <option value="">Select product</option>
                <?php foreach ($products as $p): ?>
                    <option value="<?= $p['id'] ?>"><?= e($p['name']) ?> (stock: <?= $p['quantity'] ?>)</option>
                <?php endforeach; ?>
            </select></div>
        <div><label>Action</label>
            <select name="type">
                <option value="add">Add stock (from supplier)</option>
                <option value="remove">Remove stock (damaged / returned)</option>
            </select></div>
        <div><label>Quantity</label><input type="number" name="qty" min="1" required></div>
        <div><label>New expiry date (optional)</label><input type="date" name="expiry_date"></div>
        <div><label>Note</label><input type="text" name="note" placeholder="e.g. Delivery from supplier"></div>
    </div>
    <button class="btn" type="submit">Update Stock</button>
</form>

<div class="search-bar">
    <a href="inventory.php" class="btn <?= $filter === 'all' ? '' : 'btn-light' ?>">All products</a>
    <a href="inventory.php?filter=low" class="btn <?= $filter === 'low' ? '' : 'btn-light' ?>">Low stock only</a>
</div>

<div class="table-wrap"><table>
    <tr><th>Product</th><th>Supplier</th><th>Quantity</th><th>Minimum</th><th>Status</th></tr>
    <?php foreach ($shown as $p): ?>
        <?php
        if ($p['quantity'] == 0) { $cls = 'badge-out'; $txt = 'Out of stock'; }
        elseif ($p['quantity'] <= $p['min_stock']) { $cls = 'badge-low'; $txt = 'Low stock'; }
        else { $cls = 'badge-ok'; $txt = 'In stock'; }
        ?>
        <tr><td><?= e($p['name']) ?></td><td><?= e($p['supplier'] ?? '—') ?></td><td><?= $p['quantity'] ?></td>
            <td><?= $p['min_stock'] ?></td><td><span class="badge <?= $cls ?>"><?= $txt ?></span></td></tr>
    <?php endforeach; ?>
    <?php if (!$shown): ?><tr><td colspan="5">Nothing to show.</td></tr><?php endif; ?>
</table></div>

<h3>Recent Stock Movements</h3>
<div class="table-wrap"><table>
    <tr><th>Date</th><th>Product</th><th>Change</th><th>Type</th><th>Note</th><th>By</th></tr>
    <?php foreach ($log as $l): ?>
        <tr><td><?= e($l['created_at']) ?></td><td><?= e($l['name']) ?></td>
            <td><?= $l['change_qty'] > 0 ? '+' : '' ?><?= $l['change_qty'] ?></td>
            <td><?= e($l['type']) ?></td><td><?= e($l['note']) ?></td><td><?= e($l['full_name']) ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$log): ?><tr><td colspan="6">No movements yet.</td></tr><?php endif; ?>
</table></div>
<?php require 'includes/footer.php'; ?>
