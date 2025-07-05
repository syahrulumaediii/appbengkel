<?php
// session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
$auth = new Auth();
checkAuth();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'add_transaksi':
            try {
                $db->getConnection()->beginTransaction();

                // Insert transaksi
                $db->query(
                    "INSERT INTO transaksi (
                        kode_transaksi, tanggal_transaksi, customer_name, 
                        total_item, total_harga, diskon, pajak, grand_total, 
                        bayar, kembalian, id_user, status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed')",
                    [
                        $_POST['kode_transaksi'],
                        $_POST['tanggal_transaksi'],
                        $_POST['customer_name'],
                        $_POST['total_item'],
                        $_POST['total_harga'],
                        $_POST['diskon'],
                        $_POST['pajak'],
                        $_POST['grand_total'],
                        $_POST['bayar'],
                        $_POST['kembalian'],
                        $_SESSION['user']['id_user']
                    ]
                );

                $transaksi_id = $db->lastInsertId();

                // Insert detail transaksi dan update stok
                $barang_ids = $_POST['id_barang'];
                $quantities = $_POST['quantity'];
                $prices = $_POST['price'];

                for ($i = 0; $i < count($barang_ids); $i++) {
                    if (!empty($barang_ids[$i]) && $quantities[$i] > 0) {
                        // Ambil stok
                        $barang = $db->fetch("SELECT stok, nama_barang FROM barang WHERE id_barang = ?", [$barang_ids[$i]]);
                        if (!$barang || $barang['stok'] < $quantities[$i]) {
                            throw new Exception("Stok tidak cukup untuk " . $barang['nama_barang']);
                        }

                        $subtotal = $quantities[$i] * $prices[$i];

                        // Insert detail transaksi
                        $db->query(
                            "INSERT INTO detail_transaksi (id_transaksi, id_barang, jumlah, harga, subtotal) VALUES (?, ?, ?, ?, ?)",
                            [$transaksi_id, $barang_ids[$i], $quantities[$i], $prices[$i], $subtotal]
                        );

                        // Update stok
                        $db->query(
                            "UPDATE barang SET stok = stok - ? WHERE id_barang = ?",
                            [$quantities[$i], $barang_ids[$i]]
                        );
                    }
                }

                $db->getConnection()->commit();
                $message = "Transaksi berhasil disimpan!";
            } catch (Exception $e) {
                $db->getConnection()->rollBack();
                $error = "Terjadi kesalahan: " . $e->getMessage();
            }
            break;

        case 'delete_transaksi':
            try {
                $db->getConnection()->beginTransaction();

                // Ambil detail
                $details = $db->fetchAll(
                    "SELECT id_barang, jumlah FROM detail_transaksi WHERE id_transaksi = ?",
                    [$_POST['id_transaksi']]
                );

                // Restore stok
                foreach ($details as $detail) {
                    $db->query(
                        "UPDATE barang SET stok = stok + ? WHERE id_barang = ?",
                        [$detail['jumlah'], $detail['id_barang']]
                    );
                }

                // Hapus detail dan transaksi
                $db->query("DELETE FROM detail_transaksi WHERE id_transaksi = ?", [$_POST['id_transaksi']]);
                $db->query("DELETE FROM transaksi WHERE id_transaksi = ?", [$_POST['id_transaksi']]);

                $db->getConnection()->commit();
                $message = "Transaksi berhasil dihapus dan stok dikembalikan.";
            } catch (Exception $e) {
                $db->getConnection()->rollBack();
                $error = "Terjadi kesalahan: " . $e->getMessage();
            }
            break;
    }
}

// Kode transaksi otomatis
$today = date('Ymd');
$last = $db->fetch("SELECT kode_transaksi FROM transaksi WHERE kode_transaksi LIKE 'TRX{$today}%' ORDER BY id_transaksi DESC LIMIT 1");
$next_number = 1;
if ($last) {
    $last_number = intval(substr($last['kode_transaksi'], -3));
    $next_number = $last_number + 1;
}
$kode_transaksi = 'TRX' . $today . str_pad($next_number, 3, '0', STR_PAD_LEFT);

