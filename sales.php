<?php
require_once 'includes/auth.php';
require_login();

// ---- Record a sale ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $ids = $_POST['product_id'] ?? [];
    $qtys = $_POST['qty'] ?? [];

    // Merge duplicate products
    $items = [];
    foreach ($ids as $i => $pid) {
        $pid = (int)$pid;
        $q = (int)($qtys[$i] ?? 0);
        if ($pid > 0 && $q > 0) {
            $items[$pid] = ($items[$pid] ?? 0) + $q;
        }
    }

    if (!$items) {
        flash('Add at least one product to the sale.', 'error');
        header('Location: sales.php');
        exit;
    }

    try {
        $pdo->beginTransaction();
        $total = 0;
        $lines = [];
        foreach ($items as $pid => $q) {
            $s = $pdo->prepare('SELECT * FROM products WHERE id = ? FOR UPDATE');
            $s->execute([$pid]);
            $p = $s->fetch();
            if (!$p) throw new Exception('Product not found.');
            if ($p['expiry_date'] && $p['expiry_date'] < date('Y-m-d')) {
                throw new Exception($p['name'] . ' is expired and cannot be sold.');
            }
            if ($p['quantity'] < $q) {
                throw new Exception('Not enough stock for ' . $p['name'] . ' (available: ' . $p['quantity'] . ').');
            }
            $lines[] = [$p, $q];
            $total += $p['price'] * $q;
        }

        $pdo->prepare('INSERT INTO sales (user_id, total) VALUES (?, ?)')->execute([current_user_id(), $total]);
        $saleId = $pdo->lastInsertId();

        foreach ($lines as [$p, $q]) {
            $pdo->prepare('INSERT INTO sale_items (sale_id, product_id, qty, price) VALUES (?,?,?,?)')
                ->execute([$saleId, $p['id'], $q, $p['price']]);
            // Auto-deduct inventory
            $pdo->prepare('UPDATE products SET quantity = quantity - ? WHERE id = ?')->execute([$q, $p['id']]);
            $pdo->prepare("INSERT INTO stock_log (product_id, change_qty, type, note, user_id) VALUES (?,?, 'sale', ?, ?)")
                ->execute([$p['id'], -$q, 'Sale #' . $saleId, current_user_id()]);
        }
        $pdo->commit();
        flash('Sale #' . $saleId . ' recorded. Total: ' . peso($total));
        header('Location: sales.php?receipt=' . $saleId);
    } catch (Exception $ex) {
        $pdo->rollBack();
        flash($ex->getMessage(), 'error');
        header('Location: sales.php');
    }
    exit;
}

// Products available for sale
$products = $pdo->query('SELECT id, name, price, quantity FROM products
                         WHERE quantity > 0 AND (expiry_date IS NULL OR expiry_date >= CURDATE())
                         ORDER BY name')->fetchAll();

// Receipt view
$receipt = null;
if (!empty($_GET['receipt'])) {
    $s = $pdo->prepare('SELECT s.*, u.full_name FROM sales s LEFT JOIN users u ON u.id = s.user_id WHERE s.id = ?');
    $s->execute([(int)$_GET['receipt']]);
    $receipt = $s->fetch();
    if ($receipt) {
        $s = $pdo->prepare('SELECT si.*, p.name FROM sale_items si JOIN products p ON p.id = si.product_id WHERE si.sale_id = ?');
        $s->execute([$receipt['id']]);
        $receiptItems = $s->fetchAll();
    }
}

$recent = $pdo->query('SELECT s.id, s.total, s.created_at, u.full_name FROM sales s LEFT JOIN users u ON u.id = s.user_id
                       ORDER BY s.id DESC LIMIT 15')->fetchAll();

$pageTitle = 'Sales';
require 'includes/header.php';
?>
<h2>Sales Management</h2>

<?php if ($receipt): ?>
<div class="form-box">
    <h3 style="margin-top:0">Receipt — Sale #<?= $receipt['id'] ?></h3>
    <p>Date: <?= e($receipt['created_at']) ?> &nbsp;|&nbsp; Cashier: <?= e($receipt['full_name']) ?></p>
    <table>
        <tr><th>Item</th><th>Qty</th><th>Price</th><th class="right">Subtotal</th></tr>
        <?php foreach ($receiptItems as $it): ?>
            <tr><td><?= e($it['name']) ?></td><td><?= $it['qty'] ?></td><td><?= peso($it['price']) ?></td>
                <td class="right"><?= peso($it['qty'] * $it['price']) ?></td></tr>
        <?php endforeach; ?>
    </table>
    <div class="total-line">Total: <?= peso($receipt['total']) ?></div>
</div>
<?php endif; ?>

<div class="pos">
    <form method="post" class="form-box" id="saleForm">
        <?= csrf_field() ?>
        <h3 style="margin-top:0">New Sale</h3>
        <div id="lines"></div>
        <p><button type="button" class="btn btn-light" id="addLine">+ Add item</button></p>
        <div class="total-line">Total: ₱<span id="grandTotal">0.00</span></div>
        <button class="btn" type="submit">Complete Sale</button>
    </form>

    <div>
        <h3 style="margin-top:0">Recent Sales</h3>
        <div class="table-wrap"><table>
            <tr><th>#</th><th>Date</th><th>Cashier</th><th class="right">Total</th><th></th></tr>
            <?php foreach ($recent as $r): ?>
                <tr><td><?= $r['id'] ?></td><td><?= e($r['created_at']) ?></td><td><?= e($r['full_name']) ?></td>
                    <td class="right"><?= peso($r['total']) ?></td>
                    <td><a class="btn btn-small btn-light" href="sales.php?receipt=<?= $r['id'] ?>">View</a></td></tr>
            <?php endforeach; ?>
            <?php if (!$recent): ?><tr><td colspan="5">No sales yet.</td></tr><?php endif; ?>
        </table></div>
    </div>
</div>

<script>
const products = <?= json_encode($products) ?>;
const lines = document.getElementById('lines');

function addLine() {
    const row = document.createElement('div');
    row.className = 'form-grid';
    row.style.gridTemplateColumns = '2fr 1fr auto';
    row.innerHTML =
        '<select name="product_id[]" required><option value="">Select product</option>' +
        products.map(p => `<option value="${p.id}" data-price="${p.price}" data-max="${p.quantity}">` +
            `${p.name.replace(/</g, '&lt;')} — ₱${parseFloat(p.price).toFixed(2)} (stock: ${p.quantity})</option>`).join('') +
        '</select>' +
        '<input type="number" name="qty[]" min="1" value="1" required>' +
        '<button type="button" class="btn btn-small btn-danger">✕</button>';
    row.querySelector('button').onclick = () => { row.remove(); recalc(); };
    row.querySelector('select').onchange = e => {
        const opt = e.target.selectedOptions[0];
        row.querySelector('input').max = opt.dataset.max || '';
        recalc();
    };
    row.querySelector('input').oninput = recalc;
    lines.appendChild(row);
}

function recalc() {
    let total = 0;
    lines.querySelectorAll('.form-grid').forEach(row => {
        const opt = row.querySelector('select').selectedOptions[0];
        const qty = parseInt(row.querySelector('input').value) || 0;
        if (opt && opt.dataset.price) total += parseFloat(opt.dataset.price) * qty;
    });
    document.getElementById('grandTotal').textContent = total.toFixed(2);
}

document.getElementById('addLine').onclick = addLine;
addLine();
</script>
<?php require 'includes/footer.php'; ?>
