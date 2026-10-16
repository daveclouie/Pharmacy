<?php
require_once 'includes/auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $username = trim($_POST['username']);
        $full = trim($_POST['full_name']);
        $role = $_POST['role'] === 'admin' ? 'admin' : 'staff';
        if ($username === '' || $full === '' || strlen($_POST['password']) < 6) {
            flash('Fill in all fields. Password must be at least 6 characters.', 'error');
        } else {
            try {
                $pdo->prepare('INSERT INTO users (username, password, full_name, role) VALUES (?,?,?,?)')
                    ->execute([$username, password_hash($_POST['password'], PASSWORD_DEFAULT), $full, $role]);
                flash('User created.');
            } catch (PDOException $e) {
                flash('That username already exists.', 'error');
            }
        }
    }

    header('Location: users.php');
    exit;
}

$users = $pdo->query('SELECT id, username, full_name, role, created_at FROM users ORDER BY role, username')->fetchAll();

$pageTitle = 'Users';
require 'includes/header.php';
?>
<h2>User Roles</h2>
<p><strong>Admin</strong>: full access (users, backup, deleting records). <strong>Staff</strong>: products, sales, inventory, reports.</p>

<form method="post" class="form-box">
    <?= csrf_field() ?><input type="hidden" name="action" value="add">
    <h3 style="margin-top:0">Add User</h3>
    <div class="form-grid">
        <div><label>Full name</label><input type="text" name="full_name" required></div>
        <div><label>Username</label><input type="text" name="username" required></div>
        <div><label>Password (min 6)</label><input type="password" name="password" required></div>
        <div><label>Role</label><select name="role"><option value="staff">Staff</option><option value="admin">Admin</option></select></div>
    </div>
    <button class="btn" type="submit">Create User</button>
</form>

<div class="table-wrap"><table>
    <tr><th>Name</th><th>Username</th><th>Role</th><th>Created</th></tr>
    <?php foreach ($users as $u): ?>
        <tr>
            <td><?= e($u['full_name']) ?></td><td><?= e($u['username']) ?></td><td><?= e($u['role']) ?></td><td><?= e($u['created_at']) ?></td>
        </tr>
    <?php endforeach; ?>
</table></div>
<?php require 'includes/footer.php'; ?>
