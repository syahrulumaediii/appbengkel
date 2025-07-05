// START SUPPLIER //
function editSupplier(supplier) {
    document.getElementById('form-action').value = 'edit';
    document.getElementById('id_supplier').value = supplier.id_supplier;
    document.getElementById('kode_supplier').value = supplier.kode_supplier;
    document.getElementById('nama_supplier').value = supplier.nama_supplier;
    document.getElementById('alamat').value = supplier.alamat;
    document.getElementById('telepon').value = supplier.telepon;
    document.getElementById('email').value = supplier.email;
    document.getElementById('kontak_person').value = supplier.kontak_person;
    document.getElementById('submit-btn').innerText = 'Update Supplier';
}

function deleteSupplier(id, nama) {
    if (confirm(`Yakin ingin menghapus supplier "${nama}"?`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id_supplier" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// END SUPPLIER //



// START DASHBOARD //
/**
 * Fungsi untuk memperbarui jam pada halaman dashboard
 **/
function updateClock() {
        const now = new Date();
        const options = { 
            day: '2-digit', 
            month: 'long', 
            year: 'numeric' 
        };
        const date = now.toLocaleDateString('id-ID', options);
        const time = now.toLocaleTimeString('id-ID', { hour12: false });
        document.getElementById('clock').textContent = `${date} - ${time}`;
    }

    // Jalankan saat pertama kali
    updateClock();

    // Perbarui setiap detik
    setInterval(updateClock, 1000);

// END DASHBOARD //


// START USER //
/*
function EDIT DAN DELETE USER
*/

    function editUser(user) {
        document.getElementById('edit_id').value = user.id;
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

// END USER //



// START STOK //

function showCurrentStock(select) {
    const selectedOption = select.options[select.selectedIndex];
    const currentStock = selectedOption.dataset.stok || '';
    document.getElementById('stok_sekarang').value = currentStock;
}

// END STOK //


// START LAPORAN TRANSAKSI//
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
// END LAPORAN TRANSAKSI//


//  START DETAIL BARANG MASUK //



// END DETAIL BARANG MASUK //