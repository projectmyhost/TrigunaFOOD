<?php
session_start();

// Set timezone ke Indonesia (WIB - Waktu Indonesia Barat)
// Khusus untuk InfinityFree - Paksa menggunakan timezone Indonesia
date_default_timezone_set('Asia/Jakarta');
putenv('TZ=Asia/Jakarta');

// Fungsi untuk menampilkan tanggal dan jam Indonesia - Forcefully Override untuk InfinityFree
function getCurrentDateTimeIndonesia() {
    // Cara 1: Coba dengan DateTime Object dengan timezone explicit
    try {
        $dt = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
        $timestamp = $dt->getTimestamp();
    } catch (Exception $e) {
        // Fallback: Jika DateTime gagal, gunakan cara manual dengan offset
        $timestamp = time() + (7 * 3600); // Tambah 7 jam untuk WIB
    }
    
    // Cara 2: Gunakan gmdate dengan offset manual jika diperlukan
    // Dapatkan waktu server saat ini
    $server_time = time();
    
    // Buat DateTime untuk membandingkan
    $utc = new DateTime('now', new DateTimeZone('UTC'));
    $utc_timestamp = $utc->getTimestamp();
    
    // Hitung offset dan tambahkan 7 jam untuk WIB
    $wib_timestamp = $utc_timestamp + (7 * 3600);
    
    // Convert ke format yang bisa dibaca
    $tanggal = intval(gmdate('d', $wib_timestamp));
    $bulan = intval(gmdate('m', $wib_timestamp));
    $tahun = gmdate('Y', $wib_timestamp);
    $jam = gmdate('H', $wib_timestamp);
    $menit = gmdate('i', $wib_timestamp);
    $detik = gmdate('s', $wib_timestamp);
    $hari = intval(gmdate('N', $wib_timestamp));
    
    return array(
        'tanggal' => $tanggal,
        'bulan' => $bulan,
        'tahun' => $tahun,
        'jam' => $jam,
        'menit' => $menit,
        'detik' => $detik,
        'hari_nama' => getHariIndonesia($hari),
        'bulan_nama' => getBulanIndonesia($bulan),
        'datetime_lengkap' => $tanggal . ' ' . getBulanIndonesia($bulan) . ' ' . $tahun . ' ' . $jam . ':' . $menit . ':' . $detik,
        'datetime_format_indo' => getHariIndonesia($hari) . ', ' . $tanggal . ' ' . getBulanIndonesia($bulan) . ' ' . $tahun . ' ' . $jam . ':' . $menit . ':' . $detik,
        'timestamp' => $wib_timestamp
    );
}

// Fungsi untuk mendapatkan nama hari dalam bahasa Indonesia
function getHariIndonesia($hari) {
    $hari_array = array(
        1 => 'Senin',
        2 => 'Selasa',
        3 => 'Rabu',
        4 => 'Kamis',
        5 => 'Jumat',
        6 => 'Sabtu',
        7 => 'Minggu'
    );
    return isset($hari_array[$hari]) ? $hari_array[$hari] : 'Tidak diketahui';
}

// Fungsi untuk mendapatkan nama bulan dalam bahasa Indonesia
function getBulanIndonesia($bulan) {
    $bulan_array = array(
        1 => 'Januari',
        2 => 'Februari',
        3 => 'Maret',
        4 => 'April',
        5 => 'Mei',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'Agustus',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember'
    );
    return isset($bulan_array[$bulan]) ? $bulan_array[$bulan] : 'Tidak diketahui';
}

require_once 'config.php';

// Deteksi status login
$is_logged = false;
$user_id = null;
if (isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id'])) {
    $user_id = intval($_SESSION['user_id']);
    $is_logged = true;
} elseif (isset($_SESSION['username'])) {
    $username_safe = mysqli_real_escape_string($conn, $_SESSION['username']);
    $u = mysqli_query($conn, "SELECT id FROM users WHERE username = '$username_safe' LIMIT 1");
    if ($u && mysqli_num_rows($u) > 0) {
        $user_row = mysqli_fetch_assoc($u);
        $user_id = intval($user_row['id']);
        $is_logged = true;
    }
}

// Logic Add to Cart (Hanya proses jika login)
if (isset($_POST['add_to_cart']) && $is_logged) {
    $p_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    $qty = isset($_POST['qty']) ? intval($_POST['qty']) : 0;

    $prodRes = mysqli_query($conn, "SELECT harga, stok FROM products WHERE id = $p_id LIMIT 1");
    if ($prodRes && mysqli_num_rows($prodRes) > 0) {
        $prod = mysqli_fetch_assoc($prodRes);
        $price = floatval($prod['harga']);
        $stock = intval($prod['stok']);

        $check = mysqli_query($conn, "SELECT jumlah FROM cart WHERE user_id = '$user_id' AND product_id = '$p_id'");
        $existingQty = ($check && mysqli_num_rows($check) > 0) ? intval(mysqli_fetch_assoc($check)['jumlah']) : 0;

        if (($existingQty + $qty) <= $stock) {
            $subtotal = $qty * $price;
            if ($existingQty > 0) {
                mysqli_query($conn, "UPDATE cart SET jumlah = jumlah + $qty, subtotal = subtotal + $subtotal WHERE user_id = '$user_id' AND product_id = '$p_id'");
            } else {
                mysqli_query($conn, "INSERT INTO cart (user_id, product_id, jumlah, subtotal) VALUES ('$user_id', '$p_id', '$qty', '$subtotal')");
            }
            header("Location: index.php?show_cart=1");
            exit();
        }
    }
}

