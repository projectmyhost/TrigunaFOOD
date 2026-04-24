<?php
require_once 'config.php';

// Direct update ke database
$updates = [
    "UPDATE products SET kategori = 'Minuman' WHERE LOWER(nama_produk) LIKE '%es mambo%'",
    "UPDATE products SET kategori = 'Camilan' WHERE LOWER(nama_produk) LIKE '%martabak%'",
    "UPDATE products SET kategori = 'Minuman' WHERE LOWER(nama_produk) LIKE '%es millo%'",
    "UPDATE products SET kategori = 'Minuman' WHERE LOWER(nama_produk) LIKE '%matcha%'",
    "UPDATE products SET kategori = 'Camilan' WHERE LOWER(nama_produk) LIKE '%kentang goreng%'",
    "UPDATE products SET kategori = 'Makanan' WHERE LOWER(nama_produk) LIKE '%kwetiau%'",
    "UPDATE products SET kategori = 'Camilan' WHERE LOWER(nama_produk) LIKE '%samyang%'",
    "UPDATE products SET kategori = 'Minuman' WHERE LOWER(nama_produk) LIKE '%taro%'",
    "UPDATE products SET kategori = 'Makanan' WHERE LOWER(nama_produk) LIKE '%nasi%ayam%crispy%'",
    "UPDATE products SET kategori = 'Makanan' WHERE LOWER(nama_produk) LIKE '%nasi%goreng%ayam%rica%'",
    "UPDATE products SET kategori = 'Makanan' WHERE LOWER(nama_produk) LIKE '%nasi%goreng%spesial%'",
    "UPDATE products SET kategori = 'Minuman' WHERE LOWER(nama_produk) LIKE '%lemon%tea%'",
    "UPDATE products SET kategori = 'Minuman' WHERE LOWER(nama_produk) LIKE '%kopi%butterscotch%'",
];

echo "🔄 MEMPERBARUI KATEGORI PRODUK\n";
echo "==============================\n\n";

// First show current state
echo "📋 SEBELUM UPDATE:\n";
$before = mysqli_query($conn, "SELECT kategori, COUNT(*) as count FROM products GROUP BY kategori ORDER BY kategori");
while ($row = mysqli_fetch_assoc($before)) {
    echo "   • " . ($row['kategori'] ?? 'NULL') . ": {$row['count']} produk\n";
}

// Execute updates
echo "\n🔧 MENJALANKAN UPDATE:\n";
foreach ($updates as $index => $query) {
    $result = mysqli_query($conn, $query);
    if ($result) {
        $affected = mysqli_affected_rows($conn);
        echo "   ✓ Query " . ($index + 1) . ": {$affected} baris ter-update\n";
    } else {
        echo "   ❌ Query " . ($index + 1) . ": Error - " . mysqli_error($conn) . "\n";
    }
}

// Show final state
echo "\n📋 SETELAH UPDATE:\n";
$after = mysqli_query($conn, "SELECT kategori, COUNT(*) as count FROM products GROUP BY kategori ORDER BY kategori");
while ($row = mysqli_fetch_assoc($after)) {
    echo "   • " . ($row['kategori'] ?? 'NULL') . ": {$row['count']} produk\n";
}

// Show detailed list
echo "\n📦 DETAIL PRODUK PER KATEGORI:\n";
$categories = ['Makanan', 'Minuman', 'Camilan', 'Lainnya'];
foreach ($categories as $cat) {
    $safe_cat = mysqli_real_escape_string($conn, $cat);
    $result = mysqli_query($conn, "SELECT nama_produk FROM products WHERE COALESCE(kategori, 'Lainnya') = '$safe_cat' ORDER BY nama_produk");
    $count = mysqli_num_rows($result);
    if ($count > 0) {
        echo "\n▶ {$cat} ({$count} produk):\n";
        while ($row = mysqli_fetch_assoc($result)) {
            echo "   ✓ {$row['nama_produk']}\n";
        }
    }
}

echo "\n✅ UPDATE SELESAI!\n\n";
echo "Sekarang kunjungi: http://localhost/TRIGUNAV3/home.php\n";
echo "Filter kategori seharusnya sudah menampilkan semua produk dengan benar.\n";

mysqli_close($conn);
?>
