<?php
// Koneksi database - sesuaikan dengan konfigurasi Anda
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'db_bengkel';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$database", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}

require_once '../config/database.php';

// Cek apakah parameter id ada
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo "<div class='alert alert-danger'>ID barang masuk tidak ditemukan!</div>";
    exit;
}

$id_barangmasuk = $_GET['id'];

try {
    // Query untuk mendapatkan data header barang masuk
    $stmt = $pdo->prepare("
        SELECT 
            bm.id_barangmasuk,
            bm.kode_masuk,
            bm.tanggal_masuk,
            bm.total_item,
            bm.total_harga,
            bm.keterangan,
            bm.created_at,
            s.nama_supplier,
            s.alamat as alamat_supplier,
            s.telepon as telepon_supplier,
            u.nama as nama_user
        FROM barang_masuk bm
        LEFT JOIN supplier s ON bm.id_supplier = s.id_supplier
        LEFT JOIN user u ON bm.id_user = u.id_user
        WHERE bm.id_barangmasuk = ?
    ");
    $stmt->execute([$id_barangmasuk]);
    $barang_masuk = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$barang_masuk) {
        echo "<div class='alert alert-danger'>Data barang masuk tidak ditemukan!</div>";
        exit;
    }

    // Query untuk mendapatkan detail barang masuk
    $stmt_detail = $pdo->prepare("
        SELECT 
            dbm.id_det_barangmasuk,
            dbm.jumlah,
            dbm.harga,
            dbm.subtotal,
            b.kode_barang,
            b.nama_barang,
            b.satuan,
            kb.nama_kategori,
            lb.nama_lokasi
        FROM detail_barang_masuk dbm
        JOIN barang b ON dbm.id_barang = b.id_barang
        LEFT JOIN kategori_barang kb ON b.id_kategori = kb.id_kategoribarang
        LEFT JOIN lokasi_barang lb ON b.id_lokbarang = lb.id_lokbarang
        WHERE dbm.id_barangmasuk = ?
        ORDER BY dbm.id_det_barangmasuk
    ");
    $stmt_detail->execute([$id_barangmasuk]);
    $detail_items = $stmt_detail->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Barang Masuk - <?php echo htmlspecialchars($barang_masuk['kode_masuk']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/det_brg_masuk.css">
</head>

<body>
    <div class="container-fluid">
        <!-- Header Information -->
        <div class="row mb-4">
            <div class="col-md-6">
                <h5 class="text-primary mb-3">
                    <i class="fas fa-box"></i> Detail Barang Masuk
                </h5>
                <table class="table table-sm">
                    <tr>
                        <td width="140"><strong>Kode Masuk</strong></td>
                        <td width="10">:</td>
                        <td><?= htmlspecialchars($barang_masuk['kode_masuk']) ?></td>
                    </tr>
                    <tr>
                        <td><strong>Tanggal Masuk</strong></td>
                        <td>:</td>
                        <td><?= date('d/m/Y', strtotime($barang_masuk['tanggal_masuk'])) ?></td>
                    </tr>
                    <tr>
                        <td><strong>User Input</strong></td>
                        <td>:</td>
                        <td><?= htmlspecialchars($barang_masuk['nama_user']) ?></td>
                    </tr>
                    <tr>
                        <td><strong>Total Item</strong></td>
                        <td>:</td>
                        <td><span class="badge bg-info"><?= number_format($barang_masuk['total_item']) ?> item</span>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Total Harga</strong></td>
                        <td>:</td>
                        <td><span class="text-success fw-bold">Rp
                                <?= number_format($barang_masuk['total_harga'], 0, ',', '.') ?></span></td>
                    </tr>
                    <tr>
                        <td><strong>Status</strong></td>
                        <td>:</td>
                        <td><span class="badge bg-success">Completed</span></td>
                    </tr>
                </table>
            </div>
            <div class="col-md-6">
                <h6 class="text-secondary mb-3">
                    <i class="fas fa-truck"></i> Informasi Supplier
                </h6>
                <table class="table table-sm">
                    <tr>
                        <td width="100"><strong>Supplier</strong></td>
                        <td width="10">:</td>
                        <td><?= htmlspecialchars($barang_masuk['nama_supplier'] ?? 'Tidak ada supplier') ?></td>
                    </tr>
                    <tr>
                        <td><strong>Alamat</strong></td>
                        <td>:</td>
                        <td><?= htmlspecialchars($barang_masuk['alamat_supplier'] ?? '-') ?></td>
                    </tr>
                    <tr>
                        <td><strong>Telepon</strong></td>
                        <td>:</td>
                        <td><?= htmlspecialchars($barang_masuk['telepon_supplier'] ?? '-') ?></td>
                    </tr>
                    <?php if (!empty($barang_masuk['keterangan'])): ?>
                        <tr>
                            <td><strong>Keterangan</strong></td>
                            <td>:</td>
                            <td><?= htmlspecialchars($barang_masuk['keterangan']) ?></td>
                        </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>

        <!-- Detail Items -->
        <div class="row">
            <div class="col-12">
                <h6 class="text-secondary mb-3">
                    <i class="fas fa-list"></i> Detail Barang
                </h6>

                <?php if (empty($detail_items)): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> Tidak ada detail barang untuk transaksi ini.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th width="5%">No</th>
                                    <th width="15%">Kode Barang</th>
                                    <th width="25%">Nama Barang</th>
                                    <th width="15%">Kategori</th>
                                    <th width="10%">Lokasi</th>
                                    <th width="8%">Qty</th>
                                    <th width="12%">Harga</th>
                                    <th width="15%">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $no = 1;
                                $total_qty = 0;
                                $total_subtotal = 0;
                                foreach ($detail_items as $item):
                                    $total_qty += $item['jumlah'];
                                    $total_subtotal += $item['subtotal'];
                                ?>
                                    <tr>
                                        <td><?= $no++ ?></td>
                                        <td>
                                            <span
                                                class="badge bg-secondary"><?= htmlspecialchars($item['kode_barang']) ?></span>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($item['nama_barang']) ?></strong>
                                        </td>
                                        <td>
                                            <small
                                                class="text-muted"><?= htmlspecialchars($item['nama_kategori'] ?? '-') ?></small>
                                        </td>
                                        <td>
                                            <small
                                                class="text-muted"><?= htmlspecialchars($item['nama_lokasi'] ?? '-') ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary"><?= number_format($item['jumlah']) ?>
                                                <?= htmlspecialchars($item['satuan']) ?></span>
                                        </td>
                                        <td class="text-end">
                                            Rp <?= number_format($item['harga'], 0, ',', '.') ?>
                                        </td>
                                        <td class="text-end">
                                            <strong>Rp <?= number_format($item['subtotal'], 0, ',', '.') ?></strong>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <th colspan="5" class="text-end">Total :</th>
                                    <th>
                                        <span class="badge bg-success"><?= number_format($total_qty) ?> item</span>
                                    </th>
                                    <th></th>
                                    <th class="text-end">
                                        <span class="text-success fw-bold fs-6">Rp
                                            <?= number_format($total_subtotal, 0, ',', '.') ?></span>
                                    </th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Timestamp -->
        <div class="row mt-3">
            <div class="col-12">
                <small class="text-muted">
                    <i class="fas fa-clock"></i>
                    Dibuat pada: <?= date('d/m/Y H:i:s', strtotime($barang_masuk['created_at'])) ?>
                </small>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="d-flex justify-content-between">
                    <a href="barang_masuk.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Kembali
                    </a>
                    <div>
                        <button onclick="window.print()" class="btn btn-success">
                            <i class="fas fa-print"></i> Print
                        </button>
                        <!-- <button onclick="exportTableToExcel()" class="btn btn-info">
                            <i class="fas fa-file-excel"></i> Export Excel
                        </button> -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>