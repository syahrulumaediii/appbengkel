<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

$auth = new Auth();
$auth->requireAdmin(); // Hanya admin yang bisa akses

$message = '';
$error = '';

// Handle form submission
if ($_POST) {
    $action = $_POST['action'];

    if ($action == 'add') {
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);
        $nama = trim($_POST['nama']);
        $role = trim($_POST['role']);

        if (empty($username) || empty($password) || empty($nama) || empty($role)) {
            $error = "Semua field harus diisi";
        } else {
            // Cek apakah username sudah digunakan
            $check = $db->fetch("SELECT id_user FROM user WHERE username = ?", [$username]);
            if ($check) {
                $error = "Username sudah digunakan";
            } else {
                $sql = "INSERT INTO user (username, password, nama, role) VALUES (?, MD5(?), ?, ?)";
                if ($db->query($sql, [$username, $password, $nama, $role])) {
                    $message = "User berhasil ditambahkan";
                } else {
                    $error = "Gagal menambahkan user";
                }
            }
        }
    }

    if ($action == 'edit') {
        $id_user = $_POST['id_user'];
        $username = trim($_POST['username']);
        $nama = trim($_POST['nama']);
        $role = trim($_POST['role']);
        $password = trim($_POST['password']);

        if (empty($username) || empty($nama) || empty($role)) {
            $error = "Username, nama, dan role harus diisi";
        } else {
            // Cek apakah username digunakan oleh user lain
            $check = $db->fetch("SELECT id_user FROM user WHERE username = ? AND id_user != ?", [$username, $id_user]);
            if ($check) {
                $error = "Username sudah digunakan oleh user lain";
            } else {
                if (!empty($password)) {
                    $sql = "UPDATE user SET username = ?, password = MD5(?), nama = ?, role = ?, updated_at = NOW() WHERE id_user = ?";
                    $params = [$username, $password, $nama, $role, $id_user];
                } else {
                    $sql = "UPDATE user SET username = ?, nama = ?, role = ? WHERE id_user = ?";
                    $params = [$username, $nama, $role, $id_user];
                }

                if ($db->query($sql, $params)) {
                    $message = "User berhasil diupdate";
                } else {
                    $error = "Gagal mengupdate user";
                }
            }
        }
    }

    if ($action == 'delete') {
        $id_user = $_POST['id_user'];
        if ($id_user == $_SESSION['id_user']) {
            $error = "Tidak bisa menghapus user yang sedang login";
        } else {
            $sql = "DELETE FROM user WHERE id_user = ?";
            if ($db->query($sql, [$id_user])) {
                $message = "User berhasil dihapus";
            } else {
                $error = "Gagal menghapus user";
            }
        }
    }
}

// Ambil semua user
$users = $db->fetchAll("SELECT * FROM user ORDER BY nama");
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Bengkel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../css/style.css" rel="stylesheet">
</head>

