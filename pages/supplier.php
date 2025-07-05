<?php
require_once '../includes/auth.php';
require_once '../config/database.php';

$auth = new Auth();
$auth->requireLogin();

$message = '';
$error = '';

// Handle form submission
if ($_POST) {
    $action = $_POST['action'];

    if ($action == 'add') {
        $kode_supplier = trim($_POST['kode_supplier']);
        $nama_supplier = trim($_POST['nama_supplier']);
        $alamat = trim($_POST['alamat']);
        $telepon = trim($_POST['telepon']);
        $email = trim($_POST['email']);
        $kontak_person = trim($_POST['kontak_person']);

        if (empty($kode_supplier) || empty($nama_supplier)) {
            $error = "Kode supplier dan nama supplier harus diisi";
        } else {
            // Cek kode sudah ada atau belum
            $check = $db->fetch("SELECT id_supplier FROM supplier WHERE kode_supplier = ?", [$kode_supplier]);
            if ($check) {
                $error = "Kode supplier sudah digunakan";
            } else {
                $sql = "INSERT INTO supplier (kode_supplier, nama_supplier, alamat, telepon, email, kontak_person) 
                        VALUES (?, ?, ?, ?, ?, ?)";
                if ($db->query($sql, [$kode_supplier, $nama_supplier, $alamat, $telepon, $email, $kontak_person])) {
                    $message = "Supplier berhasil ditambahkan";
                } else {
                    $error = "Gagal menambahkan supplier";
                }
            }
        }
    }

    if ($action == 'edit') {
        $id_supplier = $_POST['id_supplier'];
        $kode_supplier = trim($_POST['kode_supplier']);
        $nama_supplier = trim($_POST['nama_supplier']);
        $alamat = trim($_POST['alamat']);
        $telepon = trim($_POST['telepon']);
        $email = trim($_POST['email']);
        $kontak_person = trim($_POST['kontak_person']);

        if (empty($kode_supplier) || empty($nama_supplier)) {
            $error = "Kode supplier dan nama supplier harus diisi";
        } else {
            $check = $db->fetch("SELECT id_supplier FROM supplier WHERE kode_supplier = ? AND id_supplier != ?", [$kode_supplier, $id_supplier]);
            if ($check) {
                $error = "Kode supplier sudah digunakan oleh supplier lain";
            } else {
                $sql = "UPDATE supplier 
                        SET kode_supplier = ?, nama_supplier = ?, alamat = ?, telepon = ?, email = ?, kontak_person = ? 
                        WHERE id_supplier = ?";
                if ($db->query($sql, [$kode_supplier, $nama_supplier, $alamat, $telepon, $email, $kontak_person, $id_supplier])) {
                    $message = "Supplier berhasil diupdate";
                } else {
                    $error = "Gagal mengupdate supplier";
                }
            }
        }
    }

    if ($action == 'delete') {
        $id_supplier = $_POST['id_supplier'];
        // Cek apakah digunakan di barang_masuk
        $check = $db->fetch("SELECT id_barangmasuk FROM barang_masuk WHERE id_supplier = ? LIMIT 1", [$id_supplier]);
        if ($check) {
            $error = "Supplier tidak bisa dihapus karena sudah digunakan dalam transaksi barang masuk";
        } else {
            $sql = "DELETE FROM supplier WHERE id_supplier = ?";
            if ($db->query($sql, [$id_supplier])) {
                $message = "Supplier berhasil dihapus";
            } else {
                $error = "Gagal menghapus supplier";
            }
        }
    }
}

// Ambil semua data supplier
$suppliers = $db->fetchAll("SELECT * FROM supplier ORDER BY nama_supplier");

// Generate kode supplier baru
$last_supplier = $db->fetch("SELECT kode_supplier FROM supplier ORDER BY id_supplier DESC LIMIT 1");
$next_number = 1;
if ($last_supplier) {
    $last_number = (int)substr($last_supplier['kode_supplier'], 3); // hapus SUP
    $next_number = $last_number + 1;
}
$new_code = 'SUP' . str_pad($next_number, 3, '0', STR_PAD_LEFT);

// Hitung statistik
$total_suppliers = count($suppliers);
$suppliers_with_email = count(array_filter($suppliers, function ($s) {
    return !empty($s['email']);
}));
$suppliers_with_phone = count(array_filter($suppliers, function ($s) {
    return !empty($s['telepon']);
}));
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplier Management - Bengkel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../css/style.css" rel="stylesheet">
    <!-- <link href="../css/barang.css" rel="stylesheet"> -->
</head>

