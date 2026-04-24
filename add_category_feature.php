<?php
require_once 'config.php';

echo "🔄 MENAMBAHKAN FITUR KATEGORI PRODUK\n";
echo "====================================\n\n";

// 1. Check if kategori column exists
echo "1. Mengecek kolom kategori...\n";
$result = mysqli_query($conn, "SHOW COLUMNS FROM products LIKE 'kategori'");
if (mysqli_num_rows($result) == 0) {
    // Add kategori column
    if (mysqli_query($conn, "ALTER TABLE products ADD COLUMN kategori VARCHAR(50) DEFAULT 'Lainnya' AFTER deskripsi")) {
        echo "   ✓ Kolom kategori berhasil ditambahkan\n";
    } else {
        echo "   ❌ Error: " . mysqli_error($conn) . "\n";
    }
} else {
    echo "   ✓ Kolom kategori sudah ada\n";
}

// 2. Update sample data with categories
echo "\n2. Update kategori untuk produk yang ada...\n";

// Get all products and categorize them based on name patterns
$products = mysqli_query($conn, "SELECT id, nama_produk, kategori FROM products ORDER BY id");

$makanan_keywords = ['nasi', 'mie', 'telur', 'ayam', 'daging', 'fish', 'ikan', 'beef', 'sate', 'soto', 'lumpia', 'spring'];
$minuman_keywords = ['minum', 'minuman', 'jus', 'juice', 'air', 'teh', 'kopi', 'susu', 'soda', 'lemon'];
$camilan_keywords = ['camilan', 'snack', 'gorengan', 'keripik', 'kue', 'kopi', 'tahu', 'bakso'];

$categorized = 0;
while ($p = mysqli_fetch_assoc($products)) {
    $name_lower = strtolower($p['nama_produk']);
    $category = 'Lainnya';
    
    // Check if already has a category
    if (!empty($p['kategori']) && $p['kategori'] !== 'Lainnya') {
        continue;
    }
    
    // Determine category based on keywords
    foreach ($makanan_keywords as $keyword) {
        if (strpos($name_lower, $keyword) !== false) {
            $category = 'Makanan';
            break;
        }
    }
    
    if ($category === 'Lainnya') {
        foreach ($minuman_keywords as $keyword) {
            if (strpos($name_lower, $keyword) !== false) {
                $category = 'Minuman';
                break;
            }
        }
    }
    
    if ($category === 'Lainnya') {
        foreach ($camilan_keywords as $keyword) {
            if (strpos($name_lower, $keyword) !== false) {
                $category = 'Camilan';
                break;
            }
        }
    }
    
    // Update if category changed from default
    if ($category !== 'Lainnya' && $p['kategori'] !== $category) {
        $product_name_safe = mysqli_real_escape_string($conn, $p['nama_produk']);
        if (mysqli_query($conn, "UPDATE products SET kategori = '$category' WHERE id = " . $p['id'])) {
            echo "   ✓ {$p['nama_produk']} -> $category\n";
            $categorized++;
        }
    }
}

echo "\n3. Produk yang dikategorisasi: $categorized\n";

// 4. Show final product list with categories
echo "\n4. Daftar produk dengan kategori:\n";
$final_products = mysqli_query($conn, "SELECT nama_produk, kategori, harga FROM products ORDER BY kategori, nama_produk");
$categories = [];
while ($p = mysqli_fetch_assoc($final_products)) {
    if (!isset($categories[$p['kategori']])) {
        $categories[$p['kategori']] = [];
    }
    $categories[$p['kategori']][] = $p['nama_produk'];
}

foreach ($categories as $cat => $products_list) {
    echo "\n   📦 $cat (" . count($products_list) . " produk):\n";
    foreach ($products_list as $prod) {
        echo "      - $prod\n";
    }
}

echo "\n✅ Fitur kategori berhasil ditambahkan!\n";
mysqli_close($conn);
?>