<body class="bg-light">
    <div class="container-fluid py-4">
        <!-- Header Section -->
        <div class="header-section">
            <div class="header-content">
                <div class="container-fluid">
                    <div class="row align-items-center">
                        <div class="col-lg-8">
                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb breadcrumb-custom mb-0">
                                    <li class="breadcrumb-item">
                                        <a href="../dashboard.php">
                                            <i class="fas fa-home me-1"></i>Dashboard
                                        </a>
                                    </li>
                                    <li class="breadcrumb-item active">User Management</li>
                                </ol>
                            </nav>

                            <div class="header-title">
                                <div class="icon-bg">
                                    <i class="fas fa-users"></i>
                                </div>
                                User Management
                            </div>

                            <p class="header-subtitle">
                                Kelola pengguna sistem bengkel dengan mudah dan aman
                            </p>

                            <div class="header-stats">
                                <div class="stat-item">
                                    <span class="stat-number"><?= count($users) ?></span>
                                    <div class="stat-label">Total Users</div>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-number"><?= count(array_filter($users, function ($u) {
                                                                    return $u['role'] == 'admin';
                                                                })) ?></span>
                                    <div class="stat-label">Admin</div>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-number"><?= count(array_filter($users, function ($u) {
                                                                    return $u['role'] == 'staff';
                                                                })) ?></span>
                                    <div class="stat-label">Staff</div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-4 text-lg-end text-center mt-3 mt-lg-0">
                            <button class="btn btn-add-user" data-bs-toggle="modal" data-bs-target="#addModal">
                                <i class="fas fa-plus me-2"></i>Tambah User Baru
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show shadow-sm">
                <i class="fas fa-check-circle me-2"></i><?= $message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show shadow-sm">
                <i class="fas fa-exclamation-circle me-2"></i><?= $error ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- User Table -->
        <div class="card shadow-sm border-0" style="border-radius: 15px;">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th class="border-0">No</th>
                                <th class="border-0">Username</th>
                                <th class="border-0">Nama</th>
                                <th class="border-0">Role</th>
                                <th class="border-0">Created At</th>
                                <th class="border-0">Updated At</th>
                                <th class="border-0">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $index => $user): ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-sm bg-primary text-white rounded-circle me-2 d-flex align-items-center justify-content-center"
                                                style="width: 35px; height: 35px;">
                                                <?= strtoupper(substr($user['nama'], 0, 1)) ?>
                                            </div>
                                            <?= htmlspecialchars($user['username']) ?>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($user['nama']) ?></td>
                                    <td>
                                        <span
                                            class="badge bg-<?= $user['role'] == 'admin' ? 'primary' : 'success' ?> px-3 py-2"
                                            style="border-radius: 20px;">
                                            <i class="fas fa-<?= $user['role'] == 'admin' ? 'crown' : 'user' ?> me-1"></i>
                                            <?= ucfirst($user['role']) ?>
                                        </span>
                                    </td>
                                    <td><?= date('d/m/Y H:i', strtotime($user['created_at'])) ?></td>
                                    <td><?= date('d/m/Y H:i', strtotime($user['updated_at'])) ?></td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <button class="btn btn-warning btn-sm"
                                                onclick='editUser(<?php echo json_encode($user); ?>)'
                                                data-bs-toggle="tooltip" title="Edit User">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php if ($user['id_user'] != $_SESSION['user']): ?>
                                                <button class="btn btn-danger btn-sm"
                                                    onclick="deleteUser(<?= $user['id_user'] ?>, '<?= htmlspecialchars($user['nama']) ?>')"
                                                    data-bs-toggle="tooltip" title="Hapus User">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Add USER Modal -->
    <div class="modal fade" id="addModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-user-plus me-2"></i>Tambah User
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">

                        <div class="mb-3">
                            <label class="form-label">
                                <i class="fas fa-user me-1"></i>Username
                            </label>
                            <input type="text" class="form-control" name="username" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">
                                <i class="fas fa-lock me-1"></i>Password
                            </label>
                            <input type="password" class="form-control" name="password" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">
                                <i class="fas fa-id-card me-1"></i>Nama Lengkap
                            </label>
                            <input type="text" class="form-control" name="nama" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">
                                <i class="fas fa-user-tag me-1"></i>Role
                            </label>
                            <select class="form-select" name="role" required>
                                <option value="">Pilih Role</option>
                                <option value="admin">Admin</option>
                                <option value="staff">Staff</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i>Batal
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit USER Modal -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-user-edit me-2"></i>Edit User
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id_user" id="edit_id">

                        <div class="mb-3">
                            <label class="form-label">
                                <i class="fas fa-user me-1"></i>Username
                            </label>
                            <input type="text" class="form-control" name="username" id="edit_username" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">
                                <i class="fas fa-lock me-1"></i>Password
                                <small class="text-muted">(kosongkan jika tidak diubah)</small>
                            </label>
                            <input type="password" class="form-control" name="password" id="edit_password">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">
                                <i class="fas fa-id-card me-1"></i>Nama Lengkap
                            </label>
                            <input type="text" class="form-control" name="nama" id="edit_nama" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">
                                <i class="fas fa-user-tag me-1"></i>Role
                            </label>
                            <select class="form-select" name="role" id="edit_role" required>
                                <option value="admin">Admin</option>
                                <option value="staff">Staff</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i>Batal
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>Update
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Form -->
    <form method="POST" id="deleteForm" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id_user" id="delete_id">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        })

        function editUser(user) {
            document.getElementById('edit_id').value = user.id_user;
            document.getElementById('edit_username').value = user.username;
            document.getElementById('edit_nama').value = user.nama;
            document.getElementById('edit_role').value = user.role;
            document.getElementById('edit_password').value = '';

            new bootstrap.Modal(document.getElementById('editModal')).show();
        }

        function deleteUser(id, nama) {
            if (confirm('Yakin ingin menghapus user "' + nama + '"?')) {
                document.getElementById('delete_id').value = id;
                document.getElementById('deleteForm').submit();
            }
        }
    </script>
</body>

</html>