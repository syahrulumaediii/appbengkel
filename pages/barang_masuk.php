<?php
session_start();
require_once '../config/database.php';

$db = new Database();
$conn = $db->getConnection();

// Cek apakah user sudah login
if (!isset($_SESSION['user']['id_user'])) {
    header("Location: login.php");
    exit();
}

$id_user = $_SESSION['user']['id_user'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'add':
            // Generate kode masuk otomatis
            $stmt_kode = $conn->prepare("SELECT MAX(CAST(SUBSTRING(kode_masuk, 3) AS UNSIGNED)) as max_kode FROM barang_masuk WHERE kode_masuk LIKE 'BM%'");
            $stmt_kode->execute();
            $row_kode = $stmt_kode->fetch();
            $next_number = ($row_kode['max_kode'] ?? 0) + 1;
            $kode_masuk = 'BM' . str_pad($next_number, 4, '0', STR_PAD_LEFT);

            // Data utama barang masuk
            $tanggal_masuk = $_POST['tanggal_masuk'];
            $id_supplier = $_POST['id_supplier'];
            $keterangan = $_POST['keterangan'];

            // Insert barang_masuk
            $stmt_insert = $conn->prepare("INSERT INTO barang_masuk (kode_masuk, tanggal_masuk, id_supplier, id_user, keterangan) VALUES (?, ?, ?, ?, ?)");
            $stmt_insert->execute([$kode_masuk, $tanggal_masuk, $id_supplier, $id_user, $keterangan]);
            $id_barangmasuk = $conn->lastInsertId();

            $total_item = 0;
            $total_harga = 0;

            if (isset($_POST['id_barang']) && is_array($_POST['id_barang'])) {
                for ($i = 0; $i < count($_POST['id_barang']); $i++) {
                    $id_barang = $_POST['id_barang'][$i];
                    $jumlah = $_POST['jumlah'][$i];
                    $harga = $_POST['harga'][$i];
                    $subtotal = $jumlah * $harga;

                    // Detail barang masuk
                    $stmt_detail = $conn->prepare("INSERT INTO detail_barang_masuk (id_barangmasuk, id_barang, jumlah, harga, subtotal) VALUES (?, ?, ?, ?, ?)");
                    $stmt_detail->execute([$id_barangmasuk, $id_barang, $jumlah, $harga, $subtotal]);

                    // Update stok
                    $stmt_stok = $conn->prepare("UPDATE barang SET stok = stok + ? WHERE id_barang = ?");
                    $stmt_stok->execute([$jumlah, $id_barang]);

                    // Stok history
                    $stmt_history = $conn->prepare("INSERT INTO stok_history (id_barang, jenis_transaksi, jumlah, keterangan, id_user) VALUES (?, 'masuk', ?, ?, ?)");
                    $stmt_history->execute([$id_barang, $jumlah, "Barang masuk: $kode_masuk", $id_user]);

                    $total_item += $jumlah;
                    $total_harga += $subtotal;
                }
            }

            // Update total barang_masuk
            $stmt_update_total = $conn->prepare("UPDATE barang_masuk SET total_item = ?, total_harga = ? WHERE id_barangmasuk = ?");
            $stmt_update_total->execute([$total_item, $total_harga, $id_barangmasuk]);

            $_SESSION['message'] = "Barang masuk berhasil ditambahkan!";
            $_SESSION['message_type'] = "success";
            break;

        case 'delete':
            $id = $_POST['id'];

            // Ambil detail barang masuk
            $stmt_detail = $conn->prepare("SELECT * FROM detail_barang_masuk WHERE id_barangmasuk = ?");
            $stmt_detail->execute([$id]);
            $details = $stmt_detail->fetchAll();

            // Kurangi stok
            foreach ($details as $detail) {
                $stmt_stok = $conn->prepare("UPDATE barang SET stok = stok - ? WHERE id_barang = ?");
                $stmt_stok->execute([$detail['jumlah'], $detail['id_barang']]);
            }

            // Hapus barang masuk (detail akan ikut terhapus karena ON DELETE CASCADE)
            $stmt_delete = $conn->prepare("DELETE FROM barang_masuk WHERE id_barangmasuk = ?");
            $stmt_delete->execute([$id]);

            $_SESSION['message'] = "Barang masuk berhasil dihapus!";
            $_SESSION['message_type'] = "success";
            break;
    }

    header("Location: barang_masuk.php");
    exit();
}

// Get data barang masuk
$stmt_bm = $conn->prepare("SELECT bm.*, s.nama_supplier, u.nama AS nama_user FROM barang_masuk bm LEFT JOIN supplier s ON bm.id_supplier = s.id_supplier LEFT JOIN user u ON bm.id_user = u.id_user ORDER BY bm.tanggal_masuk DESC");
$stmt_bm->execute();
$result = $stmt_bm->fetchAll();