// Ambil barang
$barang_list = $db->fetchAll("SELECT id_barang, kode_barang, nama_barang, harga_jual, stok FROM barang WHERE stok > 0 ORDER BY nama_barang");

// FIXED: Query transaksi diperbaiki agar sesuai dengan field yang digunakan di HTML
$transaksi_list = $db->fetchAll("
    SELECT t.id_transaksi, t.kode_transaksi, t.tanggal_transaksi, t.customer_name, 
           t.total_item, t.grand_total, t.status, u.username
    FROM transaksi t
    JOIN user u ON t.id_user = u.id_user
    ORDER BY t.tanggal_transaksi DESC, t.id_transaksi DESC
    LIMIT 50
");

$detail_list = $db->fetchAll("
SELECT 
    t.id_transaksi,
    t.kode_transaksi,
    t.tanggal_transaksi,
    t.customer_name,
    t.total_item,
    t.total_harga,
    t.diskon,
    t.pajak,
    t.grand_total,
    t.bayar,
    t.kembalian,
    t.status,
    t.created_at,
    t.updated_at,
    u.username AS nama_user,

    -- Jumlah item dari detail_transaksi
    (
        SELECT COUNT(*) 
        FROM detail_transaksi dt 
        WHERE dt.id_transaksi = t.id_transaksi
    ) AS JumlahBarang

FROM transaksi t
LEFT JOIN user u ON t.id_user = u.id_user
ORDER BY t.tanggal_transaksi DESC, t.id_transaksi DESC;
");

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaksi Penjualan - Workshop Inventory</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../css/barang.css" rel="stylesheet">

</head>

<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 bg-dark text-white p-3" style="min-height: 100vh;">
                <h5><i class="fas fa-tools"></i> Workshop</h5>
                <hr>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link text-white" href="../dashboard.php">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="barang.php">
                            <i class="fas fa-boxes"></i> Barang
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="supplier.php">
                            <i class="fas fa-truck"></i> Supplier
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="stok.php">
                            <i class="fas fa-warehouse"></i> Stok
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-warning" href="transaksi.php">
                            <i class="fas fa-exchange-alt"></i> Transaksi
                        </a>
                    </li>
                    <li class="nav-item">
                        <?php if ($auth->isAdmin()): ?>
                            <a class="nav-link text-white" href="../pages/user.php">
                                <i class="fas fa-users me-2"></i>User Management
                            </a>
                        <?php endif; ?>
                    </li>
                    <li class="nav-item mt-3">
                        <a class="nav-link text-white" href="logout.php">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Main Content TRANSAKSI -->
            <div class="col-md-10 p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-exchange-alt"></i> Transaksi Penjualan</h2>
                    <div>
                        <span class="text-muted">Selamat datang, <?php echo $auth->getUserName(); ?></span>
                    </div>
                </div>

                <?php if (isset($message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Tabs -->
                <ul class="nav nav-tabs mb-4" id="transaksiTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="new-tab" data-bs-toggle="tab" data-bs-target="#new"
                            type="button" role="tab">
                            <i class="fas fa-plus"></i> Transaksi Baru
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="list-tab" data-bs-toggle="tab" data-bs-target="#list" type="button"
                            role="tab">
                            <i class="fas fa-list"></i> Daftar Transaksi
                        </button>
                    </li>
                </ul>

                <!-- START MAIN KONTEN ADD TRANSAKSI -->
                <div class="tab-content" id="transaksiTabsContent">
                    <div class="tab-pane fade show active" id="new" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <h5>Transaksi Penjualan Baru</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" id="transaksiForm">
                                    <input type="hidden" name="action" value="add_transaksi">

                                    <!-- FORM TRANSAKSI -->
                                    <div class="row mb-3">
                                        <div class="col-md-4">
                                            <label>Kode Transaksi</label>
                                            <input type="text" class="form-control" name="kode_transaksi"
                                                value="<?= $kode_transaksi ?>" readonly>
                                        </div>
                                        <div class="col-md-4">
                                            <label>Tanggal Transaksi</label>
                                            <input type="date" class="form-control" name="tanggal_transaksi"
                                                value="<?= date('Y-m-d') ?>" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label>Nama Customer</label>
                                            <input type="text" class="form-control" name="customer_name"
                                                id="customer_name" required>
                                        </div>
                                    </div>

                                    <!-- FORM BARANG YANG DI BELI -->
                                    <h6 class="mt-4">Barang Dibeli</h6>
                                    <table class="table table-bordered" id="itemsTable">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Barang</th>
                                                <th>Harga</th>
                                                <th>Qty</th>
                                                <th>Subtotal</th>
                                                <th><button type="button" class="btn btn-success btn-sm"
                                                        onclick="addItemRow()"><i class="fas fa-plus"></i></button></th>
                                            </tr>
                                        </thead>
                                        <tbody id="itemsBody">
                                            <tr>
                                                <td>
                                                    <select class="form-control" name="id_barang[]"
                                                        onchange="updateItem(this)">
                                                        <option value="">Pilih Barang</option>
                                                        <?php foreach ($barang_list as $barang): ?>
                                                            <option value="<?= $barang['id_barang'] ?>"
                                                                data-harga="<?= $barang['harga_jual'] ?>"
                                                                data-stok="<?= $barang['stok'] ?>">
                                                                <?= $barang['kode_barang'] . ' - ' . $barang['nama_barang'] ?>
                                                                (Stok: <?= $barang['stok'] ?>)
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </td>
                                                <td><input type="number" class="form-control harga" name="price[]"
                                                        readonly></td>
                                                <td><input type="number" class="form-control qty" name="quantity[]"
                                                        min="1" onchange="hitungSubtotal(this)"></td>
                                                <td><input type="number" class="form-control subtotal" readonly>
                                                </td>
                                                <td><button type="button" class="btn btn-danger btn-sm"
                                                        onclick="removeRow(this)"><i class="fas fa-trash"></i></button>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>

                                    <div class="row">
                                        <div class="col-md-3">
                                            <label>Total Item</label>
                                            <input type="number" class="form-control" name="total_item" id="total_item"
                                                readonly>
                                        </div>
                                        <div class="col-md-3">
                                            <label>Total Harga</label>
                                            <input type="number" class="form-control" name="total_harga"
                                                id="total_harga" readonly>
                                        </div>
                                        <div class="col-md-2">
                                            <label>Diskon</label>
                                            <input type="number" class="form-control" name="diskon" id="diskon"
                                                value="0" onchange="updateGrandTotal()">
                                        </div>
                                        <div class="col-md-2">
                                            <label>Pajak</label>
                                            <input type="number" class="form-control" name="pajak" id="pajak" value="0"
                                                onchange="updateGrandTotal()">
                                        </div>
                                        <div class="col-md-2">
                                            <label>Grand Total</label>
                                            <input type="number" class="form-control" name="grand_total"
                                                id="grand_total" readonly>
                                        </div>
                                    </div>

                                    <div class="row mt-3">
                                        <div class="col-md-6">
                                            <label>Bayar</label>
                                            <input type="number" class="form-control" name="bayar" id="bayar"
                                                onchange="updateKembalian()">
                                        </div>
                                        <div class="col-md-6">
                                            <label>Kembalian</label>
                                            <input type="number" class="form-control" name="kembalian" id="kembalian"
                                                readonly>
                                        </div>
                                    </div>

                                    <div class="mt-4 text-end">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save"></i> Simpan Transaksi
                                        </button>
                                        <button type="button" class="btn btn-secondary" onclick="resetForm()">
                                            <i class="fas fa-refresh"></i> Reset
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <!-- END FORM TRANSAKSI -->

                    <!-- START DAFTAR TRANSAKSI -->
                    <div class="tab-pane fade" id="list" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <h5>Daftar Transaksi (50 Terakhir)</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Kode Transaksi</th>
                                                <th>Tanggal</th>
                                                <th>Customer</th>
                                                <th>Items</th>
                                                <th>Grand Total</th>
                                                <th>Status</th>
                                                <th>User</th>
                                                <th>Aksi</th>
                                            </tr>
                                        </thead>

                                        <tbody>
                                            <?php foreach ($transaksi_list as $transaksi): ?>
                                                <tr>
                                                    <td><?php echo $transaksi['kode_transaksi']; ?></td>
                                                    <td><?php echo date('d/m/Y', strtotime($transaksi['tanggal_transaksi'])); ?>
                                                    </td>
                                                    <td><?php echo $transaksi['customer_name']; ?></td>
                                                    <td><?php echo $transaksi['total_item']; ?> item(s)</td>
                                                    <td>Rp
                                                        <?php echo number_format($transaksi['grand_total'], 0, ',', '.'); ?>
                                                    </td>
                                                    <td>
                                                        <span
                                                            class="badge bg-<?php echo $transaksi['status'] == 'completed' ? 'success' : ($transaksi['status'] == 'pending' ? 'warning' : 'danger'); ?>">
                                                            <?php echo ucfirst($transaksi['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo $transaksi['username']; ?></td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm">
                                                            <!-- DIPERBAIKI: Menggunakan $transaksi['id_transaksi'] bukan $barang['id_transaksi'] -->
                                                            <button type="button" class="btn btn-info"
                                                                title="Detail Transaksi" data-bs-toggle="modal"
                                                                data-bs-target="#detailtransaksi<?php echo $transaksi['id_transaksi']; ?>">
                                                                <i class="fas fa-eye"></i>
                                                            </button>
                                                            <form method="POST" style="display: inline;"
                                                                onsubmit="return confirm('Yakin hapus transaksi ini? Stok akan dikembalikan.')">
                                                                <input type="hidden" name="action" value="delete_transaksi">
                                                                <input type="hidden" name="id_transaksi"
                                                                    value="<?php echo $transaksi['id_transaksi']; ?>">
                                                                <button type="submit" class="btn btn-danger"
                                                                    title="Hapus Transaksi">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </form>
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
                    <!-- END : Content DAFTAR TRANSAKSI -->
                </div>
                <!-- END : Content ADD TRANSAKSI -->


                <!-- START DETAIL TRANSAKSI -->
                <?php foreach ($detail_list as $transaksi): ?>
                    <!-- Modal Detail Transaksi -->
                    <div class="modal fade" id="detailtransaksi<?php echo $transaksi['id_transaksi']; ?>" tabindex="-1"
                        aria-labelledby="detailTransaksiLabel<?php echo $transaksi['id_transaksi']; ?>" aria-hidden="true">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title"
                                        id="detailTransaksiLabel<?php echo $transaksi['id_transaksi']; ?>">
                                        Detail Transaksi - <?php echo $transaksi['kode_transaksi']; ?>
                                    </h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"
                                        aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <!-- Informasi Transaksi -->
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <strong>Kode Transaksi:</strong><br>
                                            <?php echo $transaksi['kode_transaksi']; ?>
                                        </div>
                                        <div class="col-md-6">
                                            <strong>Tanggal:</strong><br>
                                            <?php echo date('d/m/Y H:i', strtotime($transaksi['tanggal_transaksi'])); ?>
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <strong>Customer:</strong><br>
                                            <?php echo $transaksi['customer_name']; ?>
                                        </div>
                                        <div class="col-md-6">
                                            <strong>Kasir:</strong><br>
                                            <?php echo $transaksi['nama_user']; ?>
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <strong>Status:</strong><br>
                                            <span
                                                class="badge bg-<?php echo $transaksi['status'] == 'completed' ? 'success' : ($transaksi['status'] == 'pending' ? 'warning' : 'danger'); ?>">
                                                <?php echo ucfirst($transaksi['status']); ?>
                                            </span>
                                        </div>
                                        <div class="col-md-6">
                                            <strong>Total Item:</strong><br>
                                            <?php echo $transaksi['total_item']; ?> item(s)
                                        </div>
                                    </div>

                                    <!-- Detail Barang -->
                                    <hr>
                                    <h6>Detail Barang:</h6>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Nama Barang</th>
                                                    <th>Qty</th>
                                                    <th>Harga</th>
                                                    <th>Subtotal</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                // Query untuk mengambil detail barang transaksi - DIPERBAIKI
                                                $detail_barang = $db->fetchAll(
                                                    "SELECT dt.*, b.nama_barang, t.total_item, dt.jumlah as qty
                                                    FROM detail_transaksi dt
                                                    JOIN transaksi t ON dt.id_transaksi = t.id_transaksi
                                                    JOIN barang b ON dt.id_barang = b.id_barang 
                                                    WHERE dt.id_transaksi = ?",
                                                    [$transaksi['id_transaksi']]
                                                );

                                                foreach ($detail_barang as $detail): ?>
                                                    <tr>
                                                        <td><?php echo $detail['nama_barang']; ?></td>
                                                        <td><?php echo $detail['qty']; ?></td>
                                                        <td>Rp <?php echo number_format($detail['harga'], 0, ',', '.'); ?></td>
                                                        <td>Rp <?php echo number_format($detail['subtotal'], 0, ',', '.'); ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>

                                    <!-- Summary Pembayaran -->
                                    <hr>
                                    <div class="row">
                                        <div class="col-md-6 offset-md-6">
                                            <table class="table table-sm">
                                                <tr>
                                                    <td><strong>Total Harga:</strong></td>
                                                    <td class="text-end">Rp
                                                        <?php echo number_format($transaksi['total_harga'], 0, ',', '.'); ?>
                                                    </td>
                                                </tr>
                                                <!-- Tampilan Diskon -->
                                                <?php if ($transaksi['diskon'] > 0): ?>
                                                    <tr>
                                                        <td><strong>Diskon:</strong></td>
                                                        <td class="text-end">
                                                            <?php echo number_format($transaksi['diskon'], 0, ',', '.');
                                                            // Hitung persentase diskon berdasarkan total_harga
                                                            $diskon_persen = ($transaksi['total_harga'] > 0) ?
                                                                round(($transaksi['diskon'] / $transaksi['total_harga']) * 100, 1) : 0; ?>%
                                                        </td>
                                                        <!-- <?php echo $diskon_persen; ?> -->
                                                    </tr>
                                                <?php endif; ?>

                                                <!-- Tampilan Pajak -->
                                                <?php if ($transaksi['pajak'] > 0): ?>
                                                    <tr>
                                                        <td><strong>Pajak:</strong></td>
                                                        <td class="text-end">
                                                            <?php echo number_format($transaksi['pajak'], 0, ',', '.');
                                                            // Hitung persentase pajak berdasarkan total_harga setelah diskon
                                                            $subtotal_after_discount = $transaksi['total_harga'] - $transaksi['diskon'];
                                                            $pajak_persen = ($subtotal_after_discount > 0) ?
                                                                round(($transaksi['pajak'] / $subtotal_after_discount) * 100, 1) : 0; ?>%
                                                        </td>
                                                        <!-- <?php echo $pajak_persen; ?> -->
                                                    </tr>
                                                <?php endif; ?>
                                                <tr class="table-success">
                                                    <td><strong>Grand Total:</strong></td>
                                                    <td class="text-end"><strong>Rp
                                                            <?php echo number_format($transaksi['grand_total'], 0, ',', '.'); ?></strong>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td><strong>Bayar:</strong></td>
                                                    <td class="text-end">Rp
                                                        <?php echo number_format($transaksi['bayar'], 0, ',', '.'); ?></td>
                                                </tr>
                                                <tr>
                                                    <td><strong>Kembalian:</strong></td>
                                                    <td class="text-end">Rp
                                                        <?php echo number_format($transaksi['kembalian'], 0, ',', '.'); ?>
                                                    </td>
                                                </tr>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                                    <button type="button" class="btn btn-primary" onclick="window.print();">
                                        <i class="fas fa-print"></i> Print
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                <!-- END DETAIL TRANSAKSI -->
            </div>
            <!-- END KONTEN TRANSAKSI -->
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let barangOptions = `<option value="">Pilih Barang</option><?php foreach ($barang_list as $barang): ?>
            <option value="<?php echo $barang['id_barang']; ?>" 
                    data-harga="<?php echo $barang['harga_jual']; ?>"
                    data-stok="<?php echo $barang['stok']; ?>">
                <?php echo $barang['kode_barang'] . ' - ' . $barang['nama_barang'] . ' (Stok: ' . $barang['stok'] . ')'; ?>
            </option>
        <?php endforeach; ?>`;

        function addItemRow() {
            const tbody = document.getElementById('itemsBody');
            const newRow = document.createElement('tr');
            newRow.innerHTML = `
                <td>
                    <select class="form-control" name="id_barang[]" onchange="updateItem(this)">
                        ${barangOptions}
                    </select>
                </td>
                <td><input type="number" class="form-control harga" name="price[]" readonly></td>
                <td><input type="number" class="form-control qty" name="quantity[]" min="1" onchange="hitungSubtotal(this)"></td>
                <td><input type="number" class="form-control subtotal" readonly></td>
                <td><button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)"><i class="fas fa-trash"></i></button></td>
            `;
            tbody.appendChild(newRow);
        }

        function removeRow(button) {
            const row = button.closest('tr');
            const tbody = document.getElementById('itemsBody');

            if (tbody.children.length > 1) {
                row.remove();
                updateGrandTotal();
            } else {
                alert('Minimal harus ada 1 baris item!');
            }
        }

        function updateItem(selectElement) {
            const selectedOption = selectElement.options[selectElement.selectedIndex];
            const row = selectElement.closest('tr');
            const hargaInput = row.querySelector('.harga');
            const qtyInput = row.querySelector('.qty');
            const subtotalInput = row.querySelector('.subtotal');

            if (selectedOption.value) {
                const harga = selectedOption.getAttribute('data-harga');
                const stok = selectedOption.getAttribute('data-stok');

                hargaInput.value = harga;
                qtyInput.max = stok;
                qtyInput.value = '';
                subtotalInput.value = '';
                qtyInput.placeholder = `Max: ${stok}`;
            } else {
                hargaInput.value = '';
                qtyInput.value = '';
                qtyInput.max = '';
                qtyInput.placeholder = '';
                subtotalInput.value = '';
            }

            updateGrandTotal();
        }

        function hitungSubtotal(qtyInput) {
            const row = qtyInput.closest('tr');
            const hargaInput = row.querySelector('.harga');
            const subtotalInput = row.querySelector('.subtotal');
            const barangSelect = row.querySelector('select[name="id_barang[]"]');

            const harga = parseFloat(hargaInput.value) || 0;
            const qty = parseInt(qtyInput.value) || 0;
            const maxStok = parseInt(qtyInput.max) || 0;

            // Validasi stok
            if (qty > maxStok && maxStok > 0) {
                alert(`Quantity tidak boleh melebihi stok yang tersedia (${maxStok})`);
                qtyInput.value = maxStok;
                return;
            }

            // Validasi barang harus dipilih
            if (!barangSelect.value && qty > 0) {
                alert('Pilih barang terlebih dahulu!');
                qtyInput.value = '';
                return;
            }

            const subtotal = harga * qty;
            subtotalInput.value = subtotal;

            updateGrandTotal();
        }

        function updateGrandTotal() {
            const subtotalInputs = document.querySelectorAll('.subtotal');
            let totalHarga = 0;
            let totalItem = 0;

            subtotalInputs.forEach(input => {
                const row = input.closest('tr');
                const qty = parseInt(row.querySelector('.qty').value) || 0;
                const subtotal = parseFloat(input.value) || 0;

                totalHarga += subtotal;
                totalItem += qty;
            });

            const diskonPersen = parseFloat(document.getElementById('diskon').value) || 0;
            const pajakPersen = parseFloat(document.getElementById('pajak').value) || 0;

            const nilaiDiskon = (diskonPersen / 100) * totalHarga;
            const setelahDiskon = totalHarga - nilaiDiskon;
            const nilaiPajak = (pajakPersen / 100) * setelahDiskon;
            const grandTotal = setelahDiskon + nilaiPajak;

            document.getElementById('total_item').value = totalItem;
            document.getElementById('total_harga').value = totalHarga.toFixed(2);
            document.getElementById('grand_total').value = grandTotal.toFixed(2);

            // (Optional) Kalau kamu juga ingin menampilkan nilaiDiskon dan nilaiPajak:
            const diskonOutput = document.getElementById('nilai_diskon');
            const pajakOutput = document.getElementById('nilai_pajak');
            if (diskonOutput) diskonOutput.textContent = nilaiDiskon.toFixed(2);
            if (pajakOutput) pajakOutput.textContent = nilaiPajak.toFixed(2);
        }


        function updateKembalian() {
            const grandTotal = parseFloat(document.getElementById('grand_total').value) || 0;
            const bayar = parseFloat(document.getElementById('bayar').value) || 0;
            const kembalian = bayar - grandTotal;

            document.getElementById('kembalian').value = kembalian >= 0 ? kembalian : 0;
        }

        function resetForm() {
            document.getElementById('transaksiForm').reset();

            const tbody = document.getElementById('itemsBody');
            tbody.innerHTML = `
                <tr>
                    <td>
                        <select class="form-control" name="id_barang[]" onchange="updateItem(this)">
                            ${barangOptions}
                        </select>
                    </td>
                    <td><input type="number" class="form-control harga" name="price[]" readonly></td>
                    <td><input type="number" class="form-control qty" name="quantity[]" min="1" onchange="hitungSubtotal(this)"></td>
                    <td><input type="number" class="form-control subtotal" readonly></td>
                    <td><button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)"><i class="fas fa-trash"></i></button></td>
                </tr>
            `;

            updateGrandTotal();
            document.getElementById('customer_name').focus();
        }

        // Validasi form sebelum submit
        document.getElementById('transaksiForm').addEventListener('submit', function(e) {
            const customerName = document.getElementById('customer_name').value.trim();
            const barangSelects = document.querySelectorAll('select[name="id_barang[]"]');
            const qtyInputs = document.querySelectorAll('.qty');
            const grandTotal = parseFloat(document.getElementById('grand_total').value) || 0;
            const bayar = parseFloat(document.getElementById('bayar').value) || 0;

            // Validasi customer
            if (!customerName) {
                alert('Nama customer harus diisi!');
                e.preventDefault();
                return;
            }

            // Validasi minimal 1 item
            let hasValidItem = false;
            for (let i = 0; i < barangSelects.length; i++) {
                const barangId = barangSelects[i].value;
                const qty = parseInt(qtyInputs[i].value) || 0;

                if (barangId && qty > 0) {
                    hasValidItem = true;
                    break;
                }
            }

            if (!hasValidItem) {
                alert('Minimal harus ada 1 item yang valid!');
                e.preventDefault();
                return;
            }

            // Validasi grand total
            if (grandTotal <= 0) {
                alert('Grand total transaksi harus lebih dari 0!');
                e.preventDefault();
                return;
            }

            // Validasi pembayaran
            if (bayar < grandTotal) {
                alert('Jumlah bayar tidak boleh kurang dari grand total!');
                e.preventDefault();
                return;
            }

            // Konfirmasi sebelum submit
            if (!confirm('Yakin ingin menyimpan transaksi ini?')) {
                e.preventDefault();
                return;
            }
        });

        // Auto-focus ke customer nama saat halaman dimuat
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('customer_name').focus();
        });
    </script>
</body>

</html>