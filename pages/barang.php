<?php
require_once '../config/database.php';
require_once '../includes/auth.php';


$auth = new Auth();
checkAuth();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'add':
            // Cek duplikasi kode_barang
            $cek = $db->fetch("SELECT id_barang FROM barang WHERE kode_barang = ?", [$_POST['kode_barang']]);
            if ($cek) {
                $error = "Kode barang sudah digunakan!";
                break;
            }

            // QUERY ADD BARANG KE DATABASE
            $stmt = $db->query(
                "INSERT INTO barang (
            kode_barang, nama_barang, id_kategori, id_lokbarang, 
            satuan, harga_beli, harga_jual, stok, deskripsi
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $_POST['kode_barang'],
                    $_POST['nama_barang'],
                    $_POST['id_kategori'],
                    $_POST['id_lokbarang'],
                    $_POST['satuan'],
                    $_POST['harga_beli'],
                    $_POST['harga_jual'],
                    $_POST['stok'],
                    $_POST['deskripsi']
                ]
            );

            if ($stmt) {
                $barang_id = $db->lastInsertId();
                $db->query(
                    "INSERT INTO stok_history (id_barang, jenis_transaksi, jumlah, keterangan, id_user) 
                    VALUES (?, 'masuk', ?, 'Stok awal barang baru', ?)",
                    [$barang_id, $_POST['stok'], $_SESSION['user']['id_user']]
                );

                $message = "Barang berhasil ditambahkan!";
            } else {
                $error = "Gagal menambahkan barang.";
            }

            break;

        case 'edit':
            // STOK SAAT INI UNTUNK RIWAYAT STOK
            $current_barang = $db->fetch("SELECT stok FROM barang WHERE id_barang = ?", [$_POST['id_barang']]);
            $old_stock = $current_barang['stok'];
            $new_stock = $_POST['stok'];

            // QUERY UPDATE BARANG
            $stmt = $db->query(
                "UPDATE barang SET 
                    nama_barang = ?, id_kategori = ?, id_lokbarang = ?, 
                    satuan = ?, harga_beli = ?, harga_jual = ?, 
                    stok = ?, deskripsi = ? 
                WHERE id_barang = ?",
                [
                    $_POST['nama_barang'],
                    $_POST['id_kategori'],
                    $_POST['id_lokbarang'],
                    $_POST['satuan'],
                    $_POST['harga_beli'],
                    $_POST['harga_jual'],
                    $_POST['stok'],
                    $_POST['deskripsi'],
                    $_POST['id_barang']
                ]
            );

            // Insert stock adjustment history if stock changed

            if ($old_stock != $new_stock) {
                $adjustment_type = ($new_stock > $old_stock) ? 'adjustment_plus' : 'adjustment_minus';
                $adjustment_qty = abs($new_stock - $old_stock);

                $db->query(
                    "INSERT INTO stok_history (id_barang, jenis_transaksi, jumlah, keterangan, id_user) 
                    VALUES (?, ?, ?, 'Adjustment stok manual', ?)",
                    [$_POST['id_barang'], $adjustment_type, $adjustment_qty, $_SESSION['user']['id_user']]
                );
            }

            // header("Location: barang.php?");
            $message = "Barang berhasil diupdate!";
            break;

        case 'delete':
            // Hapus data terkait
            $db->query("DELETE FROM stok_history WHERE id_barang = ?", [$_POST['id_barang']]);
            $db->query("DELETE FROM detail_barang_masuk WHERE id_barang = ?", [$_POST['id_barang']]);
            $db->query("DELETE FROM detail_barang_keluar WHERE id_barang = ?", [$_POST['id_barang']]);
            $db->query("DELETE FROM detail_transaksi WHERE id_barang = ?", [$_POST['id_barang']]);

            // Baru hapus barang
            $stmt = $db->query("DELETE FROM barang WHERE id_barang = ?", [$_POST['id_barang']]);
            $message = "Barang dan data terkait berhasil dihapus!";
            break;
    }
}


