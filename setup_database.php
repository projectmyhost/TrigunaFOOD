<?php
// Database Setup Script untuk TrigunaFOOD
// File ini akan membuat database "trigunaketiga" dan semua tabelnya

// Connect tanpa database dulu untuk membuat database
$host = "localhost";
$user = "root";
$pass = "";

$conn = mysqli_connect($host, $user, $pass);

if (!$conn) {
    die("❌ Koneksi Gagal: " . mysqli_connect_error());
}

echo "✓ Terhubung ke MySQL Server\n";

// SQL untuk membuat database dan tabel
$sql = "
-- Create Database trigunaketiga
CREATE DATABASE IF NOT EXISTS trigunaketiga CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE trigunaketiga;

-- Table: users
CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(191) UNIQUE NOT NULL,
  email VARCHAR(191) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL,
  role VARCHAR(50) DEFAULT 'user',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_username (username),
  INDEX idx_email (email),
  INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: products
CREATE TABLE IF NOT EXISTS products (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  nama_produk VARCHAR(255) NOT NULL,
  harga DECIMAL(12,2) NOT NULL,
  stok INT UNSIGNED DEFAULT 0,
  deskripsi TEXT,
  gambar VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_nama_produk (nama_produk),
  INDEX idx_stok (stok)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: cart
CREATE TABLE IF NOT EXISTS cart (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  jumlah INT UNSIGNED NOT NULL DEFAULT 1,
  subtotal DECIMAL(12,2) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  UNIQUE KEY unique_user_product (user_id, product_id),
  INDEX idx_user_id (user_id),
  INDEX idx_product_id (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: orders
CREATE TABLE IF NOT EXISTS orders (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED DEFAULT NULL,
  username VARCHAR(191) DEFAULT NULL,
  telepon VARCHAR(50) DEFAULT NULL,
  alamat TEXT DEFAULT NULL,
  items LONGTEXT,
  total DECIMAL(12,2) DEFAULT 0,
  metode_pembayaran VARCHAR(50) DEFAULT 'COD',
  bukti_pembayaran VARCHAR(255) DEFAULT NULL,
  status VARCHAR(50) DEFAULT 'Pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_user_id (user_id),
  INDEX idx_status (status),
  INDEX idx_username (username),
  INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: banners
CREATE TABLE IF NOT EXISTS banners (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  position INT UNSIGNED NOT NULL UNIQUE,
  img VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default banner slots
INSERT IGNORE INTO banners (position) VALUES (1), (2), (3), (4);
";

// Split and execute queries
$queries = array_filter(array_map('trim', explode(';', $sql)));

foreach ($queries as $query) {
    if (!empty($query)) {
        if (mysqli_multi_query($conn, $query . ';')) {
            // Clear any results
            while (mysqli_next_result($conn)) {
                if ($result = mysqli_store_result($conn)) {
                    mysqli_free_result($result);
                }
            }
        } else {
            echo "⚠️  Query Error: " . mysqli_error($conn) . "\n";
        }
    }
}

// Now connect to the database and verify tables
$db_conn = mysqli_connect($host, $user, $pass, "trigunaketiga");

if ($db_conn) {
    echo "\n✓ Database 'trigunaketiga' berhasil dibuat/diterima\n";

    $tables = ['users', 'products', 'cart', 'orders', 'banners'];
    echo "\n📋 Status Tabel:\n";

    foreach ($tables as $table) {
        $result = mysqli_query($db_conn, "SHOW TABLES LIKE '$table'");
        if (mysqli_num_rows($result) > 0) {
            echo "  ✓ Tabel '$table' berhasil dibuat\n";
        } else {
            echo "  ❌ Tabel '$table' gagal dibuat\n";
        }
    }

    echo "\n✅ SETUP DATABASE BERHASIL!\n";
    echo "Database: trigunaketiga\n";
    echo "Host: localhost\n";
    echo "User: root\n";
    echo "Password: (kosong)\n";

    mysqli_close($db_conn);
} else {
    echo "❌ Gagal terhubung ke database trigunaketiga: " . mysqli_connect_error() . "\n";
}

mysqli_close($conn);
?>
