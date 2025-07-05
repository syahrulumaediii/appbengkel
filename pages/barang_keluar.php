<?php
session_start();
require_once '../config/database.php';


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
            try {
                // Generate kode keluar otomatis
                $query_kode = "SELECT MAX(CAST(SUBSTRING(kode_keluar, 3) AS UNSIGNED)) as max_kode FROM barang_keluar WHERE kode_keluar LIKE 'BK%'";
                $result_kode = $db->fetch($query_kode);
                $next_number = ($result_kode['max_kode'] ?? 0) + 1;
                $kode_keluar = 'BK' . str_pad($next_number, 4, '0', STR_PAD_LEFT);

                // Insert barang keluar
                $tanggal_keluar = $_POST['tanggal_keluar'];
                $tujuan = $_POST['tujuan'];
                $keterangan = $_POST['keterangan'];

                // Validasi stok terlebih dahulu
                $error_stok = false;
                $error_messages = [];

                if (isset($_POST['id_barang']) && is_array($_POST['id_barang'])) {
                    for ($i = 0; $i < count($_POST['id_barang']); $i++) {
                        $id_barang = $_POST['id_barang'][$i];
                        $jumlah = $_POST['jumlah'][$i];

                        // Cek stok tersedia
                        $barang = $db->fetch("SELECT nama_barang, stok FROM barang WHERE id_barang = ?", [$id_barang]);

                        if ($barang['stok'] < $jumlah) {
                            $error_stok = true;
                            $error_messages[] = "Stok {$barang['nama_barang']} tidak mencukupi. Stok tersedia: {$barang['stok']}, diminta: $jumlah";
                        }
                    }
                }

                if ($error_stok) {
                    $_SESSION['message'] = implode('<br>', $error_messages);
                    $_SESSION['message_type'] = "error";
                    break;
                }

                // Insert barang keluar
                $query = "INSERT INTO barang_keluar (kode_keluar, tanggal_keluar, id_user, tujuan, keterangan) 
                        VALUES (?, ?, ?, ?, ?)";
                $db->query($query, [$kode_keluar, $tanggal_keluar, $id_user, $tujuan, $keterangan]);
                $id_barangkeluar = $db->lastInsertId();

                // Insert detail barang keluar
                $total_item = 0;
                $total_harga = 0;

                if (isset($_POST['id_barang']) && is_array($_POST['id_barang'])) {
                    for ($i = 0; $i < count($_POST['id_barang']); $i++) {
                        $id_barang = $_POST['id_barang'][$i];
                        $jumlah = $_POST['jumlah'][$i];
                        $harga = $_POST['harga'][$i];
                        $subtotal = $jumlah * $harga;

                        // Insert detail
                        $query_detail = "INSERT INTO detail_barang_keluar (id_barangkeluar, id_barang, jumlah, harga, subtotal) 
                                    VALUES (?, ?, ?, ?, ?)";
                        $db->query($query_detail, [$id_barangkeluar, $id_barang, $jumlah, $harga, $subtotal]);

                        // Update stok barang (kurangi)
                        $query_update_stok = "UPDATE barang SET stok = stok - ? WHERE id_barang = ?";
                        $db->query($query_update_stok, [$jumlah, $id_barang]);

                        // Insert stok history
                        $query_history = "INSERT INTO stok_history (id_barang, jenis_transaksi, jumlah, keterangan, id_user) 
                                        VALUES (?, 'keluar', ?, ?, ?)";
                        $db->query($query_history, [$id_barang, $jumlah, "Barang keluar: $kode_keluar", $id_user]);

                        $total_item += $jumlah;
                        $total_harga += $subtotal;
                    }
                }

                // Update total
                $query_update = "UPDATE barang_keluar SET total_item = ?, total_harga = ? WHERE id_barangkeluar = ?";
                $db->query($query_update, [$total_item, $total_harga, $id_barangkeluar]);

                $_SESSION['message'] = "Barang keluar berhasil ditambahkan!";
                $_SESSION['message_type'] = "success";
            } catch (Exception $e) {
                $_SESSION['message'] = "Error: " . $e->getMessage();
                $_SESSION['message_type'] = "error";
            }
            break;

        case 'delete':
            try {
                $id = $_POST['id'];

                // Get detail barang keluar untuk update stok
                $details = $db->fetchAll("SELECT * FROM detail_barang_keluar WHERE id_barangkeluar = ?", [$id]);

                foreach ($details as $detail) {
                    // Update stok (tambah karena dihapus)
                    $query_update_stok = "UPDATE barang SET stok = stok + ? WHERE id_barang = ?";
                    $db->query($query_update_stok, [$detail['jumlah'], $detail['id_barang']]);
                }

                // Delete barang keluar (detail akan terhapus otomatis karena ON DELETE CASCADE)
                $query = "DELETE FROM barang_keluar WHERE id_barangkeluar = ?";
                $db->query($query, [$id]);

                $_SESSION['message'] = "Barang keluar berhasil dihapus!";
                $_SESSION['message_type'] = "success";
            } catch (Exception $e) {
                $_SESSION['message'] = "Error: " . $e->getMessage();
                $_SESSION['message_type'] = "error";
            }
            break;
    }
    header("Location: barang_keluar.php");
    exit();
}