// Ambil data keranjang jika login
$cart_count = 0;
$cart_items = null;
if ($is_logged) {
    $cart_items = mysqli_query($conn, "SELECT cart.*, products.nama_produk, products.harga, products.stok, products.gambar FROM cart JOIN products ON cart.product_id = products.id WHERE cart.user_id = '$user_id'");
    $cart_count = $cart_items ? mysqli_num_rows($cart_items) : 0;
}

// Ambil data user profile
$user_profile = null;
$user_profile_pic = null;
$username_display = '';

if ($is_logged) {
    $user_profile = mysqli_query($conn, "SELECT username, foto_profil FROM users WHERE id = '$user_id' LIMIT 1");
    $user_profile = $user_profile ? mysqli_fetch_assoc($user_profile) : null;
    $user_profile_pic = $user_profile['foto_profil'] ?? null;
    $username_display = htmlspecialchars($user_profile['username'] ?? ($_SESSION['username'] ?? ''));
} else {
    $username_display = '';
}

function getProfilePictureUrl($foto_profil) {
    if ($foto_profil && file_exists("uploads/profiles/$foto_profil")) {
        return "uploads/profiles/$foto_profil";
    }
    return "https://commons.wikimedia.org/wiki/Special:FilePath/Profile_avatar_placeholder_large.png";
}

$profile_pic_url = getProfilePictureUrl($user_profile_pic);

// Banners
$banners = [];
for ($i = 1; $i <= 4; $i++) {
    $res = mysqli_query($conn, "SELECT * FROM banners WHERE position = $i LIMIT 1");
    $banners[$i] = mysqli_fetch_assoc($res);
}

// Get selected category from URL or default to all
$selected_category = isset($_GET['kategori']) ? htmlspecialchars($_GET['kategori']) : 'Semua';

// Auto-sync: Pastikan semua produk punya kategori
$check_null = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM products WHERE kategori IS NULL OR kategori = ''");
$null_count = mysqli_fetch_assoc($check_null)['cnt'] ?? 0;
if ($null_count > 0) {
    mysqli_query($conn, "UPDATE products SET kategori = 'Lainnya' WHERE kategori IS NULL OR kategori = ''");
}

// ==========================================
// WISHLIST LOGIC
// ==========================================

