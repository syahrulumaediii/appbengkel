-- Database: db_bengkel
CREATE DATABASE db_bengkel;
USE db_bengkel;
DROP database db_bengkel;


-- Tabel User
CREATE TABLE user (
    id_user INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    nama VARCHAR(100) NOT NULL,
    role ENUM('admin', 'staff') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

SET FOREIGN_KEY_CHECKS = 0;
truncate TABLE user;
SET FOREIGN_KEY_CHECKS = 1;
SELECT * FROM user;
DESC user;


-- Tabel Kategori Barang
CREATE TABLE kategori_barang (
    id_kategoribarang INT AUTO_INCREMENT PRIMARY KEY,
    nama_kategori VARCHAR(100) NOT NULL,
    deskripsi TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabel Lokasi Barang
CREATE TABLE lokasi_barang (
    id_lokbarang INT AUTO_INCREMENT PRIMARY KEY,
    nama_lokasi VARCHAR(100) NOT NULL,
    deskripsi TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);


-- Tabel Supplier
CREATE TABLE supplier (
    id_supplier INT AUTO_INCREMENT PRIMARY KEY,
    kode_supplier VARCHAR(20) UNIQUE NOT NULL,
    nama_supplier VARCHAR(100) NOT NULL,
    alamat TEXT,
    telepon VARCHAR(20),
    email VARCHAR(100),
    kontak_person VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabel Barang
CREATE TABLE barang (
    id_barang INT AUTO_INCREMENT PRIMARY KEY,
    kode_barang VARCHAR(20) UNIQUE NOT NULL,
    nama_barang VARCHAR(100) NOT NULL,
    id_kategori INT,
    id_lokbarang INT,
    stok INT DEFAULT 0,
    harga_beli DECIMAL(15,2),
    harga_jual DECIMAL(15,2),
    satuan VARCHAR(20),
    status enum('aktif', 'nonaktif') default 'aktif',
    deskripsi TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_kategori) REFERENCES kategori_barang(id_kategoribarang),
    FOREIGN KEY (id_lokbarang) REFERENCES lokasi_barang(id_lokbarang)
);
ALTER TABLE barang ADD COLUMN status ENUM('aktif', 'nonaktif') DEFAULT 'aktif';

CREATE TABLE stok_history (
    id_history INT AUTO_INCREMENT PRIMARY KEY,
    id_barang INT NOT NULL,
    jenis_transaksi ENUM('masuk', 'keluar', 'adjustment_plus', 'adjustment_minus') NOT NULL,
    jumlah INT NOT NULL,
    keterangan TEXT,
    id_user INT,
    tanggal TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_barang) REFERENCES barang(id_barang),
    FOREIGN KEY (id_user) REFERENCES user(id_user)
);

-- Tabel Barang Masuk
CREATE TABLE barang_masuk (
    id_barangmasuk INT AUTO_INCREMENT PRIMARY KEY,
    kode_masuk VARCHAR(20) UNIQUE NOT NULL,
    tanggal_masuk DATE NOT NULL,
    id_supplier INT,
    id_user INT,
    total_item INT DEFAULT 0,
    total_harga DECIMAL(15,2) DEFAULT 0,
    keterangan TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_supplier) REFERENCES supplier(id_supplier),
    FOREIGN KEY (id_user) REFERENCES user(id_user)
);

-- Tabel Detail Barang Masuk
CREATE TABLE detail_barang_masuk (
    id_det_barangmasuk INT AUTO_INCREMENT PRIMARY KEY,
    id_barangmasuk INT,
    id_barang INT,
    jumlah INT NOT NULL,
    harga DECIMAL(15,2) NOT NULL,
    subtotal DECIMAL(15,2) NOT NULL,
    FOREIGN KEY (id_barangmasuk) REFERENCES barang_masuk(id_barangmasuk) ON DELETE CASCADE,
    FOREIGN KEY (id_barang) REFERENCES barang(id_barang)
);

-- Tabel Barang Keluar
CREATE TABLE barang_keluar (
    id_barangkeluar INT AUTO_INCREMENT PRIMARY KEY,
    kode_keluar VARCHAR(20) UNIQUE NOT NULL,
    tanggal_keluar DATE NOT NULL,
    id_user INT,
    total_item INT DEFAULT 0,
    total_harga DECIMAL(15,2) DEFAULT 0,
    tujuan VARCHAR(100),
    keterangan TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_user) REFERENCES user(id_user)
);

-- Tabel Detail Barang Keluar
CREATE TABLE detail_barang_keluar (
    id_det_barangkeluar INT AUTO_INCREMENT PRIMARY KEY,
    id_barangkeluar INT,
    id_barang INT,
    jumlah INT NOT NULL,
    harga DECIMAL(15,2) NOT NULL,
    subtotal DECIMAL(15,2) NOT NULL,
    FOREIGN KEY (id_barangkeluar) REFERENCES barang_keluar(id_barangkeluar) ON DELETE CASCADE,
    FOREIGN KEY (id_barang) REFERENCES barang(id_barang)
);

