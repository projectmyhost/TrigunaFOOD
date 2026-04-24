<?php
require_once 'config.php';

echo "🔧 MEMPERBAIKI KATEGORI PRODUK\n";
echo "===============================\n\n";

// Mapping produk ke kategori yang benar
$product_mapping = [
    'es mambo' => 'Minuman',
    'martabak telur' => 'Camilan',
    'es millo' => 'Minuman',
    'matcha latte' => 'Minuman',
    'kentang goreng' => 'Camilan',
    'kwetiau goreng' => 'Makanan',
    'samyang roll' => 'Camilan',
    'Taro Milk Shake' => 'Minuman',
    'Nasi ayam crispy jumbo' => 'Makanan',
    'nasi goreng ayam rica' => 'Makanan',
    'nasi goreng spesial' => 'Makanan',
    'es lemon tea' => 'Minuman',
    'kopi butterscotch' => 'Minuman',
];

// Get all products
$products = mysqli_query($conn, "SELECT id, nama_produk, kategori FROM products ORDER BY nama_produk");

echo "1. Daftar Produk di Database:\n";
$product_list = [];
while ($p = mysqli_fetch_assoc($products)) {
    $product_list[$p['nama_produk']] = $p;
    echo "   - {$p['nama_produk']} (ID: {$p['id']}) [Kategori: " . ($p['kategori'] ?? 'NULL') . "]\n";
}

echo "\n2. Update Kategori Produk:\n";
$updated = 0;
foreach ($product_mapping as $product_name => $correct_category) {
    // Find product by name (case-insensitive)
    $found = false;
    foreach ($product_list as $db_name => $db_data) {
        if (strtolower($db_name) === strtolower($product_name)) {
            $product_id = $db_data['id'];
            $current_category = $db_data['kategori'] ?? 'Lainnya';
            
            if ($current_category !== $correct_category) {
                $safe_category = mysqli_real_escape_string($conn, $correct_category);
                if (mysqli_query($conn, "UPDATE products SET kategori = '$safe_category' WHERE id = $product_id")) {
                    echo "   ✓ {$db_name}: $current_category → $correct_category\n";
                    $updated++;
                } else {
                    echo "   ❌ {$db_name}: Error - " . mysqli_error($conn) . "\n";
                }
            } else {
                echo "   ✓ {$db_name}: Sudah benar ($correct_category)\n";
            }
            $found = true;
            break;
        }
    }
    
    if (!$found) {
        echo "   ⚠️  {$product_name}: Tidak ditemukan di database\n";
    }
}

// Show final summary
echo "\n3. Ringkasan Kategori Final:\n";
$final = mysqli_query($conn, "SELECT kategori, COUNT(*) as count FROM products GROUP BY kategori ORDER BY kategori");
while ($row = mysqli_fetch_assoc($final)) {
    echo "   - {$row['kategori']}: {$row['count']} produk\n";
}

echo "\n4. Daftar Produk Per Kategori:\n";
$by_cat = mysqli_query($conn, "SELECT kategori, nama_produk FROM products ORDER BY kategori, nama_produk");
$current_cat = '';
while ($row = mysqli_fetch_assoc($by_cat)) {
    if ($current_cat !== $row['kategori']) {
        $current_cat = $row['kategori'];
        echo "\n   📦 {$current_cat}:\n";
    }
    echo "      • {$row['nama_produk']}\n";
}

echo "\n✅ Update Selesai! ($updated produk dikategorisasi ulang)\n";

mysqli_close($conn);
?>