<body class=" bg-light">
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
                                    <li class="breadcrumb-item active">Supplier Management</li>
                                </ol>
                            </nav>

                            <div class="header-title">
                                <div class="icon-bg">
                                    <i class="fas fa-truck"></i>
                                </div>
                                Supplier Management
                            </div>

                            <p class="header-subtitle">
                                Kelola data supplier dan mitra bisnis bengkel dengan sistem terintegrasi
                            </p>

                            <div class="header-stats">
                                <div class="stat-item">
                                    <span class="stat-number"><?= $total_suppliers ?></span>
                                    <div class="stat-label">Total Supplier</div>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-number"><?= $suppliers_with_email ?></span>
                                    <div class="stat-label">Dengan Email</div>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-number"><?= $suppliers_with_phone ?></span>
                                    <div class="stat-label">Dengan Telepon</div>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-number"><?= $new_code ?></span>
                                    <div class="stat-label">Kode Berikutnya</div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-4 text-lg-end text-center mt-3 mt-lg-0">
                            <button class="btn btn-add-supplier" data-bs-toggle="modal" data-bs-target="#addModal">
                                <i class="fas fa-plus me-2"></i>Tambah Supplier Baru
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

        <!-- Supplier Table -->
        <div class="card supplier-card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th class="border-0">No</th>
                                <th class="border-0">Kode</th>
                                <th class="border-0">Nama Supplier</th>
                                <th class="border-0">Alamat</th>
                                <th class="border-0">Kontak</th>
                                <th class="border-0">Kontak Person</th>
                                <th class="border-0">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($suppliers as $index => $supplier): ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td>
                                        <span class="badge-supplier-code">
                                            <?= $supplier['kode_supplier'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-sm bg-success text-white rounded-circle me-2 d-flex align-items-center justify-content-center"
                                                style="width: 35px; height: 35px;">
                                                <?= strtoupper(substr($supplier['nama_supplier'], 0, 1)) ?>
                                            </div>
                                            <div>
                                                <strong><?= htmlspecialchars($supplier['nama_supplier']) ?></strong>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?= htmlspecialchars($supplier['alamat']) ?: '-' ?>
                                        </small>
                                    </td>
                                    <td>
                                        <div>
                                            <?php if (!empty($supplier['telepon'])): ?>
                                                <div class="contact-info">
                                                    <i class="fas fa-phone"></i>
                                                    <small><?= htmlspecialchars($supplier['telepon']) ?></small>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($supplier['email'])): ?>
                                                <div class="contact-info">
                                                    <i class="fas fa-envelope"></i>
                                                    <small><?= htmlspecialchars($supplier['email']) ?></small>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (empty($supplier['telepon']) && empty($supplier['email'])): ?>
                                                <small class="text-muted">-</small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($supplier['kontak_person']) ?: '-' ?>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <button class="btn btn-warning btn-sm"
                                                onclick="editSupplier(<?= htmlspecialchars(json_encode($supplier)) ?>)"
                                                data-bs-toggle="tooltip" title="Edit Supplier">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-danger btn-sm"
                                                onclick="deleteSupplier(<?= $supplier['id_supplier'] ?>, '<?= htmlspecialchars($supplier['nama_supplier']) ?>')"
                                                data-bs-toggle="tooltip" title="Hapus Supplier">
                                                <i class="fas fa-trash"></i>
                                            </button>
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

    <!-- Add Modal -->
    <div class="modal fade" id="addModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-truck-loading me-2"></i>Tambah Supplier
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-barcode me-1"></i>Kode Supplier
                                    </label>
                                    <input type="text" class="form-control" name="kode_supplier"
                                        value="<?= $new_code ?>" required readonly>
                                    <small class="form-text text-muted">Kode otomatis tergenerate</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-building me-1"></i>Nama Supplier
                                    </label>
                                    <input type="text" class="form-control" name="nama_supplier" required>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">
                                <i class="fas fa-map-marker-alt me-1"></i>Alamat
                            </label>
                            <textarea class="form-control" name="alamat" rows="3"
                                placeholder="Masukkan alamat lengkap..."></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-phone me-1"></i>Telepon
                                    </label>
                                    <input type="text" class="form-control" name="telepon"
                                        placeholder="Contoh: 021-1234567">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-envelope me-1"></i>Email
                                    </label>
                                    <input type="email" class="form-control" name="email"
                                        placeholder="supplier@example.com">
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">
                                <i class="fas fa-user-tie me-1"></i>Kontak Person
                            </label>
                            <input type="text" class="form-control" name="kontak_person"
                                placeholder="Nama person in charge">
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

    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-edit me-2"></i>Edit Supplier
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id_supplier" id="edit_id">

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-barcode me-1"></i>Kode Supplier
                                    </label>
                                    <input type="text" class="form-control" name="kode_supplier" id="edit_kode_supplier"
                                        required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-building me-1"></i>Nama Supplier
                                    </label>
                                    <input type="text" class="form-control" name="nama_supplier" id="edit_nama_supplier"
                                        required>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">
                                <i class="fas fa-map-marker-alt me-1"></i>Alamat
                            </label>
                            <textarea class="form-control" name="alamat" id="edit_alamat" rows="3"></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-phone me-1"></i>Telepon
                                    </label>
                                    <input type="text" class="form-control" name="telepon" id="edit_telepon">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-envelope me-1"></i>Email
                                    </label>
                                    <input type="email" class="form-control" name="email" id="edit_email">
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">
                                <i class="fas fa-user-tie me-1"></i>Kontak Person
                            </label>
                            <input type="text" class="form-control" name="kontak_person" id="edit_kontak_person">
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
        <input type="hidden" name="id_supplier" id="delete_id">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        })

        function editSupplier(supplier) {
            document.getElementById('edit_id').value = supplier.id_supplier;
            document.getElementById('edit_kode_supplier').value = supplier.kode_supplier;
            document.getElementById('edit_nama_supplier').value = supplier.nama_supplier;
            document.getElementById('edit_alamat').value = supplier.alamat || '';
            document.getElementById('edit_telepon').value = supplier.telepon || '';
            document.getElementById('edit_email').value = supplier.email || '';
            document.getElementById('edit_kontak_person').value = supplier.kontak_person || '';

            new bootstrap.Modal(document.getElementById('editModal')).show();
        }

        function deleteSupplier(id, nama) {
            if (confirm('Yakin ingin menghapus supplier "' + nama +
                    '"?\n\nPerhatian: Supplier yang sudah digunakan dalam transaksi tidak dapat dihapus.')) {
                document.getElementById('delete_id').value = id;
                document.getElementById('deleteForm').submit();
            }
        }
    </script>
</body>

</html>