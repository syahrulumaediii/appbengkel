<?php
// session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
$auth = new Auth();
checkAuth();

// Proses form
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $id_barang = $_POST['id_barang'];
    $jumlah = intval($_POST['jumlah']);
    $keterangan = $_POST['keterangan'] ?? '';
    $id_user = $_SESSION['user']['id_user']; // dari session login

    switch ($_POST['action']) {
        case 'masuk':
            $db->query(
                "INSERT INTO stok_history (id_barang, jenis_transaksi, jumlah, keterangan, id_user) 
                 VALUES (?, 'masuk', ?, ?, ?)",
                [$id_barang, $jumlah, $keterangan, $id_user]
            );

            $db->query(
                "UPDATE barang SET stok = stok + ? WHERE id_barang = ?",
                [$jumlah, $id_barang]
            );

            $message = "Stok masuk berhasil dicatat!";
            break;

        case 'keluar':
            $barang = $db->fetch("SELECT stok, nama_barang FROM barang WHERE id_barang = ?", [$id_barang]);

            if ($barang && $barang['stok'] >= $jumlah) {
                $db->query(
                    "INSERT INTO stok_history (id_barang, jenis_transaksi, jumlah, keterangan, id_user) 
                     VALUES (?, 'keluar', ?, ?, ?)",
                    [$id_barang, $jumlah, $keterangan, $id_user]
                );

                $db->query(
                    "UPDATE barang SET stok = stok - ? WHERE id_barang = ?",
                    [$jumlah, $id_barang]
                );

                $message = "Stok keluar berhasil dicatat!";
            } else {
                $error = "Stok tidak mencukupi! Tersedia: " . ($barang['stok'] ?? 0);
            }
            break;

        case 'adjustment':
            $stok_baru = intval($_POST['stok_baru']);
            $barang = $db->fetch("SELECT stok FROM barang WHERE id_barang = ?", [$id_barang]);

            if ($barang !== false) {
                $selisih = $stok_baru - $barang['stok'];
                if ($selisih != 0) {
                    $jenis = $selisih > 0 ? 'adjustment_plus' : 'adjustment_minus';

                    $db->query(
                        "INSERT INTO stok_history (id_barang, jenis_transaksi, jumlah, keterangan, id_user) 
                         VALUES (?, ?, ?, ?, ?)",
                        [$id_barang, $jenis, abs($selisih), $keterangan, $id_user]
                    );

                    $db->query(
                        "UPDATE barang SET stok = ? WHERE id_barang = ?",
                        [$stok_baru, $id_barang]
                    );

                    $message = "Penyesuaian stok berhasil.";
                } else {
                    $error = "Tidak ada perubahan stok.";
                }
            } else {
                $error = "Barang tidak ditemukan.";
            }
            break;
    }
}

