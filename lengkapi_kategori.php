<?php
/**
 * SCRIPT LENGKAP: Lengkapi dan Sinkronisasi Kategori Produk
 * 
 * Script ini akan:
 * 1. Memastikan kolom kategori ada
 * 2. Update kategori untuk semua produk sesuai dengan daftar user
 * 3. Menampilkan hasil kategorisasi
 */

require_once 'config.php';

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║        🔧 SINKRONISASI KATEGORI PRODUK - TRIGUNAFOOD          ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

// Step 1: Pastikan kolom kategori ada
echo "STEP 1: Verifikasi Kolom Kategori\n";
echo "─────────────────────────────────────\n";

$check_col = mysqli_query($conn, "SHOW COLUMNS FROM products LIKE 'kategori'");
if (mysqli_num_rows($check_col) === 0) {
    echo "⚠ Kolom kategori tidak ditemukan, membuat...\n";
    if (mysqli_query($conn, "ALTER TABLE products ADD COLUMN kategori VARCHAR(50) DEFAULT 'Lainnya'")) {
        echo "✓ Kolom kategori berhasil ditambahkan\n\n";
    } else {
        echo "❌ Error menambahkan kolom: " . mysqli_error($conn) . "\n";
        exit;
    }
} else {
    echo "✓ Kolom kategori sudah ada\n\n";
}

// Step 2: Update kategori menggunakan mapping lengkap
echo "STEP 2: Update Kategori Produk\n";
echo "─────────────────────────────────────\n";

$mappings = [
    // Minuman (7 produk)
    ['es mambo', 'Minuman'],
    ['es millo', 'Minuman'],
    ['matcha latte', 'Minuman'],
    ['Taro Milk Shake', 'Minuman'],
    ['es lemon tea', 'Minuman'],
    ['kopi butterscotch', 'Minuman'],
    
    // Makanan (5 produk)
    ['kwetiau goreng', 'Makanan'],
    ['Nasi ayam crispy jumbo', 'Makanan'],
    ['nasi goreng ayam rica', 'Makanan'],
    ['nasi goreng spesial', 'Makanan'],
    
    // Camilan (3 produk)
    ['martabak telur', 'Camilan'],
    ['kentang goreng', 'Camilan'],
    ['samyang roll', 'Camilan'],
];

$updated = 0;
$total = 0;

foreach ($mappings as $map) {
    $product_name = $map[0];
    $correct_cat = $map[1];
    $total++;
    
    // Update menggunakan LIKE untuk fuzzy matching
    $query = "UPDATE products SET kategori = '" . mysqli_real_escape_string($conn, $correct_cat) . "' 
              WHERE LOWER(nama_produk) LIKE '%" . strtolower($product_name) . "%'";
    
    if (mysqli_query($conn, $query)) {
        $affected = mysqli_affected_rows($conn);
        if ($affected > 0) {
            echo "✓ " . str_pad($product_name, 30) . " → " . $correct_cat . " ({$affected} row)\n";
            $updated += $affected;
        }
    }
}

echo "\n───────────────────────────────────────\n";
echo "Total produk diproses: {$total}\n";
echo "Total baris ter-update: {$updated}\n\n";

// Step 3: Set default kategori untuk produk tanpa kategori
echo "STEP 3: Set Kategori Default\n";
echo "─────────────────────────────────────\n";

$default_query = "UPDATE products SET kategori = 'Lainnya' WHERE kategori IS NULL OR kategori = ''";
if (mysqli_query($conn, $default_query)) {
    $default_affected = mysqli_affected_rows($conn);
    if ($default_affected > 0) {
        echo "✓ {$default_affected} produk set ke kategori 'Lainnya'\n\n";
    } else {
        echo "✓ Semua produk sudah punya kategori\n\n";
    }
} else {
    echo "⚠ Error: " . mysqli_error($conn) . "\n\n";
}

// Step 4: Tampilkan hasil final
echo "STEP 4: Hasil Kategorisasi Final\n";
echo "─────────────────────────────────────\n";

$categories = ['Makanan', 'Minuman', 'Camilan', 'Lainnya'];
$total_all = 0;

foreach ($categories as $cat) {
    $safe_cat = mysqli_real_escape_string($conn, $cat);
    $result = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM products WHERE kategori = '$safe_cat'");
    $count = mysqli_fetch_assoc($result)['cnt'];
    $total_all += $count;
    
    $emoji = [
        'Makanan' => '🍽️',
        'Minuman' => '🥤',
        'Camilan' => '🍿',
        'Lainnya' => '📦'
    ];
    
    echo "  " . $emoji[$cat] . " " . str_pad($cat, 12) . ": " . str_pad($count, 3, ' ', STR_PAD_LEFT) . " produk\n";
}

echo "  " . str_repeat("─", 30) . "\n";
echo "  📊 Total                   : {$total_all} produk\n\n";

// Step 5: Tampilkan detail per kategori
echo "STEP 5: Detail Produk Per Kategori\n";
echo "─────────────────────────────────────\n";

foreach ($categories as $cat) {
    $safe_cat = mysqli_real_escape_string($conn, $cat);
    $products = mysqli_query($conn, "SELECT nama_produk FROM products WHERE kategori = '$safe_cat' ORDER BY nama_produk");
    $count = mysqli_num_rows($products);
    
    if ($count > 0) {
        $emoji = [
            'Makanan' => '🍽️',
            'Minuman' => '🥤',
            'Camilan' => '🍿',
            'Lainnya' => '📦'
        ];
        
        echo "\n{$emoji[$cat]} {$cat} ({$count} produk):\n";
        while ($row = mysqli_fetch_assoc($products)) {
            echo "   ✓ " . $row['nama_produk'] . "\n";
        }
    }
}

echo "\n╔════════════════════════════════════════════════════════════════╗\n";
echo "║                    ✅ SINKRONISASI SELESAI!                     ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

echo "📝 NEXT STEPS:\n";
echo "   1. Buka: http://localhost/TRIGUNAV3/home.php\n";
echo "   2. Lihat filter kategori: Semua, Makanan, Minuman, Camilan\n";
echo "   3. Filter sekarang sudah lengkap dengan semua produk!\n\n";

echo "✨ Filter kategori Anda sekarang:\n";
echo "   🔖 Semua    → Semua {$total_all} produk\n";
$makanan = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM products WHERE kategori = 'Makanan'"))['cnt'];
$minuman = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM products WHERE kategori = 'Minuman'"))['cnt'];
$camilan = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM products WHERE kategori = 'Camilan'"))['cnt'];
echo "   🍽️ Makanan  → {$makanan} produk\n";
echo "   🥤 Minuman  → {$minuman} produk\n";
echo "   🍿 Camilan  → {$camilan} produk\n";

mysqli_close($conn);
?>