-- Tabel Transaksi
CREATE TABLE transaksi (
    id_transaksi INT AUTO_INCREMENT PRIMARY KEY,
    kode_transaksi VARCHAR(20) UNIQUE NOT NULL,
    tanggal_transaksi DATE NOT NULL,
    id_user INT,
    customer_name VARCHAR(100),
    total_item INT DEFAULT 0,
    total_harga DECIMAL(15,2) DEFAULT 0,
    diskon DECIMAL(15,2) DEFAULT 0,
    pajak DECIMAL(15,2) DEFAULT 0,
    grand_total DECIMAL(15,2) DEFAULT 0,
    bayar DECIMAL(15,2) DEFAULT 0,
    kembalian DECIMAL(15,2) DEFAULT 0,
    status ENUM('pending', 'completed', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_user) REFERENCES user(id_user)
);

SET FOREIGN_KEY_CHECKS = 0;
truncate TABLE detail_transaksi;
SET FOREIGN_KEY_CHECKS = 1;
SELECT * FROM transaksi;

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


-- Tabel Detail Transaksi
CREATE TABLE detail_transaksi (
    id_det_transaksi INT AUTO_INCREMENT PRIMARY KEY,
    id_transaksi INT,
    id_barang INT,
    jumlah INT NOT NULL,
    harga DECIMAL(15,2) NOT NULL,
    subtotal DECIMAL(15,2) NOT NULL,
    FOREIGN KEY (id_transaksi) REFERENCES transaksi(id_transaksi) ON DELETE CASCADE,
    FOREIGN KEY (id_barang) REFERENCES barang(id_barang)
);

-- Insert Data Dummy
-- Data User
INSERT INTO user (username, password, nama, role) VALUES
('syahrul', md5('admin123'), 'Syahrul Umaedi', 'admin'),
('ika', md5('staff123'), 'Ika Marlena', 'staff');


-- Data Kategori Barang
INSERT INTO kategori_barang (nama_kategori, deskripsi) VALUES
('Spare Part Mesin', 'Komponen mesin kendaraan'),
('Oli & Pelumas', 'Oli mesin dan pelumas'),
('Ban & Velg', 'Ban dan velg kendaraan'),
('Aksesoris', 'Aksesoris kendaraan'),
('Tools', 'Peralatan bengkel');

-- Data Lokasi Barang
INSERT INTO lokasi_barang (nama_lokasi, deskripsi) VALUES
('Gudang A', 'Gudang utama spare part'),
('Gudang B', 'Gudang oli dan pelumas'),
('Rak Display', 'Rak display toko'),
('Workshop', 'Area workshop bengkel');

-- Data Supplier
INSERT INTO supplier (kode_supplier, nama_supplier, alamat, telepon, email, kontak_person) VALUES
('SUP001', 'PT. Sumber Rejeki', 'Jl. Industri No. 123, Jakarta', '021-1234567', 'info@sumberrejeki.com', 'Budi Santoso'),
('SUP002', 'CV. Maju Bersama', 'Jl. Raya Bogor No. 456, Bogor', '0251-987654', 'sales@majubersama.com', 'Siti Nurhaliza'),
('SUP003', 'Toko Berkah Motor', 'Jl. Pasar Minggu No. 789, Jakarta', '021-5555666', 'berkahmotor@gmail.com', 'Ahmad Yani');

-- Data Barang
INSERT INTO barang (kode_barang, nama_barang, id_kategori, id_lokbarang, stok, harga_beli, harga_jual, satuan, deskripsi) VALUES
('BRG001', 'Filter Oli Mobil', 1, 1, 50, 25000, 35000, 'pcs', 'Filter oli untuk mobil sedan'),
('BRG002', 'Oli Mesin 10W-40', 2, 2, 30, 45000, 65000, 'liter', 'Oli mesin synthetic blend'),
('BRG003', 'Ban Mobil 185/65 R14', 3, 1, 20, 350000, 450000, 'pcs', 'Ban mobil ring 14'),
('BRG004', 'Kampas Rem Depan', 1, 1, 25, 80000, 120000, 'set', 'Kampas rem depan mobil'),
('BRG005', 'Kunci Ring Set', 5, 4, 10, 150000, 200000, 'set', 'Kunci ring set 8-24mm'); 

SELECT kode_barang FROM barang WHERE kode_barang = 'BRG006';

SELECT * FROM user;

desc stok_history;
show tables;
SELECT * FROM barang;
SELECT * FROM lokasi_barang;
--


--  QUERY - QUERY YANG DI PAKAI

-- DI FORM LOGIN AMBIL USER
SELECT * FROM user WHERE username =?;  
