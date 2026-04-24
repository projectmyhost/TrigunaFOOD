<?php
require_once 'config.php';

// Mapping produk ke kategori yang benar berdasarkan informasi user
$product_mapping = [
    'es mambo' => 'Minuman',
    'martabak telur' => 'Camilan',
    'es millo' => 'Minuman',
    'matcha latte' => 'Minuman',
    'kentang goreng' => 'Camilan',
    'kwetiau goreng' => 'Makanan',
    'samyang roll' => 'Camilan',
    'taro milk shake' => 'Minuman',
    'nasi ayam crispy jumbo' => 'Makanan',
    'nasi goreng ayam rica' => 'Makanan',
    'nasi goreng spesial' => 'Makanan',
    'es lemon tea' => 'Minuman',
    'kopi butterscotch' => 'Minuman',
];

// Get all products
$all_products = mysqli_query($conn, "SELECT id, nama_produk, kategori FROM products ORDER BY nama_produk");

$product_list = [];
while ($p = mysqli_fetch_assoc($all_products)) {
    $product_list[strtolower($p['nama_produk'])] = $p;
}

echo "📋 UPDATE KATEGORI PRODUK\n";
echo "========================\n\n";

$updated = 0;
$already_correct = 0;
$not_found = 0;

foreach ($product_mapping as $product_name => $correct_category) {
    $product_name_lower = strtolower($product_name);
    
    if (isset($product_list[$product_name_lower])) {
        $db_data = $product_list[$product_name_lower];
        $product_id = $db_data['id'];
        $current_category = $db_data['kategori'] ?? 'Lainnya';
        $display_name = $db_data['nama_produk'];
        
        if ($current_category !== $correct_category) {
            $safe_category = mysqli_real_escape_string($conn, $correct_category);
            if (mysqli_query($conn, "UPDATE products SET kategori = '$safe_category' WHERE id = $product_id")) {
                echo "✓ {$display_name}\n  {$current_category} → {$correct_category}\n\n";
                $updated++;
            }
        } else {
            echo "✓ {$display_name}\n  Kategori: {$correct_category} (Sudah Benar)\n\n";
            $already_correct++;
        }
    } else {
        echo "⚠ {$product_name}\n  TIDAK DITEMUKAN DI DATABASE\n\n";
        $not_found++;
    }
}

echo "───────────────────────────────\n";
echo "📊 RINGKASAN:\n";
echo "   ✓ Dikategorisasi: {$updated}\n";
echo "   ✓ Sudah Benar: {$already_correct}\n";
echo "   ⚠ Tidak Ditemukan: {$not_found}\n\n";

// Show final summary
echo "📦 KATEGORI FINAL:\n";
$final = mysqli_query($conn, "SELECT kategori, COUNT(*) as count FROM products GROUP BY kategori ORDER BY kategori");
while ($row = mysqli_fetch_assoc($final)) {
    $cat = $row['kategori'] ?? 'Lainnya';
    echo "   • {$cat}: {$row['count']} produk\n";
}

echo "\n🎯 DAFTAR PRODUK PER KATEGORI:\n";
$categories = ['Makanan', 'Minuman', 'Camilan', 'Lainnya'];
foreach ($categories as $cat) {
    $result = mysqli_query($conn, "SELECT nama_produk FROM products WHERE kategori = '$cat' ORDER BY nama_produk");
    $count = mysqli_num_rows($result);
    if ($count > 0) {
        echo "\n{$cat} ({$count} produk):\n";
        while ($row = mysqli_fetch_assoc($result)) {
            echo "   • {$row['nama_produk']}\n";
        }
    }
}

echo "\n✅ SELESAI!\n";
echo "Kunjungi: http://localhost/TRIGUNAV3/home.php\n";

mysqli_close($conn);
?>