// Auto-create wishlist table
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS wishlist (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_wishlist (user_id, product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// AJAX: Toggle Wishlist (requires login)
if (isset($_POST['ajax_toggle_wishlist']) && $_POST['ajax_toggle_wishlist'] == '1') {
    $response = ['status' => 'error', 'message' => 'Login required'];
    
    if ($is_logged && $user_id) {
        $wl_product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        if ($wl_product_id > 0) {
            $check_wl = mysqli_query($conn, "SELECT id FROM wishlist WHERE user_id = '$user_id' AND product_id = '$wl_product_id'");
            if ($check_wl && mysqli_num_rows($check_wl) > 0) {
                mysqli_query($conn, "DELETE FROM wishlist WHERE user_id = '$user_id' AND product_id = '$wl_product_id'");
                $response = ['status' => 'success', 'action' => 'removed'];
            } else {
                mysqli_query($conn, "INSERT INTO wishlist (user_id, product_id) VALUES ('$user_id', '$wl_product_id')");
                $response = ['status' => 'success', 'action' => 'added'];
            }
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// Get user's wishlist items
$wishlist_items = [];
if ($is_logged && $user_id) {
    $wl_result = mysqli_query($conn, "SELECT product_id FROM wishlist WHERE user_id = '$user_id'");
    if ($wl_result) {
        while ($wl_row = mysqli_fetch_assoc($wl_result)) {
            $wishlist_items[] = intval($wl_row['product_id']);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TrigunaFOOD - Delivery Spesial</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="icon" href="https://i.imgur.com/o6FBULN.jpeg">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        .cart-drawer {
            transform: translateX(100%);
            transition: transform 0.45s cubic-bezier(0.32, 0.72, 0, 1);
            will-change: transform;
            backface-visibility: hidden;
            -webkit-backface-visibility: hidden;
        }

        .cart-drawer.active {
            transform: translateX(0);
        }

        .mobile-nav {
            transform: translateX(100%);
            transition: transform 0.45s cubic-bezier(0.32, 0.72, 0, 1);
            will-change: transform;
            backface-visibility: hidden;
            -webkit-backface-visibility: hidden;
        }

        .mobile-nav.active {
            transform: translateX(0);
        }

        .mobile-menu-profile {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #10b981;
            display: block;
            margin: 0 auto 12px auto;
        }

        /* Enhanced Banner Slider */
        #banner-wrapper {
            display: flex;
            width: 100%;
            height: 100%;
            transition: transform 0.5s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            will-change: transform;
            cursor: grab;
        }
        #banner-wrapper:active {
            cursor: grabbing;
        }
        .banner-slide {
            display: none;
        }


        .banner-active {
            display: block;
            animation: fadeIn 0.8s ease-in-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        .modal-enter {
            animation: modalScale 0.3s ease-out;
        }

        @keyframes modalScale {
            from {
                transform: scale(0.9);
                opacity: 0;
            }

            to {
                transform: scale(1);
                opacity: 1;
            }
        }

        /* Mobile Category Filter Buttons - Horizontal Scroll */
        @media (max-width: 768px) {
            .category-filter-container {
                display: flex;
                overflow-x: auto;
                gap: 0.75rem;
                padding-bottom: 0.5rem;
                -webkit-overflow-scrolling: touch;
                scroll-behavior: smooth;
            }
            .category-filter-container::-webkit-scrollbar {
                height: 4px;
            }
            .category-filter-container::-webkit-scrollbar-track {
                background: transparent;
            }
            .category-filter-container::-webkit-scrollbar-thumb {
                background: transparent;
                border-radius: 2px;
            }
        }

        /* ========================================
           SCROLL REVEAL ANIMATION
           ======================================== */
        .scroll-reveal {
            opacity: 0;
            transform: translateY(40px);
        }
        .scroll-reveal.visible {
            animation: scrollRevealIn 0.8s cubic-bezier(0.16, 1, 0.3, 1) both;
        }
        .scroll-reveal.hidden-out {
            animation: scrollRevealOut 0.5s ease both;
        }
        @keyframes scrollRevealIn {
            from { opacity: 0; transform: translateY(40px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes scrollRevealOut {
            from { opacity: 1; transform: translateY(0); }
            to { opacity: 0; transform: translateY(20px); }
        }

        /* ========================================
           CHECKOUT CONFIRMATION MODAL
           ======================================== */
        .checkout-modal-overlay {
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            animation: checkoutOverlayIn 0.3s ease;
        }
        .checkout-modal-content {
            animation: checkoutPopIn 0.45s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        @keyframes checkoutOverlayIn {
            from { opacity: 0; } to { opacity: 1; }
        }
        @keyframes checkoutPopIn {
            from { opacity: 0; transform: scale(0.85) translateY(20px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }
        @keyframes checkoutPopOut {
            from { opacity: 1; transform: scale(1); }
            to { opacity: 0; transform: scale(0.85) translateY(20px); }
        }
        .checkout-pulse {
            animation: checkoutPulse 2s infinite;
        }
        @keyframes checkoutPulse {
            0%, 100% { box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3); }
            50% { box-shadow: 0 4px 30px rgba(16, 185, 129, 0.6); }
        }
        .sparkle {
            position: absolute;
            border-radius: 50%;
            animation: sparkleFloat 1.5s ease-out forwards;
            pointer-events: none;
        }
        @keyframes sparkleFloat {
            0% { opacity: 1; transform: scale(0) translateY(0); }
            50% { opacity: 1; transform: scale(1) translateY(-20px); }
            100% { opacity: 0; transform: scale(0.5) translateY(-40px); }
        }

        /* ========================================
           WISHLIST HEART BUTTON
           ======================================== */
        .wishlist-btn {
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        .wishlist-btn:hover {
            transform: scale(1.2);
        }
        .wishlist-btn:active {
            transform: scale(0.9);
        }
        .wishlist-btn svg {
            transition: all 0.3s ease;
        }
        .wishlist-btn:hover svg {
            stroke: #ef4444;
        }
        .wishlist-btn.active svg {
            fill: #ef4444;
            stroke: #ef4444;
        }
        .wishlist-bounce {
            animation: wishlistBounce 0.6s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        @keyframes wishlistBounce {
            0% { transform: scale(1); }
            30% { transform: scale(1.4); }
            50% { transform: scale(0.9); }
            70% { transform: scale(1.15); }
            100% { transform: scale(1); }
        }
    </style>
</head>

<body class="bg-[#F8F9FA] text-[#1A1A1A] overflow-x-hidden">

    <!-- Navigation -->
    <nav class="bg-white/90 backdrop-blur-md shadow-sm sticky top-0 z-50 px-5 py-4 flex justify-between items-center border-b border-gray-100">
        <h1 class="text-2xl font-extrabold italic cursor-pointer" onclick="window.location.href='index.php'">
            <span class="text-orange-500">Triguna</span><span class="text-green-600">FOOD</span>
        </h1>

        <div class="flex items-center gap-3">
            <!-- Icon Keranjang Mobile -->
            

            <!-- Desktop Menu -->
            <div class="hidden md:flex space-x-3 items-center">
                <?php if ($is_logged): ?>
                    <span class="text-sm font-semibold text-gray-500 mr-2">Halo, <span class="text-gray-800"><?php echo htmlspecialchars($_SESSION['username']); ?></span></span>
                    <a href="pesanan.php" class="bg-orange-500 text-white px-5 py-2 rounded-xl text-sm font-bold hover:bg-orange-600 transition">Pesanan</a>
                    <button onclick="showModal('logout-modal')" class="bg-gray-100 text-gray-600 px-5 py-2 rounded-xl text-sm font-bold hover:bg-red-50 hover:text-red-600 transition">Logout</button>
                <?php else: ?>
                    <a href="login.php" class="bg-orange-600 text-white px-5 py-2.5 rounded-xl text-sm font-bold shadow-blue-200 shadow-lg">Login</a>
                    <a href="register.php" class="border border-gray-200 text-gray-700 px-6 py-2.5 rounded-xl text-sm font-bold hover:bg-gray-50">Daftar</a>
                <?php endif; ?>
            </div>

            <!-- Hamburger Button -->
            <button onclick="toggleMobileNav()" class="md:hidden p-2 text-gray-700">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-7 h-7">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                </svg>
            </button>
        </div>
    </nav>

    <!-- Mobile Sidebar Drawer -->
    <div id="mobile-nav-overlay" class="hidden fixed inset-0 bg-black/60 z-[80] backdrop-blur-sm" onclick="toggleMobileNav()"></div>
    <div id="mobile-nav-drawer" class="mobile-nav fixed top-0 right-0 w-72 h-full bg-white z-[90] shadow-2xl flex flex-col md:hidden">
        <div class="p-6 border-b flex justify-between items-center">
            <h1 class="text-xl font-extrabold italic">
                <span class="text-orange-500">Triguna</span><span class="text-green-600">FOOD</span>
            </h1>
            <button onclick="toggleMobileNav()" class="p-2 hover:bg-gray-100 rounded-full">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6 text-gray-400">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
<div class="p-6 flex-1">
            <!-- Profile Section in Mobile Menu -->
            <div class="mb-8 text-center">
                <?php if ($is_logged): ?>
                    <img src="<?php echo $profile_pic_url; ?>" alt="Profile" class="mobile-menu-profile">
                    <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Akun Saya</p>
                    <p class="text-lg font-black text-gray-800"><?php echo $username_display; ?></p>
                <?php else: ?>
                    <img src="https://commons.wikimedia.org/wiki/Special:FilePath/Profile_avatar_placeholder_large.png" alt="Profile" class="mobile-menu-profile">
                    <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Akun Saya</p>
                    <p class="text-lg font-black text-gray-800">Guest</p>
                <?php endif; ?>
            </div>

            <div class="space-y-4">
                <a href="home.php" class="flex items-center gap-3 font-bold text-gray-700 hover:text-orange-500 transition">
                    Home
                </a>
                <a href="profile.php" class="flex items-center gap-3 font-bold text-gray-700 hover:text-blue-500 transition">
                    Profile Saya
                </a>
                <a href="pesanan.php" class="flex items-center gap-3 font-bold text-gray-700 hover:text-orange-500 transition">
                    Pesanan Saya
                </a>
                        <?php if ($is_logged): ?>
                            <button onclick="showModal('logout-modal'); toggleMobileNav();" class="flex items-center gap-3 font-bold text-red-500 transition w-full">
                                Logout
                            </button>
                        <?php else: ?>
                    <a href="login.php" class="bg-orange-600 text-white py-3 rounded-xl font-bold text-center shadow-lg shadow-orange-100 transition block">
                        Login
                    </a>
                    <a href="register.php" class="border border-gray-200 text-gray-700 py-3 rounded-xl font-bold text-center transition block">
                        Daftar
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Drawer Keranjang -->
    <div id="cart-overlay" class="hidden fixed inset-0 bg-black/60 z-[80] backdrop-blur-sm" onclick="closeCart()"></div>
    <div id="cart-drawer" class="cart-drawer fixed top-0 right-0 w-full md:w-[420px] h-full bg-white z-[90] shadow-2xl flex flex-col">
        <div class="p-6 border-b flex justify-between items-center">
            <div>
                <h3 class="text-xl font-extrabold">Keranjang Saya</h3>
                <p class="text-xs text-gray-400 font-medium uppercase tracking-wider"><?php echo $cart_count; ?> Menu dipilih</p>
            </div>
            <button onclick="closeCart()" class="p-2 hover:bg-gray-100 rounded-full transition">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6 text-gray-400">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <div class="flex-1 overflow-y-auto p-6 space-y-4 md:space-y-4 md:grid md:grid-cols-3 md:gap-3">
            <?php
            $total_bayar = 0;
            if ($is_logged && $cart_count > 0):
                while ($item = mysqli_fetch_assoc($cart_items)):
                    $total_bayar += $item['subtotal'];
            ?>
                    <div class="flex gap-3 p-2 bg-gray-50 rounded-2xl border border-gray-100 relative">
                        <img src="uploads/products/<?php echo $item['gambar']; ?>" onerror="this.src='https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=200'" class="w-12 h-12 md:w-20 md:h-20 rounded-xl object-cover">
                        <div class="flex-1 flex flex-col justify-between">
                            <div class="pr-6">
                                <h4 class="text-sm font-bold leading-tight"><?php echo $item['nama_produk']; ?></h4>
                                <p class="text-[10px] text-gray-400 mt-1">Harga Satuan: Rp <?php echo number_format($item['harga']); ?></p>
                            </div>
                            <div class="flex justify-between items-end">
                                <span class="text-xs font-bold text-gray-500">x<?php echo $item['jumlah']; ?></span>
                                <span class="text-sm font-black text-orange-600">Rp <?php echo number_format($item['subtotal']); ?></span>
                            </div>
                        </div>
                        <a href="?remove=<?php echo $item['id']; ?>" class="absolute top-3 right-3 text-gray-300 hover:text-red-500 transition">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </a>
                    </div>
                <?php endwhile;
            else: ?>
                <div class="h-full flex flex-col items-center justify-center text-center py-20">
                    <div class="w-24 h-24 bg-gray-50 rounded-full flex items-center justify-center mb-4">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor" class="w-12 h-12 text-gray-200">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007zM8.625 10.5a.375.375 0 11-.75 0 .375.375 0 01.75 0zm7.5 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
                        </svg>
                    </div>
                    <p class="text-gray-400 font-bold text-sm">Keranjang kamu masih kosong</p>
                    <button onclick="closeCart()" class="mt-4 text-green-600 text-xs font-bold underline">Mulai Belanja</button>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($total_bayar > 0): ?>
            <div class="p-6 border-t bg-gray-50">
                <div class="flex justify-between items-center mb-6">
                    <span class="text-gray-500 font-semibold">Total Estimasi</span>
                    <span class="text-2xl font-black text-green-600">Rp <?php echo number_format($total_bayar); ?></span>
                </div>
                <button type="button" onclick="showCheckoutModal()" class="block w-full bg-orange-500 text-white py-4 rounded-2xl font-black text-center shadow-lg shadow-orange-100 hover:bg-orange-600 active:scale-95 transition">Checkout Sekarang</button>
            </div>
        <?php endif; ?>
    </div>

    <!-- Section Banner -->
    <section class="max-w-7xl mx-auto px-5 mt-6 scroll-reveal">
<div class="relative w-full aspect-[8/3] rounded-[2rem] overflow-hidden bg-gray-200 shadow-xl">
            <?php
            for ($i = 1; $i <= 4; $i++):
                $b = $banners[$i] ?? null;
            ?>
                <div class="banner-slide <?php echo $i === 1 ? 'banner-active' : ''; ?> absolute inset-0 w-full h-full">
                    <?php if ($b && !empty($b['img'])): ?>
                        <img src="uploads/<?php echo $b['img']; ?>" class="w-full h-full object-cover transition duration-700 object-contain md:object-cover">
                    <?php else: ?>
                        <div class="w-full h-full bg-gradient-to-r from-orange-500 to-green-600"></div>
                    <?php endif; ?>
                </div>
            <?php endfor; ?>

            <!-- Slide Indicators -->
            <div class="absolute bottom-6 right-8 md:right-16 flex gap-2 z-20">
                <?php for ($j = 1; $j <= 4; $j++): ?>
                    <div class="w-2 h-2 rounded-full bg-white/30 transition-all duration-300 indicator-dot-<?php echo $j; ?>"></div>
                <?php endfor; ?>
            </div>
        </div>
    </section>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-5 py-12">
        <!-- Kategori Filter Section -->
        <div class="mb-10">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h3 class="text-xl font-extrabold flex items-center gap-2 scroll-reveal">
                        <span class="w-1.5 h-6 bg-orange-500 rounded-full"></span>
                        Menu Spesial
                    </h3>
                    <p class="text-gray-400 text-xs font-bold uppercase tracking-widest mt-1">Pilihan Terbaik Hari Ini</p>
                </div>
            </div>

            <!-- Category Filter Buttons -->
<div class="flex flex-nowrap gap-3 pb-2 category-filter-container">
                <a href="index.php?kategori=Semua" 
class="px-6 py-3 rounded-2xl font-bold text-sm whitespace-nowrap flex-shrink-0 transition-all duration-300 <?php echo $selected_category === 'Semua' ? 'bg-orange-500 text-white shadow-lg shadow-orange-100' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">
                     Semua
                </a>
                <a href="index.php?kategori=Makanan" 
class="px-6 py-3 rounded-2xl font-bold text-sm whitespace-nowrap flex-shrink-0 transition-all duration-300 <?php echo $selected_category === 'Makanan' ? 'bg-orange-500 text-white shadow-lg shadow-orange-100' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">
                     Makanan
                </a>
                <a href="index.php?kategori=Minuman" 
class="px-6 py-3 rounded-2xl font-bold text-sm whitespace-nowrap flex-shrink-0 transition-all duration-300 <?php echo $selected_category === 'Minuman' ? 'bg-orange-500 text-white shadow-lg shadow-orange-100' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">
                     Minuman
                </a>
                <a href="index.php?kategori=Camilan" 
class="px-6 py-3 rounded-2xl font-bold text-sm whitespace-nowrap flex-shrink-0 transition-all duration-300 <?php echo $selected_category === 'Camilan' ? 'bg-orange-500 text-white shadow-lg shadow-orange-100' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">
                     Camilan
                </a>
            </div>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 md:gap-8">
            <?php
            // Build query based on selected category
            // First check if kategori column exists
            $check_col = mysqli_query($conn, "SHOW COLUMNS FROM products LIKE 'kategori'");
            $kategori_exists = mysqli_num_rows($check_col) > 0;
            
            if (!$kategori_exists) {
                // If kategori column doesn't exist, add it
                mysqli_query($conn, "ALTER TABLE products ADD COLUMN kategori VARCHAR(50) DEFAULT 'Lainnya'");
            }
            
            // Build query based on selected category
            if ($selected_category === 'Semua') {
                $products = mysqli_query($conn, "SELECT * FROM products ORDER BY COALESCE(kategori, 'Lainnya'), nama_produk");
            } else {
                $safe_category = mysqli_real_escape_string($conn, $selected_category);
                $products = mysqli_query($conn, "SELECT * FROM products WHERE COALESCE(kategori, 'Lainnya') = '$safe_category' ORDER BY nama_produk");
            }
            
            // Check for query errors
            if (!$products) {
                // Fallback: get all products if query fails
                $products = mysqli_query($conn, "SELECT * FROM products ORDER BY id DESC");
            }
            
            $product_count = 0;
            while ($p = mysqli_fetch_assoc($products)): 
                $product_count++;
            ?>
                <div class="bg-white rounded-[2rem] border border-gray-100 shadow-sm overflow-hidden group hover:shadow-xl hover:shadow-gray-200/50 transition-all duration-500 scroll-reveal" style="animation-delay: <?php echo (($product_count - 1) % 4) * 0.08; ?>s;">
                    <div class="h-44 md:h-60 relative overflow-hidden bg-gray-50">
                        <img src="uploads/products/<?php echo $p['gambar']; ?>" onerror="this.src='https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=500'" class="w-full h-full object-cover group-hover:scale-110 transition duration-700">
                        <div class="absolute top-4 right-4 bg-white/90 backdrop-blur px-3 py-1.5 rounded-2xl text-[10px] md:text-xs font-black text-green-600 shadow-sm">
                            Stok: <?php echo $p['stok']; ?>
                        </div>
                        <div class="absolute top-4 left-4 bg-orange-500/90 px-3 py-1.5 rounded-2xl text-[10px] font-black text-white">
                            <?php 
                            $cat_emoji = [
                                'Makanan' => '',
                                'Minuman' => '',
                                'Camilan' => '',
                                'Lainnya' => ''
                            ];
                            $kategori = $p['kategori'] ?? 'Lainnya';
                            echo ($cat_emoji[$kategori] ?? '📦') . ' ' . htmlspecialchars($kategori);
                            ?>
                        </div>
                        <!-- Wishlist Heart Button -->
                        <button type="button" onclick="event.stopPropagation(); handleWishlistClick(<?php echo $p['id']; ?>, this)" 
                            class="wishlist-btn absolute bottom-3 right-3 w-9 h-9 rounded-full bg-white/90 backdrop-blur-sm flex items-center justify-center shadow-md z-10 <?php echo in_array($p['id'], $wishlist_items) ? 'active' : ''; ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="#9ca3af" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z" />
                            </svg>
                        </button>
                    </div>
                    <div class="p-5">
                        <h4 class="font-extrabold text-sm md:text-lg mb-1 text-gray-800 line-clamp-1"><?php echo $p['nama_produk']; ?></h4>
                        <p class="text-gray-500 text-[10px] md:text-xs line-clamp-2 mb-2 min-h-[2.5rem]">
                            <?php echo !empty($p['deskripsi']) ? htmlspecialchars($p['deskripsi']) : 'Cita rasa autentik yang bikin nagih setiap hari.'; ?>
                        </p>
                        <p class="text-orange-500 font-black text-sm md:text-xl mb-4">Rp <?php echo number_format($p['harga']); ?></p>

                        <form method="POST" onsubmit="return handleAddToCart(this)" class="space-y-3">
                            <input type="hidden" name="product_id" value="<?php echo $p['id']; ?>">
                            <div class="flex gap-2">
                                <input type="number" name="qty" value="1" min="1" max="<?php echo $p['stok']; ?>"
                                    class="w-1/3 bg-gray-50 border-none text-center font-bold rounded-xl text-sm focus:ring-2 focus:ring-green-500"
                                    <?php echo $p['stok'] <= 0 ? 'disabled' : ''; ?>>
                                <?php if ($p['stok'] > 0): ?>
                                    <button type="submit" name="add_to_cart" class="w-full bg-orange-500 text-white font-bold py-2.5 rounded-xl text-xs hover:bg-orange-600 transition">+ Cart</button>
                                <?php else: ?>
                                    <button type="button" disabled class="flex-1 bg-gray-100 text-gray-400 font-bold py-3 rounded-xl text-xs cursor-not-allowed">Habis</button>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endwhile; 
            
            // Show message if no products found
            if ($product_count === 0): ?>
                <div class="col-span-2 lg:col-span-4 flex flex-col items-center justify-center py-20">
                    <div class="text-6xl mb-4">📭</div>
                    <h3 class="text-xl font-bold text-gray-700">Tidak ada produk</h3>
                    <p class="text-gray-500 text-sm">Kategori <?php echo htmlspecialchars($selected_category); ?> sedang kosong</p>
                    <a href="index.php?kategori=Semua" class="mt-4 px-6 py-2 bg-orange-500 text-white rounded-xl font-bold hover:bg-orange-600 transition">
                        Lihat Semua Produk
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Floating Cart Button -->
    <div class="fixed bottom-8 right-8 z-[60]">
        <button onclick="handleRequiredAction('cart')" class="bg-green-600 text-white p-5 rounded-3xl shadow-2xl hover:scale-110 transition active:scale-90 relative flex items-center justify-center">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-7 h-7">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007zM8.625 10.5a.375.375 0 11-.75 0 .375.375 0 01.75 0zm7.5 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
            </svg>
            <?php if ($cart_count > 0): ?>
                <span class="absolute -top-2 -right-2 bg-red-500 text-white text-[10px] w-6 h-6 rounded-full flex items-center justify-center font-black border-2 border-white"><?php echo $cart_count; ?></span>
            <?php endif; ?>
        </button>
    </div>

    <!-- ==========================================
         CHECKOUT CONFIRMATION MODAL
         ========================================== -->
    <?php if ($total_bayar > 0): ?>
    <div id="checkout-modal" class="hidden fixed inset-0 z-[100] flex items-center justify-center p-4" style="display:none;">
        <div class="absolute inset-0 checkout-modal-overlay" onclick="hideCheckoutModal()"></div>
        <div class="checkout-modal-content bg-white rounded-[2.5rem] p-8 z-10 max-w-sm w-full text-center shadow-2xl border border-gray-100 relative overflow-hidden">
            <!-- Sparkle container -->
            <div id="checkout-sparkles" class="absolute inset-0 pointer-events-none"></div>
            
            <div class="relative z-10">
                <div class="w-20 h-20 bg-gradient-to-br from-orange-400 to-orange-600 rounded-full flex items-center justify-center mx-auto mb-5 shadow-lg shadow-orange-200">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="white" class="w-10 h-10">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z" />
                    </svg>
                </div>
                
                <h3 class="text-2xl font-black text-gray-900 mb-2">Checkout Pesanan?</h3>
                <p class="text-gray-500 text-sm mb-6 leading-relaxed">Kamu akan melanjutkan ke halaman pembayaran untuk menyelesaikan pesanan.</p>
                
                <div class="bg-gradient-to-r from-orange-50 to-yellow-50 rounded-2xl p-4 mb-6 border border-orange-100">
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-sm text-gray-600 font-medium">Total Item</span>
                        <span class="text-sm font-bold text-gray-800"><?php echo $cart_count; ?> menu</span>
                    </div>
                    <div class="h-px bg-orange-200/50 my-2"></div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600 font-medium">Total Pembayaran</span>
                        <span class="text-lg font-black text-orange-600">Rp <?php echo number_format($total_bayar); ?></span>
                    </div>
                </div>
                
                <div class="flex flex-col gap-3">
                    <a href="checkout.php" class="checkout-pulse bg-gradient-to-r from-orange-500 to-orange-600 text-white py-4 rounded-2xl font-black text-center shadow-lg shadow-orange-200 hover:shadow-xl hover:shadow-orange-300 transition-all duration-300 hover:-translate-y-0.5 block">
                        🛒 Lanjut ke Checkout
                    </a>
                    <button onclick="hideCheckoutModal()" class="bg-gray-50 text-gray-500 py-3 rounded-2xl font-bold hover:bg-gray-100 transition">
                        Kembali Belanja
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <!-- --- AKHIR MODAL CHECKOUT --- -->

    <!-- MODALS -->
    <div id="login-modal" class="hidden fixed inset-0 z-[100] flex items-center justify-center px-4">
        <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm" onclick="hideModal('login-modal')"></div>
        <div class="bg-white rounded-[2.5rem] p-8 md:p-12 z-10 max-w-sm w-full text-center shadow-2xl modal-enter">
            <div class="w-20 h-20 bg-orange-50 text-orange-500 rounded-full flex items-center justify-center mx-auto mb-6">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
            </div>
            <h3 class="text-2xl font-black mb-3">Oops! Belum Login</h3>
            <p class="text-gray-500 text-sm mb-8 leading-relaxed">Kamu harus masuk ke akunmu dulu untuk bisa belanja atau melihat pesanan.</p>
            <div class="flex flex-col gap-3">
                <a href="login.php" class="bg-green-600 text-white py-4 rounded-2xl font-black shadow-lg shadow-green-100 hover:bg-green-700 transition">Login Sekarang</a>
                <a href="register.php" class="text-gray-400 text-xs font-bold uppercase tracking-widest hover:text-gray-600 py-2">Daftar Akun Baru</a>
                <button onclick="hideModal('login-modal')" class="mt-2 text-gray-300 font-bold text-sm">Nanti Saja</button>
            </div>
        </div>
    </div>

    <div id="logout-modal" class="hidden fixed inset-0 z-[100] flex items-center justify-center px-4">
        <div class="absolute inset-0 bg-gray-900/40 backdrop-blur-sm" onclick="hideModal('logout-modal')"></div>
        <div class="bg-white rounded-[2rem] p-8 z-10 max-w-xs w-full text-center shadow-2xl modal-enter">
            <h3 class="text-xl font-bold mb-2">Mau Keluar?</h3>
            <p class="text-gray-500 text-sm mb-6">Pastikan belanjaanmu sudah aman ya sebelum logout.</p>
            <div class="grid grid-cols-2 gap-3">
                <button onclick="hideModal('logout-modal')" class="bg-gray-100 py-3 rounded-xl font-bold text-gray-600">Batal</button>
                <a href="logout.php" class="bg-red-500 text-white py-3 rounded-xl font-bold shadow-lg shadow-red-100">Ya, Keluar</a>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-gray-900 text-white py-12 scroll-reveal">
        <div class="max-w-7xl mx-auto flex flex-col items-center text-center">
            <h2 class="text-2xl font-bold italic mb-4">
                <span class="text-orange-500">Triguna</span><span class="text-green-600">FOOD</span>
            </h2>
            <p class="text-gray-400 text-sm max-w-xs mb-8 font-medium">Solusi lapar kamu setiap hari dengan menu spesial dan harga terjangkau.</p>

            <a href="https://www.instagram.com/trigunafood/" class="flex items-center gap-2 px-6 py-3 bg-gradient-to-tr from-yellow-400 via-red-500 to-purple-600 text-white rounded-2xl font-bold text-sm shadow-lg hover:scale-105 transition-transform active:scale-95">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect>
                    <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path>
                    <line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line>
                </svg>
                @trigunafood
            </a>

            <div class="w-full h-px bg-white/5 my-10"></div>
            <p class="text-gray-500 text-[10px] font-bold uppercase tracking-[0.2em]">&copy; 2024 TrigunaFOOD - All Rights Reserved</p>
        </div>
    </footer>

    <!-- Scripting -->
    <script>
        const IS_LOGGED = <?php echo $is_logged ? 'true' : 'false'; ?>;

        function handleRequiredAction(actionType) {
            if (!IS_LOGGED) {
                showModal('login-modal');
                return false;
            }
            if (actionType === 'cart') {
                toggleCart();
            }
            return true;
        }

        function handleAddToCart(form) {
            if (!IS_LOGGED) {
                showModal('login-modal');
                return false;
            }
            return true;
        }

        function showModal(id) {
            const modal = document.getElementById(id);
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function hideModal(id) {
            const modal = document.getElementById(id);
            modal.classList.add('hidden');
            document.body.style.overflow = 'auto';
        }

        function toggleCart() {
            document.getElementById('cart-drawer').classList.toggle('active');
            document.getElementById('cart-overlay').classList.toggle('hidden');
            const isActive = document.getElementById('cart-drawer').classList.contains('active');
            document.body.style.overflow = isActive ? 'hidden' : 'auto';
        }

        function closeCart() {
            document.getElementById('cart-drawer').classList.remove('active');
            document.getElementById('cart-overlay').classList.add('hidden');
            document.body.style.overflow = 'auto';
        }

        // FIX: Fungsi Sidebar Mobile
        function toggleMobileNav() {
            const drawer = document.getElementById('mobile-nav-drawer');
            const overlay = document.getElementById('mobile-nav-overlay');
            drawer.classList.toggle('active');
            overlay.classList.toggle('hidden');
            const isActive = drawer.classList.contains('active');
            document.body.style.overflow = isActive ? 'hidden' : 'auto';
        }

        let slideIndex = 0;

        function showSlides() {
            const slides = document.querySelectorAll(".banner-slide");
            if (!slides || slides.length === 0) return;
            slides.forEach(s => s.classList.remove("banner-active"));
            slideIndex++;
            if (slideIndex > slides.length) slideIndex = 1;
            if (slides[slideIndex - 1]) slides[slideIndex - 1].classList.add("banner-active");
            setTimeout(showSlides, 5000);
        }

        window.onload = function() {
            showSlides();
            const params = new URLSearchParams(window.location.search);
            if (params.get('show_cart') === '1' && IS_LOGGED) {
                toggleCart();
            }
            initScrollReveal();
        };

        // --- Scroll Reveal Animation ---
        function initScrollReveal() {
            const revealElements = document.querySelectorAll('.scroll-reveal');
            if (!revealElements.length) return;
            
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('visible');
                        entry.target.classList.remove('hidden-out');
                    } else if (entry.target.classList.contains('visible')) {
                        entry.target.classList.remove('visible');
                        entry.target.classList.add('hidden-out');
                    }
                });
            }, {
                threshold: 0.08,
                rootMargin: '0px 0px -40px 0px'
            });
            
            revealElements.forEach(el => observer.observe(el));
        }

        // --- Checkout Modal ---
        function showCheckoutModal() {
            const modal = document.getElementById('checkout-modal');
            if (!modal) return;
            modal.style.display = 'flex';
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            createSparkles();
        }

        function hideCheckoutModal() {
            const modal = document.getElementById('checkout-modal');
            if (!modal) return;
            const content = modal.querySelector('.checkout-modal-content');
            content.style.animation = 'checkoutPopOut 0.3s ease forwards';
            setTimeout(() => {
                modal.style.display = 'none';
                modal.classList.add('hidden');
                content.style.animation = '';
                document.body.style.overflow = document.getElementById('cart-drawer').classList.contains('active') ? 'hidden' : 'auto';
            }, 280);
        }

        function createSparkles() {
            const container = document.getElementById('checkout-sparkles');
            if (!container) return;
            container.innerHTML = '';
            const colors = ['#f97316', '#fb923c', '#fdba74', '#fbbf24', '#f59e0b', '#a78bfa'];
            for (let i = 0; i < 15; i++) {
                const sparkle = document.createElement('div');
                sparkle.className = 'sparkle';
                sparkle.style.left = Math.random() * 100 + '%';
                sparkle.style.top = Math.random() * 80 + '%';
                sparkle.style.background = colors[Math.floor(Math.random() * colors.length)];
                sparkle.style.animationDelay = Math.random() * 0.5 + 's';
                const size = (Math.random() * 6 + 3) + 'px';
                sparkle.style.width = size;
                sparkle.style.height = size;
                container.appendChild(sparkle);
            }
        }

        // --- Wishlist Toggle ---
        function handleWishlistClick(productId, btn) {
            if (!IS_LOGGED) {
                showModal('login-modal');
                return;
            }
            toggleWishlist(productId, btn);
        }

        function toggleWishlist(productId, btn) {
            btn.classList.add('wishlist-bounce');
            setTimeout(() => btn.classList.remove('wishlist-bounce'), 600);
            
            const formData = new FormData();
            formData.append('ajax_toggle_wishlist', '1');
            formData.append('product_id', productId);
            
            fetch(window.location.pathname, {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    if (data.action === 'added') {
                        btn.classList.add('active');
                    } else {
                        btn.classList.remove('active');
                    }
                }
            })
            .catch(err => console.error('Wishlist error:', err));
        }
    </script>
</body>

</html>