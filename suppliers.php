<?php
require_once 'includes/auth.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $data = [trim($_POST['name']), trim($_POST['contact_person']), trim($_POST['phone']), trim($_POST['address'])];
        if ($data[0] === '') {
            flash('Supplier name is required.', 'error');
        } elseif ($id) {
            $pdo->prepare('UPDATE suppliers SET name=?, contact_person=?, phone=?, address=? WHERE id=?')->execute([...$data, $id]);
            flash('Supplier updated.');
        } else {
            $pdo->prepare('INSERT INTO suppliers (name, contact_person, phone, address) VALUES (?,?,?,?)')->execute($data);
            flash('Supplier added.');
        }
    }
    if ($action === 'delete' && is_admin()) {
        $pdo->prepare('DELETE FROM suppliers WHERE id = ?')->execute([(int)$_POST['id']]);
        flash('Supplier deleted.');
    }
    header('Location: suppliers.php');
    exit;
}

$edit = null;
if (!empty($_GET['edit'])) {
    $s = $pdo->prepare('SELECT * FROM suppliers WHERE id = ?');
    $s->execute([(int)$_GET['edit']]);
    $edit = $s->fetch();
}

// Number of products + units supplied by each supplier
$suppliers = $pdo->query('SELECT s.*, COUNT(p.id) AS product_count, COALESCE(SUM(p.quantity),0) AS units
                          FROM suppliers s LEFT JOIN products p ON p.supplier_id = s.id
                          GROUP BY s.id ORDER BY s.name')->fetchAll();

$pageTitle = 'Suppliers';
require 'includes/header.php';
?>
<h2>Supplier Management</h2>

<form method="post" class="form-box">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= $edit['id'] ?? 0 ?>">
    <h3 style="margin-top:0"><?= $edit ? 'Edit Supplier' : 'Add Supplier' ?></h3>
    <div class="form-grid">
        <div><label>Supplier name</label><input type="text" name="name" required value="<?= e($edit['name'] ?? '') ?>"></div>
        <div><label>Contact person</label><input type="text" name="contact_person" value="<?= e($edit['contact_person'] ?? '') ?>"></div>
        <div><label>Phone</label><input type="text" name="phone" value="<?= e($edit['phone'] ?? '') ?>"></div>
        <div><label>Address</label><input type="text" name="address" value="<?= e($edit['address'] ?? '') ?>"></div>
    </div>
    <button class="btn" type="submit"><?= $edit ? 'Update Supplier' : 'Add Supplier' ?></button>
    <?php if ($edit): ?><a href="suppliers.php" class="btn btn-light">Cancel</a><?php endif; ?>
</form>

<div class="table-wrap"><table>
    <tr><th>Supplier</th><th>Contact</th><th>Phone</th><th>Address</th><th>Products supplied</th><th>Units in stock</th><th></th></tr>
    <?php foreach ($suppliers as $s): ?>
        <tr>
            <td><?= e($s['name']) ?></td><td><?= e($s['contact_person']) ?></td><td><?= e($s['phone']) ?></td>
            <td><?= e($s['address']) ?></td><td><?= $s['product_count'] ?></td><td><?= $s['units'] ?></td>
            <td class="actions">
                <a class="btn btn-small btn-light" href="suppliers.php?edit=<?= $s['id'] ?>">Edit</a>
                <?php if (is_admin()): ?>
                <form method="post" style="display:inline" onsubmit="return confirm('Delete this supplier?')">
                    <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $s['id'] ?>">
                    <button class="btn btn-small btn-danger" type="submit">Delete</button>
                </form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$suppliers): ?><tr><td colspan="7">No suppliers yet.</td></tr><?php endif; ?>
</table></div>
<?php require 'includes/footer.php'; ?>