// Get suppliers
$stmt_supplier = $conn->prepare("SELECT * FROM supplier ORDER BY nama_supplier");
$stmt_supplier->execute();
$suppliers = $stmt_supplier->fetchAll();

// Get barang
$stmt_barang = $conn->prepare("SELECT * FROM barang WHERE status = 'aktif' ORDER BY nama_barang");
$stmt_barang->execute();
$barang_list = $stmt_barang->fetchAll();
?>


<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barang Masuk - Sistem Inventory Bengkel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css///inout_barang.css">
</head>

<body class="bg-light">
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header">
                        <h4 class="mb-0"><i class="fas fa-arrow-down me-2"></i>Barang Masuk</h4>
                    </div>
                    <div class="card-body">
                        <?php if (isset($_SESSION['message'])): ?>
                            <div class="alert alert-<?= $_SESSION['message_type']; ?> alert-dismissible fade show"
                                role="alert">
                                <?= $_SESSION['message']; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                            <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
                        <?php endif; ?>

                        <button type="button" class="btn btn-primary mb-3" data-bs-toggle="modal"
                            data-bs-target="#addModal">
                            <i class="fas fa-plus me-2"></i>Tambah Barang Masuk
                        </button>

                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>No</th>
                                        <th>Kode Masuk</th>
                                        <th>Tanggal</th>
                                        <th>Supplier</th>
                                        <th>Total Item</th>
                                        <th>Total Harga</th>
                                        <th>User</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $no = 1;
                                    foreach ($result as $row): ?>
                                        <tr>
                                            <td><?= $no++; ?></td>
                                            <td><?= $row['kode_masuk']; ?></td>
                                            <td><?= date('d/m/Y', strtotime($row['tanggal_masuk'])); ?></td>
                                            <td><?= $row['nama_supplier']; ?></td>
                                            <td><?= $row['total_item']; ?></td>
                                            <td>Rp <?= number_format($row['total_harga'], 0, ',', '.'); ?></td>
                                            <td><?= $row['nama_user']; ?></td>
                                            <td>
                                                <button class="btn btn-info btn-sm"
                                                    onclick="viewDetail(<?= $row['id_barangmasuk']; ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-danger btn-sm"
                                                    onclick="deleteItem(<?= $row['id_barangmasuk']; ?>)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Tambah -->
    <div class="modal fade" id="addModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Tambah Barang Masuk</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Tanggal Masuk</label>
                                <input type="date" class="form-control" name="tanggal_masuk" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Supplier</label>
                                <select class="form-select" name="id_supplier" required>
                                    <option value="">Pilih Supplier</option>
                                    <?php foreach ($suppliers as $supplier): ?>
                                        <option value="<?= $supplier['id_supplier']; ?>"><?= $supplier['nama_supplier']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Keterangan</label>
                            <textarea class="form-control" name="keterangan" rows="2"></textarea>
                        </div>

                        <hr>
                        <h6>Detail Barang</h6>
                        <div id="barangContainer">
                            <div class="row barang-row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Barang</label>
                                    <select class="form-select" name="id_barang[]" required>
                                        <option value="">Pilih Barang</option>
                                        <?php foreach ($barang_list as $barang): ?>
                                            <option value="<?= $barang['id_barang']; ?>"><?= $barang['nama_barang']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Jumlah</label>
                                    <input type="number" class="form-control" name="jumlah[]" min="1" required>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Harga</label>
                                    <input type="number" class="form-control" name="harga[]" min="0" required>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <label class="form-label">&nbsp;</label>
                                    <button type="button" class="btn btn-success form-control" onclick="addBarangRow()">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Detail -->
    <div class="modal fade" id="detailModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detail Barang Masuk</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="detailContent">
                    <!-- AJAX Detail Loaded Here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function addBarangRow() {
            const container = document.getElementById('barangContainer');
            const row = document.querySelector('.barang-row').cloneNode(true);

            row.querySelectorAll('input').forEach(input => input.value = '');
            row.querySelector('select').selectedIndex = 0;

            const button = row.querySelector('button');
            button.className = 'btn btn-danger form-control';
            button.innerHTML = '<i class="fas fa-minus"></i>';
            button.onclick = function() {
                this.closest('.barang-row').remove();
            };

            container.appendChild(row);
        }

        function deleteItem(id) {
            if (confirm('Apakah Anda yakin ingin menghapus barang masuk ini?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function viewDetail(id) {
            fetch(`detail_barang_masuk.php?id=${id}`)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('detailContent').innerHTML = data;
                    new bootstrap.Modal(document.getElementById('detailModal')).show();
                });
        }

        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            document.querySelector('input[name="tanggal_masuk"]').value = today;
        });
    </script>
</body>

</html>