// Build filter conditions
$kondisi = [];
$params = [];

// Search by nama dan kode barang
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = '%' . $_GET['search'] . '%';
    $kondisi[] = "(b.nama_barang LIKE ? OR b.kode_barang LIKE ?)";
    $params[] = $search;
    $params[] = $search;
}

// Filter by kategori barang
if (isset($_GET['kategori']) && !empty($_GET['kategori'])) {
    $kondisi[] = "b.id_kategori = ?";
    $params[] = $_GET['kategori'];
}

// Filter by lokasi penyimpanan barang
if (isset($_GET['lokasi']) && !empty($_GET['lokasi'])) {
    $kondisi[] = "b.id_lokbarang = ?";
    $params[] = $_GET['lokasi'];
}

// Filter by stock status barang
if (isset($_GET['stok_status']) && !empty($_GET['stok_status'])) {
    switch ($_GET['stok_status']) {
        case 'rendah':
            $kondisi[] = "b.stok <= 10";
            break;
        case 'kosong':
            $kondisi[] = "b.stok = 0";
            break;
        case 'tersedia':
            $kondisi[] = "b.stok > 10";
            break;
    }
}

// Filter by rentang harga barang
if (isset($_GET['harga_min']) && !empty($_GET['harga_min'])) {
    $kondisi[] = "b.harga_jual >= ?";
    $params[] = $_GET['harga_min'];
}

if (isset($_GET['harga_max']) && !empty($_GET['harga_max'])) {
    $kondisi[] = "b.harga_jual <= ?";
    $params[] = $_GET['harga_max'];
}

// QUERY UNTUK MENAMPILKAN DATA BARANG
$query = "
    SELECT b.*, 
           k.nama_kategori, 
           l.nama_lokasi
    FROM barang b
    LEFT JOIN kategori_barang k ON b.id_kategori = k.id_kategoribarang
    LEFT JOIN lokasi_barang l ON b.id_lokbarang = l.id_lokbarang
";

if (!empty($kondisi)) {
    $query .= " WHERE " . implode(" AND ", $kondisi);
}

// Order by
$order_by = isset($_GET['sort']) ? $_GET['sort'] : 'nama_barang';
$order_dir = isset($_GET['order']) && $_GET['order'] === 'desc' ? 'DESC' : 'ASC';

switch ($order_by) {
    case 'kode':
        $query .= " ORDER BY b.kode_barang " . $order_dir;
        break;
    case 'kategori':
        $query .= " ORDER BY k.nama_kategori " . $order_dir;
        break;
    case 'lokasi':
        $query .= " ORDER BY l.nama_lokasi " . $order_dir;
        break;
    case 'stok':
        $query .= " ORDER BY b.stok " . $order_dir;
        break;
    case 'harga_beli':
        $query .= " ORDER BY b.harga_beli " . $order_dir;
        break;
    case 'harga_jual':
        $query .= " ORDER BY b.harga_jual " . $order_dir;
        break;
    default:
        $query .= " ORDER BY b.nama_barang " . $order_dir;
}


$barang_list = $db->fetchAll($query, $params);

// Dropdown kategori dan lokasi
$kategori_list = $db->fetchAll("SELECT id_kategoribarang, nama_kategori FROM kategori_barang ORDER BY nama_kategori");
$lokasi_list = $db->fetchAll("SELECT id_lokbarang, nama_lokasi FROM lokasi_barang ORDER BY nama_lokasi");
$supplier_list = $db->fetchAll("SELECT id_supplier, nama_supplier FROM supplier ORDER BY nama_supplier");