// Get data barang keluar
$barang_keluar_list = $db->fetchAll("SELECT bk.*, u.nama as nama_user 
    FROM barang_keluar bk 
    LEFT JOIN user u ON bk.id_user = u.id_user 
    ORDER BY bk.tanggal_keluar DESC");

// Get barang with stock for dropdown
$barang_list = $db->fetchAll("SELECT * FROM barang WHERE status = 'aktif' AND stok > 0 ORDER BY nama_barang");
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barang Keluar - Sistem Inventory Bengkel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css//inout_barang.css">
</head>

<body class="bg-light">
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header">
                        <h4 class="mb-0"><i class="fas fa-arrow-up me-2"></i>Barang Keluar</h4>
                    </div>
                    <div class="card-body">
                        <?php if (isset($_SESSION['message'])): ?>
                            <div class="alert alert-<?php echo $_SESSION['message_type']; ?> alert-dismissible fade show"
                                role="alert">
                                <?php echo $_SESSION['message']; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                            <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
                        <?php endif; ?>

                        <!-- Button trigger modal -->
                        <button type="button" class="btn btn-primary mb-3" data-bs-toggle="modal"
                            data-bs-target="#addModal">
                            <i class="fas fa-plus me-2"></i>Tambah Barang Keluar
                        </button>

                        <!-- Table -->
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>No</th>
                                        <th>Kode Keluar</th>
                                        <th>Tanggal</th>
                                        <th>Tujuan</th>
                                        <th>Total Item</th>
                                        <th>Total Harga</th>
                                        <th>User</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $no = 1;
                                    foreach ($barang_keluar_list as $row): ?>
                                        <tr>
                                            <td><?php echo $no++; ?></td>
                                            <td><?php echo htmlspecialchars($row['kode_keluar']); ?></td>
                                            <td><?php echo date('d/m/Y', strtotime($row['tanggal_keluar'])); ?></td>
                                            <td><?php echo htmlspecialchars($row['tujuan']); ?></td>
                                            <td><?php echo number_format($row['total_item']); ?></td>
                                            <td>Rp <?php echo number_format($row['total_harga'], 0, ',', '.'); ?></td>
                                            <td><?php echo htmlspecialchars($row['nama_user']); ?></td>
                                            <td>
                                                <button class="btn btn-info btn-sm"
                                                    onclick="viewDetail(<?php echo $row['id_barangkeluar']; ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn btn-danger btn-sm"
                                                    onclick="deleteItem(<?php echo $row['id_barangkeluar']; ?>)">
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

    <!-- Add Modal -->
    <div class="modal fade" id="addModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Barang Keluar</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Tanggal Keluar</label>
                                    <input type="date" class="form-control" name="tanggal_keluar" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Tujuan</label>
                                    <input type="text" class="form-control" name="tujuan"
                                        placeholder="Misal: Workshop, Customer, dll" required>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Keterangan</label>
                            <textarea class="form-control" name="keterangan" rows="2"
                                placeholder="Keterangan tambahan (opsional)"></textarea>
                        </div>

                        <hr>
                        <h6>Detail Barang</h6>
                        <div id="barangContainer">
                            <div class="row barang-row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Barang</label>
                                        <select class="form-select barang-select" name="id_barang[]"
                                            onchange="updateStock(this)" required>
                                            <option value="">Pilih Barang</option>
                                            <?php foreach ($barang_list as $barang): ?>
                                                <option value="<?php echo $barang['id_barang']; ?>"
                                                    data-stock="<?php echo $barang['stok']; ?>"
                                                    data-harga="<?php echo $barang['harga_jual']; ?>">
                                                    <?php echo htmlspecialchars($barang['nama_barang']); ?> (Stok:
                                                    <?php echo $barang['stok']; ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="stock-info mt-1"></div>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="mb-3">
                                        <label class="form-label">Jumlah</label>
                                        <input type="number" class="form-control jumlah-input" name="jumlah[]" min="1"
                                            onchange="validateStock(this)" required>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="mb-3">
                                        <label class="form-label">Harga</label>
                                        <input type="number" class="form-control harga-input" name="harga[]" min="0"
                                            required>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="mb-3">
                                        <label class="form-label">Subtotal</label>
                                        <input type="number" class="form-control subtotal-input" readonly>
                                    </div>
                                </div>
                                <div class="col-md-1">
                                    <div class="mb-3">
                                        <label class="form-label">&nbsp;</label>
                                        <button type="button" class="btn btn-success form-control"
                                            onclick="addBarangRow()">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <hr>
                        <div class="row">
                            <div class="col-md-6">
                                <strong>Total Item: <span id="totalItem">0</span></strong>
                            </div>
                            <div class="col-md-6 text-end">
                                <strong>Total Harga: Rp <span id="totalHarga">0</span></strong>
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

    <!-- Detail Modal -->
    <div class="modal fade" id="detailModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detail Barang Keluar</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="detailContent">
                    <!-- Content will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let barangData = {};

        // Load barang data
        <?php
        echo "barangData = {";
        foreach ($barang_list as $barang) {
            echo $barang['id_barang'] . ": {";
            echo "nama: '" . addslashes($barang['nama_barang']) . "',";
            echo "stok: " . $barang['stok'] . ",";
            echo "harga: " . $barang['harga_jual'];
            echo "},";
        }
        echo "};";
        ?>

        function addBarangRow() {
            const container = document.getElementById('barangContainer');
            const row = document.querySelector('.barang-row').cloneNode(true);

            // Reset values
            row.querySelectorAll('input, select').forEach(input => {
                input.value = '';
                if (input.classList.contains('subtotal-input')) {
                    input.value = 0;
                }
            });

            // Clear stock info
            row.querySelector('.stock-info').innerHTML = '';

            // Change button to remove
            const button = row.querySelector('button');
            button.className = 'btn btn-danger form-control';
            button.innerHTML = '<i class="fas fa-minus"></i>';
            button.onclick = function() {
                this.closest('.barang-row').remove();
                calculateTotal();
            };

            container.appendChild(row);
        }

        function updateStock(selectElement) {
            const row = selectElement.closest('.barang-row');
            const stockInfo = row.querySelector('.stock-info');
            const hargaInput = row.querySelector('.harga-input');
            const jumlahInput = row.querySelector('.jumlah-input');

            if (selectElement.value) {
                const selectedOption = selectElement.selectedOptions[0];
                const stock = selectedOption.dataset.stock;
                const harga = selectedOption.dataset.harga;

                stockInfo.innerHTML = `<small class="text-info">Stok tersedia: ${stock}</small>`;
                hargaInput.value = harga;
                jumlahInput.max = stock;

                // Reset jumlah if exceeds stock
                if (parseInt(jumlahInput.value) > parseInt(stock)) {
                    jumlahInput.value = '';
                }
            } else {
                stockInfo.innerHTML = '';
                hargaInput.value = '';
                jumlahInput.max = '';
            }

            calculateSubtotal(row);
        }

        function validateStock(input) {
            const row = input.closest('.barang-row');
            const select = row.querySelector('.barang-select');

            if (select.value) {
                const selectedOption = select.selectedOptions[0];
                const stock = parseInt(selectedOption.dataset.stock);
                const jumlah = parseInt(input.value);

                if (jumlah > stock) {
                    alert(`Jumlah tidak boleh melebihi stok yang tersedia (${stock})`);
                    input.value = stock;
                }
            }

            calculateSubtotal(row);
        }

        function calculateSubtotal(row) {
            const jumlahInput = row.querySelector('.jumlah-input');
            const hargaInput = row.querySelector('.harga-input');
            const subtotalInput = row.querySelector('.subtotal-input');

            const jumlah = parseInt(jumlahInput.value) || 0;
            const harga = parseInt(hargaInput.value) || 0;
            const subtotal = jumlah * harga;

            subtotalInput.value = subtotal;
            calculateTotal();
        }

        function calculateTotal() {
            let totalItem = 0;
            let totalHarga = 0;

            document.querySelectorAll('.barang-row').forEach(row => {
                const jumlah = parseInt(row.querySelector('.jumlah-input').value) || 0;
                const subtotal = parseInt(row.querySelector('.subtotal-input').value) || 0;

                totalItem += jumlah;
                totalHarga += subtotal;
            });

            document.getElementById('totalItem').textContent = totalItem;
            document.getElementById('totalHarga').textContent = totalHarga.toLocaleString('id-ID');
        }

        // Add event listeners for calculation
        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('jumlah-input') || e.target.classList.contains('harga-input')) {
                calculateSubtotal(e.target.closest('.barang-row'));
            }
        });

        function deleteItem(id) {
            if (confirm('Apakah Anda yakin ingin menghapus barang keluar ini?')) {
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
            fetch(`detail_barang_keluar.php?id=${id}`)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('detailContent').innerHTML = data;
                    new bootstrap.Modal(document.getElementById('detailModal')).show();
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Terjadi kesalahan saat memuat detail');
                });
        }

        // Set default date to today
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            document.querySelector('input[name="tanggal_keluar"]').value = today;
        });
    </script>
</body>

</html>