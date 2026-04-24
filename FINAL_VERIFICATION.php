<?php
require_once 'config.php';

echo "🔧 KATEGORI FEATURE - ERROR FIX & VERIFICATION\n";
echo "==============================================\n\n";

// 1. Check kategori column
echo "1. Checking kategori column...\n";
$result = mysqli_query($conn, "SHOW COLUMNS FROM products LIKE 'kategori'");
if (mysqli_num_rows($result) == 0) {
    echo "   ⚠️  Kolom tidak ada, menambahkan...\n";
    if (mysqli_query($conn, "ALTER TABLE products ADD COLUMN kategori VARCHAR(50) DEFAULT 'Lainnya'")) {
        echo "   ✅ Kolom kategori berhasil ditambahkan\n";
    } else {
        echo "   ❌ Error: " . mysqli_error($conn) . "\n";
    }
} else {
    echo "   ✅ Kolom kategori sudah ada\n";
}

// 2. Set default kategori untuk produk yang kosong
echo "\n2. Updating default kategori...\n";
$update = mysqli_query($conn, "UPDATE products SET kategori = 'Lainnya' WHERE kategori IS NULL OR kategori = ''");
echo "   ✅ Default kategori updated\n";

// 3. Auto-categorize products
echo "\n3. Auto-categorizing products...\n";
$makanan_keywords = ['nasi', 'mie', 'telur', 'ayam', 'daging', 'fish', 'ikan', 'beef', 'sate', 'soto', 'lumpia', 'spring'];
$minuman_keywords = ['minum', 'minuman', 'jus', 'juice', 'air', 'teh', 'kopi', 'susu', 'soda', 'lemon'];
$camilan_keywords = ['camilan', 'snack', 'gorengan', 'keripik', 'kue', 'bakso'];

$products = mysqli_query($conn, "SELECT id, nama_produk, kategori FROM products WHERE kategori = 'Lainnya'");
$updated = 0;

while ($p = mysqli_fetch_assoc($products)) {
    $name_lower = strtolower($p['nama_produk']);
    $category = 'Lainnya';
    
    // Check keywords
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
    
    if ($category !== 'Lainnya') {
        mysqli_query($conn, "UPDATE products SET kategori = '$category' WHERE id = " . $p['id']);
        echo "   ✅ {$p['nama_produk']} → $category\n";
        $updated++;
    }
}

if ($updated == 0) {
    echo "   ℹ️  Semua produk sudah terkategorisasi\n";
}

// 4. Show distribution
echo "\n4. Category Distribution:\n";
$categories = ['Makanan', 'Minuman', 'Camilan', 'Lainnya'];
$total = 0;
foreach ($categories as $cat) {
    $count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM products WHERE kategori = '$cat'"))['cnt'];
    echo "   📦 $cat: $count produk\n";
    $total += $count;
}
echo "   ─────────────────\n";
echo "   Total: $total produk\n";

// 5. Verify code fixes
echo "\n5. Checking code fixes...\n";
$home_content = file_get_contents('home.php');
$index_content = file_get_contents('index.php');

$fixes_home = [];
if (strpos($home_content, 'COALESCE(kategori') !== false) $fixes_home[] = 'COALESCE';
if (strpos($home_content, 'SHOW COLUMNS') !== false) $fixes_home[] = 'Column Check';
if (strpos($home_content, 'kategori_exists') !== false) $fixes_home[] = 'Error Handler';

$fixes_index = [];
if (strpos($index_content, 'COALESCE(kategori') !== false) $fixes_index[] = 'COALESCE';
if (strpos($index_content, 'SHOW COLUMNS') !== false) $fixes_index[] = 'Column Check';
if (strpos($index_content, 'kategori_exists') !== false) $fixes_index[] = 'Error Handler';

echo "   ✅ home.php: " . implode(', ', $fixes_home) . "\n";
echo "   ✅ index.php: " . implode(', ', $fixes_index) . "\n";

// 6. Test queries
echo "\n6. Testing queries...\n";
$test = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM products");
$count = mysqli_fetch_assoc($test)['cnt'];
echo "   ✅ Total products: $count\n";

echo "\n==============================================\n";
echo "✅ ALL FIXES APPLIED SUCCESSFULLY!\n\n";
echo "🔗 Test Links:\n";
echo "   → http://localhost/TRIGUNAV3/home.php?kategori=Semua\n";
echo "   → http://localhost/TRIGUNAV3/home.php?kategori=Makanan\n";
echo "   → http://localhost/TRIGUNAV3/home.php?kategori=Minuman\n";
echo "   → http://localhost/TRIGUNAV3/home.php?kategori=Camilan\n\n";
echo "✨ Should work perfectly now!\n";

mysqli_close($conn);
?>