// Ambil histori stok
$history = $db->fetchAll("
    SELECT sh.*, b.nama_barang, b.kode_barang, u.nama,u.role 
    FROM stok_history sh
    JOIN barang b ON sh.id_barang = b.id_barang
    JOIN user u ON sh.id_user = u.id_user
    ORDER BY sh.tanggal DESC
    LIMIT 50
");

// Ambil daftar barang untuk dropdown
$barang_list = $db->fetchAll("
    SELECT id_barang, kode_barang, nama_barang, stok 
    FROM barang 
    ORDER BY nama_barang
");
?>


<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Stok - Workshop Inventory</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>

<body>
    <!-- START NAVBAR STOK -->
    <div class="container-fluid">
        <div class="row">
            <!-- START SIDEBAR -->
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
                        <a class="nav-link text-warning" href="stok.php">
                            <i class="fas fa-warehouse"></i> Stok
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="transaksi.php">
                            <i class="fas fa-exchange-alt"></i> Transaksi
                        </a>
                    </li>
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
            <!-- END SIDEBAR -->

            <!-- START Main Content STOKK-->
            <div class="col-md-10 p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-warehouse"></i> Manajemen Stok</h2>
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

                <!-- START PERINGATAN STOK RENDAH -->
                <?php if (!empty($low_stock)): ?>
                    <div class="alert alert-warning" role="alert">
                        <h5><i class="fas fa-exclamation-triangle"></i> Peringatan Stok Rendah</h5>
                        <p>Terdapat <?php echo count($low_stock); ?> barang dengan stok di bawah minimum:</p>
                        <ul class="mb-0">
                            <?php foreach ($low_stock as $item): ?>
                                <li><?php echo $item['nama_barang']; ?> - Stok: <?php echo $item['stok']; ?> (Min:
                                    <?php echo $item['stok_minimum']; ?>)</li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                <!-- END PERINGATAN STOK RENDAH -->

                <!-- START TAB UNTUK OPERASI STOK -->
                <ul class="nav nav-tabs mb-4" id="stockTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="masuk-tab" data-bs-toggle="tab" data-bs-target="#masuk"
                            type="button" role="tab">
                            <i class="fas fa-plus-circle text-success"></i> Stok Masuk
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="keluar-tab" data-bs-toggle="tab" data-bs-target="#keluar"
                            type="button" role="tab">
                            <i class="fas fa-minus-circle text-danger"></i> Stok Keluar
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="adjustment-tab" data-bs-toggle="tab" data-bs-target="#adjustment"
                            type="button" role="tab">
                            <i class="fas fa-balance-scale text-info"></i> Adjustment
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="history-tab" data-bs-toggle="tab" data-bs-target="#history"
                            type="button" role="tab">
                            <i class="fas fa-history"></i> Riwayat
                        </button>
                    </li>
                </ul>
                <!-- END TAB UNTUK OPERASI STOK -->


                <!-- START OPERASI DALAM STOK -->
                <div class="tab-content" id="stockTabsContent">
                    <!-- START ADD STOK MASUK -->
                    <div class="tab-pane fade show active" id="masuk" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <h5>Input Stok Masuk</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="action" value="masuk">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="barang_id_masuk" class="form-label">Pilih Barang</label>
                                                <select class="form-control" id="barang_id_masuk" name="id_barang"
                                                    required>
                                                    <option value="">Pilih Barang</option>
                                                    <?php foreach ($barang_list as $barang): ?>
                                                        <option value="<?php echo $barang['id_barang']; ?>">
                                                            <?php echo $barang['kode_barang'] . ' - ' . $barang['nama_barang'] . ' (Stok: ' . $barang['stok'] . ')'; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label for="jumlah_masuk" class="form-label">Jumlah Masuk</label>
                                                <input type="number" class="form-control" id="jumlah_masuk"
                                                    name="jumlah" min="1" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="keterangan_masuk" class="form-label">Keterangan</label>
                                                <textarea class="form-control" id="keterangan_masuk" name="keterangan"
                                                    rows="3" placeholder="Pembelian dari supplier, dll"></textarea>
                                            </div>
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-plus"></i> Tambah Stok
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <!-- END ADD STOK MASUK -->

                    <!-- Stok Keluar -->
                    <div class="tab-pane fade" id="keluar" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <h5>Input Stok Keluar</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="action" value="keluar">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="barang_id_keluar" class="form-label">Pilih Barang</label>
                                                <select class="form-control" id="barang_id_keluar" name="id_barang"
                                                    required>
                                                    <option value="">Pilih Barang</option>
                                                    <?php foreach ($barang_list as $barang): ?>
                                                        <option value="<?php echo $barang['id_barang']; ?>">
                                                            <?php echo $barang['kode_barang'] . ' - ' . $barang['nama_barang'] . ' (Stok: ' . $barang['stok'] . ')'; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label for="jumlah_keluar" class="form-label">Jumlah Keluar</label>
                                                <input type="number" class="form-control" id="jumlah_keluar"
                                                    name="jumlah" min="1" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="keterangan_keluar" class="form-label">Keterangan</label>
                                                <textarea class="form-control" id="keterangan_keluar" name="keterangan"
                                                    rows="3" placeholder="Penjualan, pemakaian service, dll"
                                                    required></textarea>
                                            </div>
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-danger">
                                        <i class="fas fa-minus"></i> Kurangi Stok
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Stock Adjustment -->
                    <div class="tab-pane fade" id="adjustment" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <h5>Adjustment Stok</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="action" value="adjustment">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="barang_id_adj" class="form-label">Pilih Barang</label>
                                                <select class="form-control" id="barang_id_adj" name="id_barang"
                                                    required onchange="showCurrentStock(this)">
                                                    <option value="">Pilih Barang</option>
                                                    <?php foreach ($barang_list as $barang): ?>
                                                        <option value="<?php echo $barang['id_barang']; ?>"
                                                            data-stok="<?php echo $barang['stok']; ?>">
                                                            <?php echo $barang['kode_barang'] . ' - ' . $barang['nama_barang'] . ' (Stok: ' . $barang['stok'] . ')'; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Stok Saat Ini</label>
                                                <input type="text" class="form-control" id="stok_sekarang" readonly>
                                            </div>
                                            <div class="mb-3">
                                                <label for="stok_baru" class="form-label">Stok Baru</label>
                                                <input type="number" class="form-control" id="stok_baru"
                                                    name="stok_baru" min="0" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="keterangan_adj" class="form-label">Keterangan</label>
                                                <textarea class="form-control" id="keterangan_adj" name="keterangan"
                                                    rows="3"
                                                    placeholder="Alasan adjustment (stock opname, kerusakan, dll)"
                                                    required></textarea>
                                            </div>
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-info">
                                        <i class="fas fa-balance-scale"></i> Lakukan Adjustment
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- START RIWAYAT STOK -->
                    <div class="tab-pane fade" id="history" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <h5>Riwayat Pergerakan Stok (50 Terakhir)</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Tanggal</th>
                                                <th>Kode Barang</th>
                                                <th>Nama Barang</th>
                                                <th>Jenis</th>
                                                <th>Jumlah</th>
                                                <th>Keterangan</th>
                                                <th>User</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($history as $item): ?>
                                                <tr>
                                                    <td><?php echo date('d/m/Y H:i', strtotime($item['tanggal'])); ?></td>
                                                    <td><?php echo $item['kode_barang']; ?></td>
                                                    <td><?php echo $item['nama_barang']; ?></td>
                                                    <td>
                                                        <?php
                                                        $badges = [
                                                            'masuk' => '<span class="badge bg-success"><i class="fas fa-plus"></i> Masuk</span>',
                                                            'keluar' => '<span class="badge bg-danger"><i class="fas fa-minus"></i> Keluar</span>',
                                                            'adjustment_plus' => '<span class="badge bg-info"><i class="fas fa-plus"></i> Adj +</span>',
                                                            'adjustment_minus' => '<span class="badge bg-warning"><i class="fas fa-minus"></i> Adj -</span>'
                                                        ];
                                                        echo $badges[$item['jenis_transaksi']] ?? $item['jenis_transaksi'];
                                                        ?>
                                                    </td>
                                                    <td><?php echo $item['jumlah']; ?></td>
                                                    <td><?php echo $item['keterangan']; ?></td>
                                                    <td><?php echo $item['nama'] . '  (' . $item['role'] . ')'; ?></td>

                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- END RIWAYAT STOK -->
                </div>
                <!-- END OPERASI DALAM STOK -->
            </div>
            <!-- END MAIN CONTENT STOK -->
        </div>
    </div>
    <!-- END NAVBAR STOK -->

    <!-- START SCRIPT -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        src = "./js/script.js"
    </script>
    <!-- END SCRIPT -->
</body>

</html>