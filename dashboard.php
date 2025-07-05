<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';

$auth = new Auth();
$auth->requireLogin();

// Statistik utama
$stats = [
    'total_barang' => $db->fetch("SELECT COUNT(*) as count FROM barang")['count'],
    'total_supplier' => $db->fetch("SELECT COUNT(*) as count FROM supplier")['count'],
    'total_transaksi' => $db->fetch("SELECT COUNT(*) as count FROM transaksi WHERE DATE(created_at) = CURDATE()")['count'],
    'barang_habis' => $db->fetch("SELECT COUNT(*) as count FROM barang WHERE stok <= 5")['count']
];

// Transaksi terbaru
$recent_transactions = $db->fetchAll("
    SELECT t.*, u.nama AS user_name 
    FROM transaksi t 
    LEFT JOIN user u ON t.id_user = u.id_user
    ORDER BY t.created_at DESC 
    LIMIT 5
");

// Barang dengan stok rendah
$low_stock = $db->fetchAll("
    SELECT b.*, k.nama_kategori, l.nama_lokasi 
    FROM barang b 
    LEFT JOIN kategori_barang k ON b.id_kategori = k.id_kategoribarang
    LEFT JOIN lokasi_barang l ON b.id_lokbarang = l.id_lokbarang
    WHERE b.stok <= 5 
    ORDER BY b.stok ASC 
    LIMIT 5
");
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Management Barang Bengkel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/dashboard.css">
</head>

<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar">
                <div class="p-3">
                    <h4 class="text-white"><i class="fas fa-tools me-2"></i>Bengkel</h4>
                    <hr class="text-white">

                    <nav class="nav flex-column">
                        <a class="nav-link active" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                        </a>

                        <?php if ($auth->isAdmin()): ?>
                            <a class="nav-link" href="./pages/user.php">
                                <i class="fas fa-users me-2"></i>User Management
                            </a>
                        <?php endif; ?>

                        <a class="nav-link" href="pages/supplier.php">
                            <i class="fas fa-truck me-2"></i>Supplier
                        </a>

                        <!-- <a class="nav-link" href="pages/kategori.php">
                            <i class="fas fa-tags me-2"></i>Kategori
                        </a>

                        <a class="nav-link" href="pages/lokasi.php">
                            <i class="fas fa-map-marker-alt me-2"></i>Lokasi
                        </a> -->

                        <a class="nav-link" href="./pages/barang.php">
                            <i class="fas fa-boxes me-2"></i>Data Barang
                        </a>

                        <a class="nav-link" href="./pages/barang_masuk.php">
                            <i class="fas fa-plus-circle me-2"></i>Barang Masuk
                        </a>

                        <a class="nav-link" href="./pages/barang_keluar.php">
                            <i class="fas fa-minus-circle me-2"></i>Barang Keluar
                        </a>

                        <a class="nav-link" href="pages/transaksi.php">
                            <i class="fas fa-cash-register me-2"></i>Transaksi
                        </a>

                        <hr class="text-white">

                        <a class="nav-link" href="pages/logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i>Logout
                        </a>
                    </nav>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10">
                <div class="p-4">
                    <!-- Header -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h2>Dashboard</h2>
                            <p class="text-muted">Selamat datang, <?= $auth->getUserName(); ?>
                                (<?= ucfirst($auth->getUserRole()); ?>)

                            </p>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-primary fs-6" id="clock"></span>
                        </div>

                    </div>

                    <!-- Statistics Cards -->
                    <div class="row mb-4">
                        <div class="col-md-3 mb-3">
                            <div class="card stat-card border-0 h-100">
                                <div class="card-body text-center">
                                    <i class="fas fa-boxes fa-2x mb-3"></i>
                                    <h3><?= number_format($stats['total_barang']) ?></h3>
                                    <p class="mb-0">Total Barang</p>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3 mb-3">
                            <div class="card stat-card-success border-0 h-100">
                                <div class="card-body text-center">
                                    <i class="fas fa-truck fa-2x mb-3"></i>
                                    <h3><?= number_format($stats['total_supplier']) ?></h3>
                                    <p class="mb-0">Total Supplier</p>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3 mb-3">
                            <div class="card stat-card-info border-0 h-100">
                                <div class="card-body text-center">
                                    <i class="fas fa-cash-register fa-2x mb-3"></i>
                                    <h3><?= number_format($stats['total_transaksi']) ?></h3>
                                    <p class="mb-0">Transaksi Hari Ini</p>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3 mb-3">
                            <div class="card stat-card-warning border-0 h-100">
                                <div class="card-body text-center">
                                    <i class="fas fa-exclamation-triangle fa-2x mb-3"></i>
                                    <h3><?= number_format($stats['barang_habis']) ?></h3>
                                    <p class="mb-0">Stok Menipis</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- END Statistics Cards -->

                    <div class="row">
                        <!-- Recent Transactions -->
                        <div class="col-md-6 mb-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5><i class="fas fa-history me-2"></i>Transaksi Terakhir</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($recent_transactions)): ?>
                                        <p class="text-muted text-center">Belum ada transaksi</p>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Kode</th>
                                                        <th>Tanggal</th>
                                                        <th>Total</th>
                                                        <th>Status</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($recent_transactions as $t): ?>
                                                        <tr>
                                                            <td><?= $t['kode_transaksi'] ?></td>
                                                            <td><?= date('d/m/Y', strtotime($t['tanggal_transaksi'])) ?></td>
                                                            <td>Rp <?= number_format($t['grand_total']) ?></td>
                                                            <td>
                                                                <span
                                                                    class="badge bg-<?= $t['status'] == 'completed' ? 'success' : ($t['status'] == 'pending' ? 'warning' : 'danger') ?>">
                                                                    <?= ucfirst($t['status']) ?>
                                                                </span>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Low Stock Alert -->
                        <div class="col-md-6 mb-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5><i class="fas fa-exclamation-triangle me-2"></i>Stok Menipis</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($low_stock)): ?>
                                        <p class="text-muted text-center">Semua barang stok aman</p>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Barang</th>
                                                        <th>Kategori</th>
                                                        <th>Stok</th>
                                                        <th>Status</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($low_stock as $item): ?>
                                                        <tr>
                                                            <td><?= $item['nama_barang'] ?></td>
                                                            <td><?= $item['nama_kategori'] ?></td>
                                                            <td><?= $item['stok'] ?></td>
                                                            <td>
                                                                <span
                                                                    class="badge bg-<?= $item['stok'] == 0 ? 'danger' : 'warning' ?>">
                                                                    <?= $item['stok'] == 0 ? 'Habis' : 'Menipis' ?>
                                                                </span>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/script.js"></script>
</body>

</html>