// Ambil data barang untuk diedit
$edit_barang = null;
if (isset($_GET['edit'])) {
    $edit_barang = $db->fetch("SELECT * FROM barang WHERE id_barang = ?", [$_GET['edit']]);
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Barang - Workshop Inventory</title>
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
                        <a class="nav-link text-warning" href="../pages/barang.php">
                            <i class="fas fa-boxes"></i> Barang
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="../pages/supplier.php">
                            <i class="fas fa-truck"></i> Supplier
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="../pages/stok.php">
                            <i class="fas fa-warehouse"></i> Stok
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="../pages/transaksi.php">
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

            <!-- Main Content -->
            <div class="col-md-10 p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-boxes"></i> Manajemen Barang</h2>
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

                <!-- Form Add/Edit Barang -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5><?php echo $edit_barang ? 'Edit Barang' : 'Tambah Barang Baru'; ?></h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="<?php echo $edit_barang ? 'edit' : 'add'; ?>">
                            <?php if ($edit_barang): ?>
                                <input type="hidden" name="id_barang" value="<?php echo $edit_barang['id_barang']; ?>">
                            <?php endif; ?>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="kode_barang" class="form-label">Kode Barang</label>
                                        <input type="text" class="form-control" id="kode_barang" name="kode_barang"
                                            value="<?php echo $edit_barang ? $edit_barang['kode_barang'] : ''; ?>"
                                            <?php echo $edit_barang ? 'readonly' : 'required'; ?>>
                                    </div>
                                    <div class="mb-3">
                                        <label for="nama_barang" class="form-label">Nama Barang</label>
                                        <input type="text" class="form-control" id="nama_barang" name="nama_barang"
                                            value="<?php echo $edit_barang ? $edit_barang['nama_barang'] : ''; ?>"
                                            required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="id_kategori" class="form-label">Kategori</label>
                                        <select class="form-control" id="id_kategori" name="id_kategori" required>
                                            <option value="">Pilih Kategori</option>
                                            <?php foreach ($kategori_list as $kategori): ?>
                                                <option value="<?= $kategori['id_kategoribarang']; ?>"
                                                    <?= ($edit_barang && $edit_barang['id_kategori'] == $kategori['id_kategoribarang']) ? 'selected' : ''; ?>>
                                                    <?= $kategori['nama_kategori']; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label for="id_lokbarang" class="form-label">Lokasi</label>
                                        <select class="form-control" id="id_lokbarang" name="id_lokbarang" required>
                                            <option value="">Pilih Lokasi</option>
                                            <?php foreach ($lokasi_list as $lokasi): ?>
                                                <option value="<?= $lokasi['id_lokbarang']; ?>"
                                                    <?= ($edit_barang && $edit_barang['id_lokbarang'] == $lokasi['id_lokbarang']) ? 'selected' : ''; ?>>
                                                    <?= $lokasi['nama_lokasi']; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label for="satuan" class="form-label">Satuan</label>
                                        <select class="form-control" id="satuan" name="satuan" required>
                                            <option value="">Pilih Satuan</option>
                                            <option value="pcs"
                                                <?php echo ($edit_barang && $edit_barang['satuan'] == 'pcs') ? 'selected' : ''; ?>>
                                                Pcs</option>
                                            <option value="set"
                                                <?php echo ($edit_barang && $edit_barang['satuan'] == 'set') ? 'selected' : ''; ?>>
                                                Set</option>
                                            <option value="liter"
                                                <?php echo ($edit_barang && $edit_barang['satuan'] == 'liter') ? 'selected' : ''; ?>>
                                                Liter</option>
                                            <option value="kg"
                                                <?php echo ($edit_barang && $edit_barang['satuan'] == 'kg') ? 'selected' : ''; ?>>
                                                Kg</option>
                                            <option value="meter"
                                                <?php echo ($edit_barang && $edit_barang['satuan'] == 'meter') ? 'selected' : ''; ?>>
                                                Meter</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="harga_beli" class="form-label">Harga Beli</label>
                                        <input type="number" step="0.01" class="form-control" id="harga_beli"
                                            name="harga_beli"
                                            value="<?php echo $edit_barang ? $edit_barang['harga_beli'] : ''; ?>"
                                            required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="harga_jual" class="form-label">Harga Jual</label>
                                        <input type="number" step="0.01" class="form-control" id="harga_jual"
                                            name="harga_jual"
                                            value="<?php echo $edit_barang ? $edit_barang['harga_jual'] : ''; ?>"
                                            required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="stok" class="form-label">Stok</label>
                                        <input type="number" class="form-control" id="stok" name="stok"
                                            value="<?php echo $edit_barang ? $edit_barang['stok'] : '0'; ?>" required>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="deskripsi" class="form-label">Deskripsi</label>
                                <textarea class="form-control" id="deskripsi" name="deskripsi"
                                    rows="3"><?php echo $edit_barang ? $edit_barang['deskripsi'] : ''; ?></textarea>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> <?php echo $edit_barang ? 'Update' : 'Simpan'; ?>
                                </button>
                                <?php if ($edit_barang): ?>
                                    <a href="barang.php" class="btn btn-secondary">
                                        <i class="fas fa-times"></i> Batal
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Filter dan Search -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-filter"></i> Filter & Pencarian Barang</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <!-- Keep edit parameter if exists -->
                            <?php if (isset($_GET['edit'])): ?>
                                <input type="hidden" name="edit" value="<?php echo $_GET['edit']; ?>">
                            <?php endif; ?>

                            <div class="col-md-3">
                                <label for="search" class="form-label">Cari Nama/Kode</label>
                                <input type="text" class="form-control" id="search" name="search"
                                    placeholder="Nama atau kode barang..."
                                    value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                            </div>

                            <div class="col-md-2">
                                <label for="kategori" class="form-label">Kategori</label>
                                <select class="form-control" id="kategori" name="kategori">
                                    <option value="">Semua Kategori</option>
                                    <?php foreach ($kategori_list as $kategori): ?>
                                        <option value="<?= $kategori['id_kategoribarang']; ?>"
                                            <?= (isset($_GET['kategori']) && $_GET['kategori'] == $kategori['id_kategoribarang']) ? 'selected' : ''; ?>>
                                            <?= $kategori['nama_kategori']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-2">
                                <label for="lokasi" class="form-label">Lokasi</label>
                                <select class="form-control" id="lokasi" name="lokasi">
                                    <option value="">Semua Lokasi</option>
                                    <?php foreach ($lokasi_list as $lokasi): ?>
                                        <option value="<?= $lokasi['id_lokbarang']; ?>"
                                            <?= (isset($_GET['lokasi']) && $_GET['lokasi'] == $lokasi['id_lokbarang']) ? 'selected' : ''; ?>>
                                            <?= $lokasi['nama_lokasi']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-2">
                                <label for="stok_status" class="form-label">Status Stok</label>
                                <select class="form-control" id="stok_status" name="stok_status">
                                    <option value="">Semua Status</option>
                                    <option value="kosong"
                                        <?= (isset($_GET['stok_status']) && $_GET['stok_status'] == 'kosong') ? 'selected' : ''; ?>>
                                        Kosong (0)</option>
                                    <option value="rendah"
                                        <?= (isset($_GET['stok_status']) && $_GET['stok_status'] == 'rendah') ? 'selected' : ''; ?>>
                                        Rendah (≤10)</option>
                                    <option value="tersedia"
                                        <?= (isset($_GET['stok_status']) && $_GET['stok_status'] == 'tersedia') ? 'selected' : ''; ?>>
                                        Tersedia (>10)</option>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Range Harga Jual</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" name="harga_min" placeholder="Min"
                                        value="<?php echo isset($_GET['harga_min']) ? $_GET['harga_min'] : ''; ?>">
                                    <span class="input-group-text">-</span>
                                    <input type="number" class="form-control" name="harga_max" placeholder="Max"
                                        value="<?php echo isset($_GET['harga_max']) ? $_GET['harga_max'] : ''; ?>">
                                </div>
                            </div>

                            <div class="col-md-12">
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search"></i> Cari
                                    </button>
                                    <a href="barang.php" class="btn btn-secondary">
                                        <i class="fas fa-refresh"></i> Reset
                                    </a>
                                    <div class="ms-auto">
                                        <div class="btn-group">
                                            <button type="button" class="btn btn-outline-secondary dropdown-toggle"
                                                data-bs-toggle="dropdown">
                                                <i class="fas fa-sort"></i> Urutkan
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li><a class="dropdown-item"
                                                        href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'nama_barang', 'order' => 'asc'])); ?>">Nama
                                                        A-Z</a></li>
                                                <li><a class="dropdown-item"
                                                        href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'nama_barang', 'order' => 'desc'])); ?>">Nama
                                                        Z-A</a></li>
                                                <li><a class="dropdown-item"
                                                        href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'kode', 'order' => 'asc'])); ?>">Kode
                                                        A-Z</a></li>
                                                <li><a class="dropdown-item"
                                                        href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'kategori', 'order' => 'asc'])); ?>">Kategori</a>
                                                </li>
                                                <li><a class="dropdown-item"
                                                        href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'stok', 'order' => 'asc'])); ?>">Stok
                                                        Terendah</a></li>
                                                <li><a class="dropdown-item"
                                                        href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'stok', 'order' => 'desc'])); ?>">Stok
                                                        Tertinggi</a></li>
                                                <li><a class="dropdown-item"
                                                        href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'harga_jual', 'order' => 'asc'])); ?>">Harga
                                                        Termurah</a></li>
                                                <li><a class="dropdown-item"
                                                        href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'harga_jual', 'order' => 'desc'])); ?>">Harga
                                                        Termahal</a></li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h5>Total Barang</h5>
                                        <h3><?php echo count($barang_list); ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-boxes fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h5>Total Stok</h5>
                                        <h3><?php echo array_sum(array_column($barang_list, 'stok')); ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-warehouse fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h5>Stok Rendah</h5>
                                        <h3><?php echo count(array_filter($barang_list, function ($item) {
                                                return $item['stok'] <= 10;
                                            })); ?>
                                        </h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-exclamation-triangle fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h5>Kategori</h5>
                                        <h3><?php echo count($kategori_list); ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-tags fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Daftar Barang -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5>Daftar Barang</h5>
                        <div>
                            <small class="text-muted">
                                Menampilkan <?php echo count($barang_list); ?> barang
                                <?php if (!empty($kondisi)): ?>
                                    dari hasil pencarian
                                <?php endif; ?>
                            </small>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($barang_list)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-search fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">Tidak ada barang ditemukan</h5>
                                <p class="text-muted">Coba ubah kriteria pencarian atau filter Anda</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>No</th>
                                            <th>
                                                <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'kode', 'order' => (isset($_GET['sort']) && $_GET['sort'] == 'kode' && $_GET['order'] == 'asc') ? 'desc' : 'asc'])); ?>"
                                                    class="text-white text-decoration-none">
                                                    Kode
                                                    <?php if (isset($_GET['sort']) && $_GET['sort'] == 'kode'): ?>
                                                        <i
                                                            class="fas fa-sort-<?php echo $_GET['order'] == 'desc' ? 'down' : 'up'; ?>"></i>
                                                    <?php endif; ?>
                                                </a>
                                            </th>
                                            <th>
                                                <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'nama_barang', 'order' => (isset($_GET['sort']) && $_GET['sort'] == 'nama_barang' && $_GET['order'] == 'asc') ? 'desc' : 'asc'])); ?>"
                                                    class="text-white text-decoration-none">
                                                    Nama Barang
                                                    <?php if (!isset($_GET['sort']) || $_GET['sort'] == 'nama_barang'): ?>
                                                        <i
                                                            class="fas fa-sort-<?php echo (isset($_GET['order']) && $_GET['order'] == 'desc') ? 'down' : 'up'; ?>"></i>
                                                    <?php endif; ?>
                                                </a>
                                            </th>
                                            <th>
                                                <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'kategori', 'order' => (isset($_GET['sort']) && $_GET['sort'] == 'kategori' && $_GET['order'] == 'asc') ? 'desc' : 'asc'])); ?>"
                                                    class="text-white text-decoration-none">
                                                    Kategori
                                                    <?php if (isset($_GET['sort']) && $_GET['sort'] == 'kategori'): ?>
                                                        <i
                                                            class="fas fa-sort-<?php echo $_GET['order'] == 'desc' ? 'down' : 'up'; ?>"></i>
                                                    <?php endif; ?>
                                                </a>
                                            </th>
                                            <th>
                                                <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'lokasi', 'order' => (isset($_GET['sort']) && $_GET['sort'] == 'lokasi' && $_GET['order'] == 'asc') ? 'desc' : 'asc'])); ?>"
                                                    class="text-white text-decoration-none">
                                                    Lokasi
                                                    <?php if (isset($_GET['sort']) && $_GET['sort'] == 'lokasi'): ?>
                                                        <i
                                                            class="fas fa-sort-<?php echo $_GET['order'] == 'desc' ? 'down' : 'up'; ?>"></i>
                                                    <?php endif; ?>
                                                </a>
                                            </th>
                                            <th>Satuan</th>
                                            <th>
                                                <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'harga_beli', 'order' => (isset($_GET['sort']) && $_GET['sort'] == 'harga_beli' && $_GET['order'] == 'asc') ? 'desc' : 'asc'])); ?>"
                                                    class="text-white text-decoration-none">
                                                    Harga Beli
                                                    <?php if (isset($_GET['sort']) && $_GET['sort'] == 'harga_beli'): ?>
                                                        <i
                                                            class="fas fa-sort-<?php echo $_GET['order'] == 'desc' ? 'down' : 'up'; ?>"></i>
                                                    <?php endif; ?>
                                                </a>
                                            </th>
                                            <th>
                                                <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'harga_jual', 'order' => (isset($_GET['sort']) && $_GET['sort'] == 'harga_jual' && $_GET['order'] == 'asc') ? 'desc' : 'asc'])); ?>"
                                                    class="text-white text-decoration-none">
                                                    Harga Jual
                                                    <?php if (isset($_GET['sort']) && $_GET['sort'] == 'harga_jual'): ?>
                                                        <i
                                                            class="fas fa-sort-<?php echo $_GET['order'] == 'desc' ? 'down' : 'up'; ?>"></i>
                                                    <?php endif; ?>
                                                </a>
                                            </th>
                                            <th>
                                                <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'stok', 'order' => (isset($_GET['sort']) && $_GET['sort'] == 'stok' && $_GET['order'] == 'asc') ? 'desc' : 'asc'])); ?>"
                                                    class="text-white text-decoration-none">
                                                    Stok
                                                    <?php if (isset($_GET['sort']) && $_GET['sort'] == 'stok'): ?>
                                                        <i
                                                            class="fas fa-sort-<?php echo $_GET['order'] == 'desc' ? 'down' : 'up'; ?>"></i>
                                                    <?php endif; ?>
                                                </a>
                                            </th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($barang_list as $index => $barang): ?>
                                            <tr>
                                                <td><?php echo $index + 1; ?></td>
                                                <td>
                                                    <code><?php echo $barang['kode_barang']; ?></code>
                                                </td>
                                                <td>
                                                    <strong><?php echo $barang['nama_barang']; ?></strong>
                                                    <?php if (!empty($barang['deskripsi'])): ?>
                                                        <br><small
                                                            class="text-muted"><?php echo substr($barang['deskripsi'], 0, 50) . (strlen($barang['deskripsi']) > 50 ? '...' : ''); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span
                                                        class="badge bg-secondary"><?php echo $barang['nama_kategori'] ?: 'N/A'; ?></span>
                                                </td>
                                                <td>
                                                    <span
                                                        class="badge bg-info"><?php echo $barang['nama_lokasi'] ?: 'N/A'; ?></span>
                                                </td>
                                                <td><?php echo $barang['satuan']; ?></td>
                                                <td>
                                                    <small class="text-muted">Rp</small>
                                                    <?php echo number_format($barang['harga_beli'], 0, ',', '.'); ?>
                                                </td>
                                                <td>
                                                    <strong>Rp
                                                        <?php echo number_format($barang['harga_jual'], 0, ',', '.'); ?></strong>
                                                </td>
                                                <td>
                                                    <?php
                                                    $stok_class = 'bg-success';
                                                    $stok_icon = 'fas fa-check-circle';
                                                    if ($barang['stok'] == 0) {
                                                        $stok_class = 'bg-danger';
                                                        $stok_icon = 'fas fa-times-circle';
                                                    } elseif ($barang['stok'] <= 10) {
                                                        $stok_class = 'bg-warning';
                                                        $stok_icon = 'fas fa-exclamation-triangle';
                                                    }
                                                    ?>
                                                    <span class="badge <?php echo $stok_class; ?>">
                                                        <i class="<?php echo $stok_icon; ?>"></i>
                                                        <?php echo $barang['stok']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="?edit=<?php echo $barang['id_barang']; ?>&<?php echo http_build_query($_GET); ?>"
                                                            class="btn btn-warning" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <button type="button" class="btn btn-info" title="Detail"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#detailModal<?php echo $barang['id_barang']; ?>">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <form method="POST" style="display: inline;"
                                                            onsubmit="return confirm('Yakin hapus barang <?php echo $barang['nama_barang']; ?>?')">
                                                            <input type="hidden" name="action" value="delete">
                                                            <input type="hidden" name="id_barang"
                                                                value="<?php echo $barang['id_barang']; ?>">
                                                            <button type="submit" class="btn btn-danger" title="Hapus">
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
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Detail Barang -->
                <?php foreach ($barang_list as $barang): ?>
                    <div class="modal fade" id="detailModal<?php echo $barang['id_barang']; ?>" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Detail Barang</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <table class="table table-borderless">
                                        <tr>
                                            <th width="40%">Kode Barang:</th>
                                            <td><code><?php echo $barang['kode_barang']; ?></code></td>
                                        </tr>
                                        <tr>
                                            <th>Nama Barang:</th>
                                            <td><strong><?php echo $barang['nama_barang']; ?></strong></td>
                                        </tr>
                                        <tr>
                                            <th>Kategori:</th>
                                            <td><?php echo $barang['nama_kategori'] ?: 'N/A'; ?></td>
                                        </tr>
                                        <tr>
                                            <th>Lokasi:</th>
                                            <td><?php echo $barang['nama_lokasi'] ?: 'N/A'; ?></td>
                                        </tr>
                                        <tr>
                                            <th>Satuan:</th>
                                            <td><?php echo $barang['satuan']; ?></td>
                                        </tr>
                                        <tr>
                                            <th>Harga Beli:</th>
                                            <td>Rp <?php echo number_format($barang['harga_beli'], 0, ',', '.'); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Harga Jual:</th>
                                            <td><strong>Rp
                                                    <?php echo number_format($barang['harga_jual'], 0, ',', '.'); ?></strong>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Stok:</th>
                                            <td>
                                                <span
                                                    class="badge <?php echo ($barang['stok'] <= 10) ? 'bg-danger' : 'bg-success'; ?>">
                                                    <?php echo $barang['stok']; ?> <?php echo $barang['satuan']; ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>Profit Margin:</th>
                                            <td>
                                                <?php
                                                $margin = (($barang['harga_jual'] - $barang['harga_beli']) / $barang['harga_beli']) * 100;
                                                echo number_format($margin, 1) . '%';
                                                ?>
                                            </td>
                                        </tr>
                                        <?php if (!empty($barang['deskripsi'])): ?>
                                            <tr>
                                                <th>Deskripsi:</th>
                                                <td><?php echo nl2br($barang['deskripsi']); ?></td>
                                            </tr>
                                        <?php endif; ?>
                                        <tr>
                                            <th>Dibuat:</th>
                                            <td><?php echo date('d/m/Y H:i', strtotime($barang['created_at'])); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Diupdate:</th>
                                            <td><?php echo date('d/m/Y H:i', strtotime($barang['updated_at'])); ?></td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                                    <a href="?edit=<?php echo $barang['id_barang']; ?>" class="btn btn-warning">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div><?php endforeach; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>