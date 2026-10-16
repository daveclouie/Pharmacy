<?php
require_once 'includes/auth.php';
require_login();

// ---- Handle POST actions ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $data = [
            trim($_POST['name']),
            trim($_POST['category']),
            max(0, (float)$_POST['price']),
            max(0, (int)$_POST['quantity']),
            max(0, (int)$_POST['min_stock']),
            $_POST['expiry_date'] !== '' ? $_POST['expiry_date'] : null,
            $_POST['supplier_id'] !== '' ? (int)$_POST['supplier_id'] : null,
        ];
        if ($data[0] === '') {
            flash('Product name is required.', 'error');
        } elseif ($id) {
            $pdo->prepare('UPDATE products SET name=?, category=?, price=?, quantity=?, min_stock=?, expiry_date=?, supplier_id=? WHERE id=?')
                ->execute([...$data, $id]);
            flash('Product updated.');
        } else {
            $pdo->prepare('INSERT INTO products (name, category, price, quantity, min_stock, expiry_date, supplier_id) VALUES (?,?,?,?,?,?,?)')
                ->execute($data);
            $newId = $pdo->lastInsertId();
            if ($data[3] > 0) {
                $pdo->prepare("INSERT INTO stock_log (product_id, change_qty, type, note, user_id) VALUES (?,?, 'add', 'Initial stock', ?)")
                    ->execute([$newId, $data[3], current_user_id()]);
            }
            flash('Product added.');
        }
    }

    if ($action === 'delete' && is_admin()) {
        try {
            $pdo->prepare('DELETE FROM products WHERE id = ?')->execute([(int)$_POST['id']]);
            flash('Product deleted.');
        } catch (PDOException $e) {
            flash('Cannot delete: this product already has sales records.', 'error');
        }
    }
    header('Location: products.php');
    exit;
}

// ---- Load data ----
$search = trim($_GET['q'] ?? '');
$edit = null;
if (!empty($_GET['edit'])) {
    $s = $pdo->prepare('SELECT * FROM products WHERE id = ?');
    $s->execute([(int)$_GET['edit']]);
    $edit = $s->fetch();
}

$sql = 'SELECT p.*, s.name AS supplier FROM products p LEFT JOIN suppliers s ON s.id = p.supplier_id';
$params = [];
if ($search !== '') {
    $sql .= ' WHERE p.name LIKE ? OR p.category LIKE ?';
    $params = ["%$search%", "%$search%"];
}
$sql .= ' ORDER BY p.name';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();
$suppliers = $pdo->query('SELECT id, name FROM suppliers ORDER BY name')->fetchAll();

$pageTitle = 'Products';
require 'includes/header.php';
?>
<h2>Product Management</h2>

<form method="post" class="form-box">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= $edit['id'] ?? 0 ?>">
    <h3 style="margin-top:0"><?= $edit ? 'Edit Product' : 'Add Product' ?></h3>
    <div class="form-grid">
        <div><label>Name</label><input type="text" name="name" required value="<?= e($edit['name'] ?? '') ?>"></div>
        <div><label>Category</label>
            <select name="category">
                <?php foreach (['Medicine', 'Medical Supply', 'Vitamins', 'Personal Care', 'Other'] as $c): ?>
                    <option <?= ($edit['category'] ?? '') === $c ? 'selected' : '' ?>><?= $c ?></option>
                <?php endforeach; ?>
            </select></div>
        <div><label>Price (₱)</label><input type="number" step="0.01" min="0" name="price" required value="<?= e($edit['price'] ?? '') ?>"></div>
        <div><label>Quantity</label><input type="number" min="0" name="quantity" required value="<?= e($edit['quantity'] ?? 0) ?>"></div>
        <div><label>Minimum stock</label><input type="number" min="0" name="min_stock" required value="<?= e($edit['min_stock'] ?? 10) ?>"></div>
        <div><label>Expiration date</label><input type="date" name="expiry_date" value="<?= e($edit['expiry_date'] ?? '') ?>"></div>
        <div><label>Supplier</label>
            <select name="supplier_id">
                <option value="">— None —</option>
                <?php foreach ($suppliers as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= ($edit['supplier_id'] ?? '') == $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
                <?php endforeach; ?>
            </select></div>
    </div>
    <button class="btn" type="submit"><?= $edit ? 'Update Product' : 'Add Product' ?></button>
    <?php if ($edit): ?><a href="products.php" class="btn btn-light">Cancel</a><?php endif; ?>
</form>

<form method="get" class="search-bar">
    <input type="text" name="q" placeholder="Search by name or category" value="<?= e($search) ?>">
    <button class="btn" type="submit">Search</button>
    <?php if ($search): ?><a href="products.php" class="btn btn-light">Clear</a><?php endif; ?>
</form>

<div class="table-wrap"><table>
    <tr><th>Name</th><th>Category</th><th>Price</th><th>Qty</th><th>Min</th><th>Expiry</th><th>Supplier</th><th></th></tr>
    <?php foreach ($products as $p): ?>
        <tr>
            <td><?= e($p['name']) ?></td>
            <td><?= e($p['category']) ?></td>
            <td><?= peso($p['price']) ?></td>
            <td><span class="badge <?= $p['quantity'] == 0 ? 'badge-out' : ($p['quantity'] <= $p['min_stock'] ? 'badge-low' : 'badge-ok') ?>"><?= $p['quantity'] ?></span></td>
            <td><?= $p['min_stock'] ?></td>
            <td><?= e($p['expiry_date'] ?? '—') ?></td>
            <td><?= e($p['supplier'] ?? '—') ?></td>
            <td class="actions">
                <a class="btn btn-small btn-light" href="products.php?edit=<?= $p['id'] ?>">Edit</a>
                <?php if (is_admin()): ?>
                <form method="post" style="display:inline" onsubmit="return confirm('Delete this product?')">
                    <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $p['id'] ?>">
                    <button class="btn btn-small btn-danger" type="submit">Delete</button>
                </form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$products): ?><tr><td colspan="8">No products found.</td></tr><?php endif; ?>
</table></div>
<?php require 'includes/footer.php'; ?>
