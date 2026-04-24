<?php
require_once 'config.php';

// Get all products grouped by category
$products = mysqli_query($conn, "SELECT nama_produk, kategori FROM products ORDER BY kategori, nama_produk");

echo "📋 DAFTAR PRODUK SAAT INI\n";
echo "========================\n\n";

$categories = [];
while ($p = mysqli_fetch_assoc($products)) {
    $cat = $p['kategori'] ?? 'Lainnya';
    if (!isset($categories[$cat])) {
        $categories[$cat] = [];
    }
    $categories[$cat][] = $p['nama_produk'];
}

foreach ($categories as $cat => $products_list) {
    echo "🔹 $cat (" . count($products_list) . " produk):\n";
    foreach ($products_list as $prod) {
        echo "   - $prod\n";
    }
    echo "\n";
}

mysqli_close($conn);
?>
