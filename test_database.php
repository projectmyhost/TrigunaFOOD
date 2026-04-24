<?php
require_once 'config.php';

echo "🔍 TEST KONEKSI DATABASE TRIGUNAKETIGA\n";
echo "=====================================\n\n";

// Test 1: Check Database Connection
echo "1. CEK KONEKSI DATABASE\n";
if ($conn) {
    echo "   ✓ Koneksi berhasil ke database: " . $db . "\n";
} else {
    echo "   ❌ Koneksi gagal: " . mysqli_connect_error() . "\n";
    exit;
}

// Test 2: Check Tables
echo "\n2. CEK KEBERADAAN TABEL\n";
$tables = ['users', 'products', 'cart', 'orders', 'banners'];
foreach ($tables as $table) {
    $result = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
    if (mysqli_num_rows($result) > 0) {
        echo "   ✓ Tabel '$table' ada\n";
    } else {
        echo "   ❌ Tabel '$table' tidak ada\n";
    }
}

// Test 3: Insert Test User
echo "\n3. INSERT TEST USER\n";
$test_username = "admin_test";
$test_email = "admin@test.com";
$test_password = password_hash("admin123", PASSWORD_DEFAULT);

$check_user = mysqli_query($conn, "SELECT id FROM users WHERE username='$test_username' OR email='$test_email'");
if (mysqli_num_rows($check_user) > 0) {
    echo "   ⚠️  User test sudah ada, skip insert\n";
} else {
    $insert_user = "INSERT INTO users (username, email, password, role) VALUES ('$test_username', '$test_email', '$test_password', 'admin')";
    if (mysqli_query($conn, $insert_user)) {
        echo "   ✓ User test berhasil dibuat\n";
        echo "     Username: admin_test\n";
        echo "     Password: admin123\n";
        echo "     Role: admin\n";
    } else {
        echo "   ❌ Error: " . mysqli_error($conn) . "\n";
    }
}

// Test 4: Insert Test Product
echo "\n4. INSERT TEST PRODUCT\n";
$test_product_name = "Nasi Goreng Spesial";
$check_product = mysqli_query($conn, "SELECT id FROM products WHERE nama_produk='$test_product_name'");
if (mysqli_num_rows($check_product) > 0) {
    echo "   ⚠️  Produk test sudah ada, skip insert\n";
} else {
    $insert_product = "INSERT INTO products (nama_produk, harga, stok, deskripsi)
                       VALUES ('$test_product_name', 25000, 50, 'Nasi goreng dengan telur dan sayuran segar')";
    if (mysqli_query($conn, $insert_product)) {
        echo "   ✓ Produk test berhasil dibuat\n";
        echo "     Nama: $test_product_name\n";
        echo "     Harga: Rp 25.000\n";
        echo "     Stok: 50\n";
    } else {
        echo "   ❌ Error: " . mysqli_error($conn) . "\n";
    }
}

// Test 5: Check Banners
echo "\n5. CEK BANNER SLOTS\n";
$banners_result = mysqli_query($conn, "SELECT * FROM banners");
$banner_count = mysqli_num_rows($banners_result);
if ($banner_count > 0) {
    echo "   ✓ Ada $banner_count slot banner tersedia\n";
} else {
    echo "   ⚠️  Tidak ada banner slots\n";
}

// Test 6: Database Statistics
echo "\n6. STATISTIK DATABASE\n";

$user_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM users"))['total'] ?? 0;
echo "   👥 Total Users: $user_count\n";

$product_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM products"))['total'] ?? 0;
echo "   🍽️  Total Produk: $product_count\n";

$order_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM orders"))['total'] ?? 0;
echo "   📦 Total Orders: $order_count\n";

$cart_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM cart"))['total'] ?? 0;
echo "   🛒 Total Cart Items: $cart_count\n";

// Test 7: Check if uploads directories exist
echo "\n7. CEK DIREKTORI UPLOAD\n";
$upload_dirs = ['uploads', 'uploads/products', 'uploads/bukti_pembayaran'];
foreach ($upload_dirs as $dir) {
    if (is_dir($dir)) {
        echo "   ✓ Direktori '$dir' ada\n";
    } else {
        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
            echo "   ✓ Direktori '$dir' berhasil dibuat\n";
        }
    }
}

echo "\n=====================================\n";
echo "✅ SEMUA TEST SELESAI!\n";
echo "Database siap digunakan.\n";
echo "\n📌 Akses Aplikasi:\n";
echo "   URL: http://localhost/TRIGUNAV3/\n";
echo "   Login: http://localhost/TRIGUNAV3/login.php\n";
echo "   Admin: http://localhost/TRIGUNAV3/admin.php\n";
echo "\n📋 Akun Test:\n";
echo "   Username: admin_test\n";
echo "   Password: admin123\n";

mysqli_close($conn);
?>
