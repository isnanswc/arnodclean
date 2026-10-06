<?php
// admin/users.php
session_start();
require_once '../db.php';
require_once 'includes/auth.php';
checkLogin();

// Only admin can access
if ($_SESSION['admin_role'] !== 'admin') {
    header("Location: dashboard.php");
    exit;
}

// Handle Add User
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'];

    try {
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
        $stmt->execute([$username, $email, $password, $role]);
        logActivity('Create User', "Menambah user baru: $email");
        $_SESSION['swal'] = ['title' => 'Sukses', 'text' => 'User berhasil ditambahkan', 'icon' => 'success'];
    } catch (PDOException $e) {
        $_SESSION['swal'] = ['title' => 'Gagal', 'text' => 'Email mungkin sudah terdaftar.', 'icon' => 'error'];
    }
    header("Location: users.php");
    exit;
}

// Handle Edit User
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'edit') {
    $id = $_POST['user_id'];
    $username = trim($_POST['username']);
    $role = $_POST['role'];
    
    // Check if password provided
    if (!empty($_POST['password'])) {
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET username = ?, role = ?, password = ? WHERE id = ?");
        $stmt->execute([$username, $role, $password, $id]);
    } else {
        $stmt = $pdo->prepare("UPDATE users SET username = ?, role = ? WHERE id = ?");
        $stmt->execute([$username, $role, $id]);
    }

    logActivity('Update User', "Mengubah data user ID: $id ($username)");
    $_SESSION['swal'] = ['title' => 'Berhasil', 'text' => 'Data user diperbarui.', 'icon' => 'success'];
    
    // Update session if self-edit
    if ($id == $_SESSION['admin_id']) {
        $_SESSION['admin_username'] = $username;
        $_SESSION['admin_role'] = $role;
    }

    header("Location: users.php");
    exit;
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    if ($id != $_SESSION['admin_id']) { // Check self delete
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
        logActivity('Delete User', "Menghapus user ID: $id");
        $_SESSION['swal'] = ['title' => 'Terhapus', 'text' => 'User telah dihapus.', 'icon' => 'success'];
    } else {
        $_SESSION['swal'] = ['title' => 'Gagal', 'text' => 'Tidak bisa menghapus akun sendiri.', 'icon' => 'error'];
    }
    header("Location: users.php");
    exit;
}

$users = $pdo->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll();

require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Manajemen User</h1>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
        <i class="fa-solid fa-plus me-2"></i>Tambah User
    </button>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">User</th>
                        <th>Role</th>
                        <th>Login Terakhir</th>
                        <th class="text-end pe-4">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($users as $user): ?>
                    <tr>
                        <td class="ps-4">
                            <div class="d-flex align-items-center">
                                <?php if($user['avatar']): ?>
                                    <img src="<?php echo htmlspecialchars($user['avatar']); ?>" class="rounded-circle me-3" width="40" height="40">
                                <?php else: ?>
                                    <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center me-3" style="width:40px; height:40px;">
                                        <i class="fa-solid fa-user"></i>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($user['username']); ?></div>
                                    <div class="text-muted small"><?php echo htmlspecialchars($user['email']); ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <?php if($user['role'] == 'admin'): ?>
                                <span class="badge bg-primary">Admin</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Editor</span>
                            <?php endif; ?>
                            <?php if($user['google_id']): ?>
                                <span class="badge bg-danger ms-1"><i class="fa-brands fa-google"></i></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted small">
                            <?php 
                                // Get last login
                                $stmt = $pdo->prepare("SELECT login_at FROM login_history WHERE user_id = ? ORDER BY login_at DESC LIMIT 1");
                                $stmt->execute([$user['id']]);
                                $last = $stmt->fetchColumn();
                                echo $last ? date('d M Y H:i', strtotime($last)) : '-';
                            ?>
                        </td>
                        <td class="text-end pe-4">
                            <button class="btn btn-sm btn-outline-primary me-1 btn-edit" 
                                data-id="<?php echo $user['id']; ?>"
                                data-username="<?php echo htmlspecialchars($user['username']); ?>"
                                data-email="<?php echo htmlspecialchars($user['email']); ?>"
                                data-role="<?php echo $user['role']; ?>"
                                data-bs-toggle="modal" data-bs-target="#editUserModal">
                                <i class="fa-solid fa-pen-to-square"></i>
                            </button>
                            <?php if($user['id'] != $_SESSION['admin_id']): ?>
                            <a href="users.php?delete=<?php echo $user['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Hapus user ini?')"><i class="fa-solid fa-trash"></i></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Add -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="action" value="add">
            <div class="modal-header">
                <h5 class="modal-title">Tambah User Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <input type="text" class="form-control" name="username" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-control" name="email" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" class="form-control" name="password" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Role</label>
                    <select class="form-select" name="role">
                        <option value="editor">Editor</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="user_id" id="edit_user_id">
            <div class="modal-header">
                <h5 class="modal-title">Edit User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-control bg-light" id="edit_email" readonly>
                    <small class="text-muted">Email tidak dapat diubah.</small>
                </div>
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <input type="text" class="form-control" name="username" id="edit_username" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Password Baru <small class="text-muted">(Kosongkan jika tidak ubah)</small></label>
                    <input type="password" class="form-control" name="password" placeholder="***">
                </div>
                <div class="mb-3">
                    <label class="form-label">Role</label>
                    <select class="form-select" name="role" id="edit_role">
                        <option value="editor">Editor</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Handle Edit Button Click
    document.addEventListener('DOMContentLoaded', function() {
        const editButtons = document.querySelectorAll('.btn-edit');
        editButtons.forEach(btn => {
            btn.addEventListener('click', function() {
                document.getElementById('edit_user_id').value = this.dataset.id;
                document.getElementById('edit_username').value = this.dataset.username;
                document.getElementById('edit_email').value = this.dataset.email;
                document.getElementById('edit_role').value = this.dataset.role;
            });
        });
    });
</script>

<?php 
if (isset($_SESSION['swal'])) {
    $s = $_SESSION['swal'];
    echo "<script>Swal.fire('{$s['title']}', '{$s['text']}', '{$s['icon']}');</script>";
    unset($_SESSION['swal']);
}
require_once 'includes/footer.php'; 
?>
