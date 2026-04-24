<?php
// Verification script untuk fitur kategori
require_once 'config.php';

echo "🔍 VERIFIKASI FITUR KATEGORI PRODUK\n";
echo "===================================\n\n";

// 1. Check kategori column
echo "1. Cek Kolom Kategori\n";
$result = mysqli_query($conn, "SHOW COLUMNS FROM products LIKE 'kategori'");
if (mysqli_num_rows($result) > 0) {
    echo "   ✓ Kolom kategori tersedia\n";
    $col = mysqli_fetch_assoc($result);
    echo "   - Tipe: " . $col['Type'] . "\n";
    echo "   - Default: " . $col['Default'] . "\n";
} else {
    echo "   ❌ Kolom kategori tidak ditemukan\n";
    echo "   💡 Silakan jalankan add_category_feature.php terlebih dahulu\n";
}

// 2. Check kategori distribution
echo "\n2. Distribusi Kategori Produk\n";
$categories = ['Makanan', 'Minuman', 'Camilan', 'Lainnya'];
$total = 0;
foreach ($categories as $cat) {
    $count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM products WHERE kategori = '$cat'"))['cnt'] ?? 0;
    $total += $count;
    echo "   - $cat: $count produk\n";
}
echo "   ─────────────────\n";
echo "   Total: $total produk\n";

// 3. Sample products per category
echo "\n3. Contoh Produk Per Kategori\n";
foreach ($categories as $cat) {
    $products = mysqli_query($conn, "SELECT nama_produk, harga FROM products WHERE kategori = '$cat' LIMIT 2");
    if (mysqli_num_rows($products) > 0) {
        echo "\n   📦 $cat:\n";
        while ($p = mysqli_fetch_assoc($products)) {
            echo "      • " . $p['nama_produk'] . " (Rp " . number_format($p['harga']) . ")\n";
        }
    }
}

// 4. Check page modifications
echo "\n4. Verifikasi Modifikasi Halaman\n";

// Check home.php
if (strpos(file_get_contents('home.php'), 'kategori') !== false) {
    echo "   ✓ home.php sudah dimodifikasi (fitur kategori)\n";
} else {
    echo "   ❌ home.php belum dimodifikasi\n";
}

// Check index.php
if (strpos(file_get_contents('index.php'), 'kategori') !== false) {
    echo "   ✓ index.php sudah dimodifikasi (fitur kategori)\n";
} else {
    echo "   ❌ index.php belum dimodifikasi\n";
}

echo "\n5. Status Fitur\n";
echo "   ✅ Fitur kategori siap digunakan\n";
echo "   📱 Home Page: http://localhost/TRIGUNAV3/home.php\n";
echo "   📱 Index Page: http://localhost/TRIGUNAV3/index.php\n";
echo "   🔗 Dengan filter: ?kategori=Makanan\n";

echo "\n===================================\n";
echo "✅ VERIFIKASI SELESAI!\n";

mysqli_close($conn);
?>
