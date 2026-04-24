<?php
// ==========================================
// 1. INISIALISASI SESSION DAN KONEKSI DATABASE
// ==========================================
session_start();
require_once 'config.php';

// Proteksi Halaman: Jika belum login, lempar ke login.php
if (!isset($_SESSION['user_id']) && !isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// Mengambil User ID dari Session atau Database
$user_id = null;
if (isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id'])) {
    $user_id = intval($_SESSION['user_id']);
} elseif (isset($_SESSION['username'])) {
    $username_safe = mysqli_real_escape_string($conn, $_SESSION['username']);
    $u = mysqli_query($conn, "SELECT id FROM users WHERE username = '$username_safe' LIMIT 1");
    if ($u && mysqli_num_rows($u) > 0) {
        $user_row = mysqli_fetch_assoc($u);
        $user_id = intval($user_row['id']);
    }
}

// Validasi Akhir User ID
if (empty($user_id) || $user_id <= 0) {
    header("Location: login.php");
    exit();
}

// Auto-sync: Pastikan semua produk punya kategori
$check_null = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM products WHERE kategori IS NULL OR kategori = ''");
$null_count = mysqli_fetch_assoc($check_null)['cnt'] ?? 0;
if ($null_count > 0) {
    mysqli_query($conn, "UPDATE products SET kategori = 'Lainnya' WHERE kategori IS NULL OR kategori = ''");
}
// --- AKHIR INISIALISASI ---


// ==========================================
// 2. LOGIKA KERANJANG (ADD & REMOVE)
// ==========================================

// FUNGSI: Tambah ke Keranjang
if (isset($_POST['add_to_cart'])) {
    $p_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    $qty  = isset($_POST['qty']) ? intval($_POST['qty']) : 0;

    if ($p_id <= 0 || $qty <= 0) {
        header("Location: home.php?show_cart=0");
        exit();
    }

    // Cek Stok Produk
    $prodRes = mysqli_query($conn, "SELECT id, harga, stok FROM products WHERE id = $p_id LIMIT 1");
    if (!$prodRes || mysqli_num_rows($prodRes) == 0) {
        header("Location: home.php?show_cart=0");
        exit();
    }
    
    $prod  = mysqli_fetch_assoc($prodRes);
    $price = floatval($prod['harga']);
    $stock = intval($prod['stok']);

    // Cek apakah produk sudah ada di keranjang user
    $existingQty = 0;
    $check = mysqli_query($conn, "SELECT jumlah FROM cart WHERE user_id = '$user_id' AND product_id = '$p_id'");
    if ($check && mysqli_num_rows($check) > 0) {
        $row = mysqli_fetch_assoc($check);
        $existingQty = intval($row['jumlah']);
    }

    $newTotal = $existingQty + $qty;
    if ($newTotal > $stock) {
        header("Location: home.php?show_cart=0&stock_error=1&max=" . $stock);
        exit();
    }

    $subtotal = $qty * $price;

    if ($existingQty > 0) {
        // Update jika sudah ada
        mysqli_query($conn, "UPDATE cart SET jumlah = jumlah + $qty, subtotal = subtotal + $subtotal WHERE user_id = '$user_id' AND product_id = '$p_id'");
    } else {
        // Insert jika baru
        mysqli_query($conn, "INSERT INTO cart (user_id, product_id, jumlah, subtotal) VALUES ('$user_id', '$p_id', '$qty', '$subtotal')");
    }

    header("Location: home.php?added=1");
    exit();
}

// FUNGSI: Hapus dari Keranjang
if (isset($_GET['remove'])) {
    $c_id = $_GET['remove'];
    mysqli_query($conn, "DELETE FROM cart WHERE id = '$c_id' AND user_id = '$user_id'");
    header("Location: home.php?show_cart=1");
}

// FUNGSI: AJAX Update Qty
if (isset($_POST['ajax_update_qty']) && $_POST['ajax_update_qty'] == '1') {
    $c_id = isset($_POST['cart_id']) ? intval($_POST['cart_id']) : 0;
    $new_qty = isset($_POST['qty']) ? intval($_POST['qty']) : 0;
    
    $response = ['status' => 'error', 'message' => 'Invalid data'];
    
    if ($c_id > 0 && $new_qty > 0) {
        $check = mysqli_query($conn, "SELECT cart.product_id, products.stok, products.harga FROM cart JOIN products ON cart.product_id = products.id WHERE cart.id = '$c_id' AND cart.user_id = '$user_id'");
        if ($check && mysqli_num_rows($check) > 0) {
            $row = mysqli_fetch_assoc($check);
            if ($new_qty <= $row['stok']) {
                $subtotal = $new_qty * $row['harga'];
                mysqli_query($conn, "UPDATE cart SET jumlah = '$new_qty', subtotal = '$subtotal' WHERE id = '$c_id'");
                
                $total_query = mysqli_query($conn, "SELECT SUM(subtotal) as total FROM cart WHERE user_id = '$user_id'");
                $total_row = mysqli_fetch_assoc($total_query);
                
                $response = [
                    'status' => 'success', 
                    'new_subtotal' => $subtotal,
                    'grand_total' => $total_row['total'],
                    'qty' => $new_qty
                ];
            } else {
                $response['message'] = 'Stok tidak cukup (max: ' . $row['stok'] . ')';
            }
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}
// --- AKHIR LOGIKA KERANJANG ---


// ==========================================
// 2.5 LOGIKA WISHLIST
// ==========================================

// Auto-create wishlist table
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS wishlist (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_wishlist (user_id, product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// AJAX: Toggle Wishlist
if (isset($_POST['ajax_toggle_wishlist']) && $_POST['ajax_toggle_wishlist'] == '1') {
    $wl_product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    $response = ['status' => 'error', 'message' => 'Invalid data'];
    
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
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// Get user's wishlist items
$wishlist_items = [];
$wl_result = mysqli_query($conn, "SELECT product_id FROM wishlist WHERE user_id = '$user_id'");
if ($wl_result) {
    while ($wl_row = mysqli_fetch_assoc($wl_result)) {
        $wishlist_items[] = intval($wl_row['product_id']);
    }
}
// --- AKHIR LOGIKA WISHLIST ---


// ==========================================
// 3. PENGAMBILAN DATA (DATA FETCHING)
// ==========================================

// Get selected category from URL or default to all
$selected_category = isset($_GET['kategori']) ? htmlspecialchars($_GET['kategori']) : 'Semua';

// Ambil User Profile Photo dan Username
$user_profile = mysqli_query($conn, "SELECT username, foto_profil FROM users WHERE id = $user_id LIMIT 1");
$user_data = mysqli_fetch_assoc($user_profile);
$user_profile_pic = $user_data['foto_profil'] ?? null;
$username_display = htmlspecialchars($user_data['username'] ?? $_SESSION['username']);

// Function to get profile picture URL
function getProfilePictureUrl($foto_profil) {
    if ($foto_profil && file_exists("uploads/profiles/$foto_profil")) {
        return "uploads/profiles/$foto_profil";
    }
    return "https://commons.wikimedia.org/wiki/Special:FilePath/Profile_avatar_placeholder_large.png";
}

$profile_pic_url = getProfilePictureUrl($user_profile_pic);

// Ambil Item Keranjang untuk Drawer
$cart_items = mysqli_query($conn, "SELECT cart.*, products.nama_produk, products.harga, products.stok, products.gambar FROM cart JOIN products ON cart.product_id = products.id WHERE cart.user_id = '$user_id'");
$cart_count = mysqli_num_rows($cart_items);

// Ambil Data Banner
$banners = [];
for ($i = 1; $i <= 4; $i++) {
    $res = mysqli_query($conn, "SELECT * FROM banners WHERE position = $i LIMIT 1");
    $banners[$i] = mysqli_fetch_assoc($res);
}
// --- AKHIR DATA FETCHING ---
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home - TrigunaFOOD</title>
    
    <!-- Dependencies -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="icon" href="https://i.imgur.com/o6FBULN.jpeg">

    <style>
        /* Global & Typography */
        body { font-family: 'Plus Jakarta Sans', sans-serif; scroll-behavior: smooth; }

        /* Profile Picture Styling */
        .navbar-profile-pic {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #10b981;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .navbar-profile-pic:hover {
            border-color: #059669;
            transform: scale(1.05);
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

        /* Animasi Slide Banner */
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
            width: 100%;
            height: 100%;
            flex-shrink: 0;
            position: relative;
        }

        /* Animasi Drawer (Nav & Cart) */
        .mobile-nav, .cart-drawer { 
            transform: translateX(100%); 
            transition: transform 0.45s cubic-bezier(0.32, 0.72, 0, 1); 
            will-change: transform;
            backface-visibility: hidden;
            -webkit-backface-visibility: hidden;
        }
        .mobile-nav.active, .cart-drawer.active { transform: translateX(0); }

        /* Animasi Modal Logout */
        #logout-modal.show { display: flex; animation: fadeIn 0.2s ease-out; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

        /* Animasi Toast Notification */
        #toast-notification {
            transform: translateY(-150%);
            transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            z-index: 9999;
        }
        #toast-notification.show { transform: translateY(0); }

        /* Quantity Button Styling */
        .qty-btn {
            transition: all 0.2s ease;
        }
        .qty-btn:hover:not(:disabled) {
            transform: scale(1.05);
        }
        .qty-btn:active:not(:disabled) {
            transform: scale(0.95);
        }
        .qty-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Cart Item Animation */
        .cart-item {
            animation: slideIn 0.3s ease-out;
        }
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        /* Flying Cart Animation */
        .flying-img {
            position: fixed;
            z-index: 9999;
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 50%;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            transition: all 0.8s cubic-bezier(0.25, 1, 0.5, 1);
            pointer-events: none;
        }
        .cart-bounce {
            animation: cartBounce 0.5s cubic-bezier(0.36, 0, 0.66, -0.56);
        }
        @keyframes cartBounce {
            0% { transform: scale(1); }
            50% { transform: scale(1.2); }
            100% { transform: scale(1); }
        }

        /* Loading Animation */
        .loading-spinner {
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }   
        }

        /* Banner Mobile Optimization */
        @media (max-width: 768px) {
            .banner-section {
                padding-left: 0.375rem;  /* px-1.5 - mengurangi padding untuk membuat banner lebih besar */
                padding-right: 0.375rem;
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

<body class="bg-[#F8F9FA] text-[#1A1A1A]">

    <!-- ==========================================
         A. TOAST NOTIFICATION (POPUP BERHASIL)
         ========================================== -->
    <div id="toast-notification" class="fixed top-4 left-0 right-0 flex justify-center px-4 pointer-events-none">
        <div class="bg-white border-l-4 border-green-500 shadow-2xl rounded-2xl px-6 py-4 flex items-center gap-4 pointer-events-auto max-w-sm w-full">
            <div class="bg-green-100 p-2 rounded-full shrink-0">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                </svg>
            </div>
            <div>
                <p class="font-black text-gray-800 text-sm">Berhasil Ditambahkan!</p>
                <p class="text-xs text-gray-500 font-semibold">Menu sudah masuk ke keranjang.</p>
            </div>
        </div>
    </div>
    <!-- --- AKHIR TOAST --- -->


    <!-- ==========================================
         B. NAVIGATION BAR (HEADER)
         ========================================== -->
    <nav class="bg-white/80 backdrop-blur-md shadow-sm sticky top-0 z-50 px-5 py-4 flex justify-between items-center border-b border-gray-100">
        <!-- Logo -->
        <h1 class="text-2xl font-extrabold tracking-tight italic">
            <span class="text-orange-500">Triguna</span><span class="text-green-600">FOOD</span>
        </h1>

        <!-- Menu Desktop -->
        <div class="flex items-center gap-4">
            <div class="hidden md:flex space-x-4 items-center">
                <div class="flex items-center gap-3">
                    <img src="<?php echo $profile_pic_url; ?>" alt="Profile" class="navbar-profile-pic" title="<?php echo $username_display; ?>">
                    <span class="text-sm font-semibold text-gray-600">Halo, <?php echo $username_display; ?></span>
                </div>
                <a href="profile.php" class="bg-blue-50 text-blue-600 px-5 py-2.5 rounded-xl text-sm font-bold border border-blue-100 hover:bg-blue-100 transition">Profile</a>
                <a href="pesanan.php" class="bg-orange-600 text-white px-5 py-2.5 rounded-xl text-sm font-bold shadow-lg shadow-orange-100 hover:bg-orange-700 transition">Pesanan</a>
                <button onclick="showLogoutConfirm()" class="bg-red-50 text-red-600 px-5 py-2.5 rounded-xl text-sm font-bold border border-red-100 hover:bg-red-100 transition">Logout</button>
            </div>

            <!-- Tombol Menu Mobile -->
            <button onclick="toggleMobileNav()" class="md:hidden p-2 text-gray-700">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-7 h-7">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                </svg>
            </button>
        </div>
    </nav>
    <!-- --- AKHIR NAV BAR --- -->


    <!-- ==========================================
         C. MOBILE MENU DRAWER (SIDEBAR KANAN)
         ========================================== -->
    <div id="mobile-nav-overlay" class="hidden fixed inset-0 bg-black/60 z-[80] backdrop-blur-sm" onclick="toggleMobileNav()"></div>
    <div id="mobile-nav-drawer" class="mobile-nav fixed top-0 right-0 w-72 h-full bg-white z-[90] shadow-2xl flex flex-col md:hidden">
        <div class="p-6 border-b flex justify-between items-center">
            <h1 class="text-xl font-extrabold italic"><span class="text-orange-500">Triguna</span><span class="text-green-600">FOOD</span></h1>
            <button onclick="toggleMobileNav()" class="p-2 hover:bg-gray-100 rounded-full">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6 text-gray-400">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <div class="p-6 flex-1">
            <!-- Profile Section in Mobile Menu -->
            <div class="mb-8 text-center">
                <img src="<?php echo $profile_pic_url; ?>" alt="Profile" class="mobile-menu-profile">
                <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Akun Saya</p>
                <p class="text-lg font-black text-gray-800"><?php echo $username_display; ?></p>
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
                <button onclick="showLogoutConfirm(); toggleMobileNav();" class="flex items-center gap-3 font-bold text-red-500 transition w-full">
                    Logout
                </button>
            </div>
        </div>
    </div>
    <!-- --- AKHIR MOBILE NAV --- -->


    <!-- ==========================================
         D. KERANJANG DRAWER (CART DRAWER)
         ========================================== -->
    <div id="cart-overlay" class="hidden fixed inset-0 bg-black/60 z-[80] backdrop-blur-sm" onclick="toggleCart()"></div>
    <div id="cart-drawer" class="cart-drawer fixed top-0 right-0 w-full md:w-[400px] h-full bg-white z-[90] shadow-2xl flex flex-col">
        <!-- Header Cart -->
        <div class="p-6 border-b flex justify-between items-center bg-white sticky top-0">
            <div>
                <h3 class="text-xl font-extrabold text-gray-800">Keranjang</h3>
                <p class="text-sm text-gray-500"><?php echo $cart_count; ?> item terpilih</p>
            </div>
            <button onclick="toggleCart()" class="p-2 hover:bg-gray-100 rounded-full transition">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6 text-gray-400">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <!-- List Item Cart -->
        <div class="flex-1 overflow-y-auto p-6 space-y-4 md:space-y-4 mobile-cart-list gap-3">
            <?php
            $grand_total = 0;
            mysqli_data_seek($cart_items, 0);
            if ($cart_count > 0):
                while ($item = mysqli_fetch_assoc($cart_items)):
                    $grand_total += $item['subtotal'];
            ?>
                    <div class="flex gap-3 p-2 bg-gray-50 rounded-2xl border border-gray-100 md:p-3">
                        <div class="w-12 h-12 md:w-20 md:h-20 bg-gray-200 rounded-xl overflow-hidden shrink-0">
                            <img src="uploads/products/<?php echo $item['gambar'] ?? 'default.jpg'; ?>" 
                                 onerror="this.src='https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=200'"
                             class="w-full h-full object-cover md:object-cover object-contain">
                        </div>
                        <div class="flex-1 flex flex-col justify-between py-1">
                            <div class="flex justify-between items-start">
                                <h4 class="text-sm font-bold text-gray-800 leading-tight"><?php echo $item['nama_produk']; ?></h4>
                                <a href="?remove=<?php echo $item['id']; ?>" class="text-gray-300 hover:text-red-500 transition ml-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5">
                                        <path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 006 3.75V4H5a2 2 0 00-2 2v.092c0 .546.126 1.082.366 1.565l.51 1.02a12.022 12.022 0 003.542 4.606l1.246 1.002a2.75 2.75 0 003.67 0l1.246-1.002a12.022 12.022 0 003.542-4.606l.51-1.02A2.75 2.75 0 0017 6.092V6a2 2 0 00-2-2h-1v-.25A2.75 2.75 0 0011.25 1h-2.5zM7.5 3.75A1.25 1.25 0 018.75 2.5h2.5A1.25 1.25 0 0112.5 3.75V4h-5v-.25z" clip-rule="evenodd" />
                                    </svg>
                                </a>
                            </div>
                            <div class="flex justify-between items-end mt-2">
                                <div class="flex items-center gap-1.5 bg-gray-100 rounded-lg px-1.5 py-1">
                                    <?php if ($item['jumlah'] <= 1): ?>
                                        <a href="?remove=<?php echo $item['id']; ?>" class="w-6 h-6 flex items-center justify-center text-red-500 hover:bg-red-50 rounded-md transition font-bold text-xs">✕</a>
                                    <?php else: ?>
                                        <button onclick="updateCartQty(<?php echo $item['id']; ?>, -1, this)" class="w-6 h-6 flex items-center justify-center text-gray-600 hover:bg-gray-200 rounded-md transition font-bold text-lg leading-none">-</button>
                                    <?php endif; ?>
                                    
                                    <span class="w-6 text-center text-xs font-bold text-gray-700 qty-display"><?php echo $item['jumlah']; ?></span>
                                    
                                    <button onclick="updateCartQty(<?php echo $item['id']; ?>, 1, this)" class="w-6 h-6 flex items-center justify-center text-gray-600 hover:bg-gray-200 rounded-md transition font-bold text-lg leading-none">+</button>
                                </div>
                                <span class="text-sm font-bold text-orange-500 subtotal-display" data-base="<?php echo $item['subtotal']; ?>">Rp <?php echo number_format($item['subtotal']); ?></span>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="h-full flex flex-col items-center justify-center text-center opacity-40 py-20">
                    <p class="text-lg">Wah, belum ada makanan nih.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Checkout Button -->
        <?php if ($cart_count > 0): ?>
            <div class="p-6 border-t bg-gray-50/50 backdrop-blur-md">
                <div class="flex justify-between items-center mb-6">
                    <span class="text-gray-500 font-medium">Total Pembayaran</span>
                    <span class="text-2xl font-extrabold text-orange-600 grand-total-display">Rp <?php echo number_format($grand_total); ?></span>
                </div>
                <button onclick="showCheckoutModal()" class="w-full bg-gradient-to-r from-green-500 to-emerald-600 text-white py-4 rounded-2xl font-black text-center shadow-lg shadow-green-200 hover:shadow-xl hover:shadow-green-300 hover:-translate-y-0.5 transition-all duration-300 active:scale-95">
                    ✨ Checkout Sekarang
                </button>
            </div>
        <?php endif; ?>
    </div>
    <!-- --- AKHIR KERANJANG --- -->


    <!-- ==========================================
         E. HERO SECTION (BANNER SLIDER)
         ========================================== -->
    <section class="banner-section max-w-7xl mx-auto px-5 md:px-5 mt-6 scroll-reveal">
        <div class="relative w-full aspect-[8/3] rounded-[2rem] overflow-hidden bg-gray-200 shadow-xl" id="banner-container">
            <div id="banner-wrapper">
            <?php
            for ($i = 1; $i <= 4; $i++):
                $b = $banners[$i] ?? null;
            ?>
                <!-- Per Slide Banner -->
                <div class="banner-slide">
                    <?php if ($b && !empty($b['img'])): ?>
                        <img src="uploads/<?php echo $b['img']; ?>" class="w-full h-full object-contain" draggable="false">
                    <?php else: ?>
                        <div class="w-full h-full bg-gradient-to-r from-orange-500 to-green-600"></div>
                    <?php endif; ?>
                </div>
            <?php endfor; ?>
            </div>

            <!-- Indicators Bullets -->
            <div class="absolute bottom-6 right-8 flex gap-2 z-20">
                <?php for ($j = 1; $j <= 4; $j++): ?>
                    <div class="w-2 h-2 rounded-full bg-white/30 transition-all duration-300 indicator-dot-<?php echo $j; ?>"></div>
                <?php endfor; ?>
            </div>
        </div>
    </section>
    <!-- --- AKHIR BANNER --- -->


    <!-- ==========================================
         F. MAIN CONTENT (LIST PRODUK)
         ========================================== -->
    <main class="max-w-7xl mx-auto px-5 py-10">
        <!-- Kategori Filter Section -->
        <div class="mb-10 scroll-reveal">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h3 class="text-xl font-extrabold flex items-center gap-2">
                        <span class="w-1.5 h-6 bg-orange-500 rounded-full"></span>
                        Menu Spesial
                    </h3>
                    <p class="text-gray-400 text-xs font-bold uppercase tracking-widest mt-1">Pilihan Terbaik Hari Ini</p>
                </div>
            </div>

            <!-- Category Filter Buttons -->
<div class="flex flex-nowrap gap-3 pb-2 category-filter-container">
                <a href="home.php?kategori=Semua" 
class="px-6 py-3 rounded-2xl font-bold text-sm whitespace-nowrap flex-shrink-0 transition-all duration-300 <?php echo $selected_category === 'Semua' ? 'bg-orange-500 text-white shadow-lg shadow-orange-100' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">
                     Semua
                </a>
                <a href="home.php?kategori=Makanan" 
class="px-6 py-3 rounded-2xl font-bold text-sm whitespace-nowrap flex-shrink-0 transition-all duration-300 <?php echo $selected_category === 'Makanan' ? 'bg-orange-500 text-white shadow-lg shadow-orange-100' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">
                     Makanan
                </a>
                <a href="home.php?kategori=Minuman" 
class="px-6 py-3 rounded-2xl font-bold text-sm whitespace-nowrap flex-shrink-0 transition-all duration-300 <?php echo $selected_category === 'Minuman' ? 'bg-orange-500 text-white shadow-lg shadow-orange-100' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">
                     Minuman
                </a>
                <a href="home.php?kategori=Camilan" 
class="px-6 py-3 rounded-2xl font-bold text-sm whitespace-nowrap flex-shrink-0 transition-all duration-300 <?php echo $selected_category === 'Camilan' ? 'bg-orange-500 text-white shadow-lg shadow-orange-100' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">
                     Camilan
                </a>
            </div>
        </div>

        <!-- Grid Produk -->
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
                <div class="bg-white rounded-[2rem] border border-gray-100 shadow-sm overflow-hidden group hover:shadow-xl transition-all duration-500 scroll-reveal" style="animation-delay: <?php echo (($product_count - 1) % 4) * 0.08; ?>s;">
                    <!-- Image Produk -->
                    <div class="h-44 md:h-60 relative overflow-hidden bg-gray-50">
                        <img src="uploads/products/<?php echo $p['gambar']; ?>" 
                             onerror="this.src='https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=500'"
                             class="w-full h-full object-cover group-hover:scale-110 transition duration-700">
                        <div class="absolute top-4 right-4 bg-white/90 px-3 py-1.5 rounded-2xl text-[10px] font-black text-green-600">
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
                        <button type="button" onclick="event.stopPropagation(); toggleWishlist(<?php echo $p['id']; ?>, this)" 
                            class="wishlist-btn absolute bottom-3 right-3 w-9 h-9 rounded-full bg-white/90 backdrop-blur-sm flex items-center justify-center shadow-md z-10 <?php echo in_array($p['id'], $wishlist_items) ? 'active' : ''; ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="#9ca3af" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z" />
                            </svg>
                        </button>
                    </div>
                    <!-- Info Produk -->
                    <div class="p-4">
                        <h4 class="font-extrabold text-sm md:text-lg mb-1 line-clamp-1"><?php echo $p['nama_produk']; ?></h4>
                        <p class="text-gray-500 text-[10px] md:text-xs line-clamp-2 mb-2 min-h-[2.5rem]">
                            <?php echo !empty($p['deskripsi']) ? htmlspecialchars($p['deskripsi']) : 'Cita rasa autentik yang bikin nagih setiap hari.'; ?>
                        </p>
                        <p class="text-orange-500 font-black text-sm md:text-xl mb-4">Rp <?php echo number_format($p['harga']); ?></p>

                        <!-- Form Tambah ke Cart -->
                        <form method="POST" onsubmit="return handleAddToCartAnimation(this, event)" class="mt-4 flex flex-col md:flex-row gap-2 relative">
                            <input type="hidden" name="product_id" value="<?php echo $p['id']; ?>">
                            <input type="number" name="qty" value="1" min="1" max="<?php echo $p['stok']; ?>" 
                                   class="w-full md:w-20 bg-gray-50 p-2 text-center text-xs font-bold rounded-xl border border-gray-100 focus:outline-none" 
                                   <?php echo $p['stok'] <= 0 ? 'disabled' : ''; ?>>
                            
                            <?php if ($p['stok'] > 0): ?>
                                <button type="submit" name="add_to_cart" class="add-cart-btn w-full bg-orange-500 text-white font-bold py-2.5 rounded-xl text-xs hover:bg-orange-600 transition active:scale-95">+ Cart</button>
                            <?php else: ?>
                                <button type="button" disabled class="w-full bg-gray-100 text-gray-400 font-bold py-2.5 rounded-xl text-xs cursor-not-allowed">Habis</button>
                            <?php endif; ?>
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
                    <a href="home.php?kategori=Semua" class="mt-4 px-6 py-2 bg-orange-500 text-white rounded-xl font-bold hover:bg-orange-600 transition">
                        Lihat Semua Produk
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </main>
    <!-- --- AKHIR LIST PRODUK --- -->


    <!-- ==========================================
         G. FOOTER SECTION
         ========================================== -->
    <footer class="bg-gray-900 text-white py-12 scroll-reveal">
        <div class="max-w-7xl mx-auto flex flex-col items-center text-center">
            <h2 class="text-2xl font-bold italic mb-4">
                <span class="text-orange-500">Triguna</span><span class="text-green-600">FOOD</span>
            </h2>
            <p class="text-gray-400 text-sm max-w-xs mb-8">Solusi lapar kamu setiap hari dengan menu spesial dan harga terjangkau.</p>

            <a href="https://www.instagram.com/trigunafood/" class="flex items-center gap-2 px-6 py-3 bg-gradient-to-tr from-yellow-400 via-red-500 to-purple-600 text-white rounded-2xl font-bold text-sm shadow-lg hover:scale-105 transition">
                @trigunafood
            </a>
            <div class="w-full h-px bg-white/5 my-10"></div>
            <p class="text-gray-500 text-[10px] font-bold uppercase tracking-widest">&copy; 2024 TrigunaFOOD - All Rights Reserved</p>
        </div>
    </footer>
    <!-- --- AKHIR FOOTER --- -->


    <!-- ==========================================
         H. FLOATING ACTION BUTTON (CART TRIGGER)
         ========================================== -->
    <div class="fixed bottom-8 right-8 z-[60]">
        <button id="floating-cart-btn" onclick="toggleCart()" class="bg-green-600 text-white p-5 rounded-3xl shadow-2xl hover:scale-110 transition active:scale-90 relative">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-7 h-7">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007zM8.625 10.5a.375.375 0 11-.75 0 .375.375 0 01.75 0zm7.5 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
            </svg>
            <?php if ($cart_count > 0): ?>
                <span class="absolute -top-2 -right-2 bg-red-500 text-white text-[10px] w-6 h-6 rounded-full flex items-center justify-center font-black border-2 border-white"><?php echo $cart_count; ?></span>
            <?php endif; ?>
        </button>
    </div>
    <!-- --- AKHIR FLOATING BUTTON --- -->


    <!-- ==========================================
         I. MODAL KONFIRMASI LOGOUT
         ========================================== -->
    <div id="logout-modal" class="hidden fixed inset-0 z-[100] items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="closeLogoutConfirm()"></div>
        <div class="bg-white rounded-[2.5rem] p-8 z-10 max-w-sm w-full text-center shadow-2xl border border-gray-100">
            <h3 class="text-2xl font-black mb-2 text-gray-900">Konfirmasi Keluar</h3>
            <p class="text-gray-500 mb-8 font-medium">Yakin ingin keluar dari akun <b><?php echo htmlspecialchars($_SESSION['username']); ?></b>?</p>
            <div class="flex flex-col gap-3">
                <button onclick="proceedLogout()" class="w-full bg-red-600 text-white py-4 rounded-2xl font-extrabold hover:bg-red-700 shadow-lg shadow-red-100 transition">Ya, Keluar</button>
                <button onclick="closeLogoutConfirm()" class="w-full bg-gray-50 text-gray-600 py-4 rounded-2xl font-bold hover:bg-gray-100 transition">Batal</button>
            </div>
        </div>
    </div>
    <!-- --- AKHIR MODAL LOGOUT --- -->


    <!-- ==========================================
         K. MODAL KONFIRMASI CHECKOUT
         ========================================== -->
    <div id="checkout-modal" class="hidden fixed inset-0 z-[100] items-center justify-center p-4">
        <div class="absolute inset-0 checkout-modal-overlay" onclick="hideCheckoutModal()"></div>
        <div class="checkout-modal-content bg-white rounded-[2.5rem] p-8 z-10 max-w-sm w-full text-center shadow-2xl border border-gray-100 relative overflow-hidden">
            <!-- Sparkle container -->
            <div id="checkout-sparkles" class="absolute inset-0 pointer-events-none"></div>
            
            <!-- Icon -->
            <div class="relative z-10">
                <div class="w-20 h-20 bg-gradient-to-br from-green-400 to-emerald-600 rounded-full flex items-center justify-center mx-auto mb-5 shadow-lg shadow-green-200">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="white" class="w-10 h-10">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z" />
                    </svg>
                </div>
                
                <h3 class="text-2xl font-black text-gray-900 mb-2">Checkout Pesanan?</h3>
                <p class="text-gray-500 text-sm mb-6 leading-relaxed">Kamu akan melanjutkan ke halaman pembayaran untuk menyelesaikan pesanan.</p>
                
                <!-- Order Summary -->
                <div class="bg-gradient-to-r from-green-50 to-emerald-50 rounded-2xl p-4 mb-6 border border-green-100">
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-sm text-gray-600 font-medium">Total Item</span>
                        <span class="text-sm font-bold text-gray-800"><?php echo $cart_count; ?> menu</span>
                    </div>
                    <div class="h-px bg-green-200/50 my-2"></div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600 font-medium">Total Pembayaran</span>
                        <span class="text-lg font-black text-green-600">Rp <?php echo number_format($grand_total); ?></span>
                    </div>
                </div>
                
                <!-- Buttons -->
                <div class="flex flex-col gap-3">
                    <a href="checkout.php" class="checkout-pulse bg-gradient-to-r from-green-500 to-emerald-600 text-white py-4 rounded-2xl font-black text-center shadow-lg shadow-green-200 hover:shadow-xl hover:shadow-green-300 transition-all duration-300 hover:-translate-y-0.5 block">
                        🛒 Lanjut ke Checkout
                    </a>
                    <button onclick="hideCheckoutModal()" class="bg-gray-50 text-gray-500 py-3 rounded-2xl font-bold hover:bg-gray-100 transition">
                        Kembali Belanja
                    </button>
                </div>
            </div>
        </div>
    </div>
    <!-- --- AKHIR MODAL CHECKOUT --- -->


    <!-- ==========================================
         J. JAVASCRIPT FUNCTIONS
         ========================================== -->
    <script>
        // --- Bagian: Slider Banner ---
        const bannerWrapper = document.getElementById('banner-wrapper');
        const slides = document.querySelectorAll('.banner-slide');
        let currentSlide = 0;
        let isDragging = false;
        let startPos = 0;
        let currentTranslate = 0;
        let prevTranslate = 0;
        let animationID = 0;
        let autoSlideInterval;

        function updateIndicators(index) {
            for (let i = 1; i <= 4; i++) {
                const dot = document.querySelector(`.indicator-dot-${i}`);
                if (dot) {
                    dot.classList.remove('bg-white', 'w-6');
                    dot.classList.add('bg-white/30', 'w-2');
                }
            }
            const activeDot = document.querySelector(`.indicator-dot-${index + 1}`);
            if (activeDot) {
                activeDot.classList.add('bg-white', 'w-6');
                activeDot.classList.remove('bg-white/30', 'w-2');
            }
        }

        function setPositionByIndex() {
            currentTranslate = currentSlide * -100;
            prevTranslate = currentTranslate;
            if(bannerWrapper) bannerWrapper.style.transform = `translateX(${currentTranslate}%)`;
            updateIndicators(currentSlide);
        }

        function nextSlide() {
            if(slides.length > 0) {
                currentSlide = (currentSlide + 1) % slides.length;
                setPositionByIndex();
            }
        }

        function startAutoSlide() {
            autoSlideInterval = setInterval(nextSlide, 5000);
        }

        function stopAutoSlide() {
            clearInterval(autoSlideInterval);
        }

        if (bannerWrapper && slides.length > 0) {
            bannerWrapper.addEventListener('touchstart', touchStart);
            bannerWrapper.addEventListener('touchmove', touchMove);
            bannerWrapper.addEventListener('touchend', touchEnd);
            bannerWrapper.addEventListener('mousedown', touchStart);
            bannerWrapper.addEventListener('mousemove', touchMove);
            bannerWrapper.addEventListener('mouseleave', touchEnd);
            bannerWrapper.addEventListener('mouseup', touchEnd);
            bannerWrapper.addEventListener('contextmenu', e => e.preventDefault());

            function touchStart(event) {
                stopAutoSlide();
                isDragging = true;
                startPos = getPositionX(event);
                animationID = requestAnimationFrame(animation);
                bannerWrapper.style.transition = 'none';
            }

            function touchMove(event) {
                if (isDragging) {
                    const currentPosition = getPositionX(event);
                    const diff = ((currentPosition - startPos) / window.innerWidth) * 100;
                    currentTranslate = prevTranslate + diff;
                }
            }

            function touchEnd() {
                isDragging = false;
                cancelAnimationFrame(animationID);
                bannerWrapper.style.transition = 'transform 0.5s cubic-bezier(0.25, 0.46, 0.45, 0.94)';
                
                const movedBy = currentTranslate - prevTranslate;
                if (movedBy < -15 && currentSlide < slides.length - 1) currentSlide += 1;
                else if (movedBy > 15 && currentSlide > 0) currentSlide -= 1;
                else if (movedBy < -15 && currentSlide === slides.length - 1) currentSlide = 0;
                else if (movedBy > 15 && currentSlide === 0) currentSlide = slides.length - 1;

                setPositionByIndex();
                startAutoSlide();
            }

            function getPositionX(event) {
                return event.type.includes('mouse') ? event.pageX : event.touches[0].clientX;
            }

            function animation() {
                bannerWrapper.style.transform = `translateX(${currentTranslate}%)`;
                if (isDragging) requestAnimationFrame(animation);
            }

            setPositionByIndex();
            startAutoSlide();
        }
        // --- Selesai: Slider Banner ---

        // --- Bagian: Toggle Drawer (Cart & Mobile Nav) ---
        function toggleMobileNav() {
            const drawer = document.getElementById('mobile-nav-drawer');
            const overlay = document.getElementById('mobile-nav-overlay');
            drawer.classList.toggle('active');
            overlay.classList.toggle('hidden');
            document.body.style.overflow = drawer.classList.contains('active') ? 'hidden' : 'auto';
        }

        function toggleCart() {
            const drawer = document.getElementById('cart-drawer');
            const overlay = document.getElementById('cart-overlay');
            drawer.classList.toggle('active');
            overlay.classList.toggle('hidden');
            document.body.style.overflow = drawer.classList.contains('active') ? 'hidden' : 'auto';
        }
        // --- Selesai: Toggle Drawer ---

        // --- Bagian: Logout Management ---
        function showLogoutConfirm() {
            const modal = document.getElementById('logout-modal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.style.overflow = 'hidden';
        }

        function closeLogoutConfirm() {
            const modal = document.getElementById('logout-modal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.body.style.overflow = 'auto';
        }

        function proceedLogout() {
            window.location.href = 'logout.php';
        }
        // --- Selesai: Logout Management ---

        // --- Bagian: Notification Toast & Cart ---
        function showToast() {
            const toast = document.getElementById('toast-notification');
            toast.classList.add('show');
            setTimeout(() => { toast.classList.remove('show'); }, 3000);
        }

        function handleAddToCartAnimation(form, event) {
            event.preventDefault();
            const btn = form.querySelector('.add-cart-btn');
            const productCard = form.closest('.bg-white.rounded-\\[2rem\\]');
            const productImg = productCard.querySelector('img');
            const cartBtn = document.getElementById('floating-cart-btn');
            
            if (productImg && cartBtn) {
                const flyingImg = productImg.cloneNode(true);
                const rect = productImg.getBoundingClientRect();
                const cartRect = cartBtn.getBoundingClientRect();
                
                flyingImg.className = 'flying-img';
                flyingImg.style.left = rect.left + 'px';
                flyingImg.style.top = rect.top + 'px';
                flyingImg.style.width = rect.width + 'px';
                flyingImg.style.height = rect.height + 'px';
                
                document.body.appendChild(flyingImg);
                
                requestAnimationFrame(() => {
                    flyingImg.style.left = (cartRect.left + cartRect.width / 2 - 25) + 'px';
                    flyingImg.style.top = (cartRect.top + cartRect.height / 2 - 25) + 'px';
                    flyingImg.style.width = '50px';
                    flyingImg.style.height = '50px';
                    flyingImg.style.opacity = '0.5';
                    flyingImg.style.transform = 'scale(0.5)';
                });
                
                setTimeout(() => {
                    flyingImg.remove();
                    cartBtn.classList.add('cart-bounce');
                    setTimeout(() => {
                        cartBtn.classList.remove('cart-bounce');
                        
                        const hiddenInput = document.createElement('input');
                        hiddenInput.type = 'hidden';
                        hiddenInput.name = 'add_to_cart';
                        hiddenInput.value = '1';
                        form.appendChild(hiddenInput);
                        form.submit();
                    }, 500);
                }, 800);
            } else {
                form.submit();
            }
            return false;
        }

        function updateCartQty(cartId, change, btnElement) {
            const container = btnElement.closest('.flex-1');
            const qtyDisplay = container.querySelector('.qty-display');
            const subtotalDisplay = container.querySelector('.subtotal-display');
            
            let currentQty = parseInt(qtyDisplay.innerText);
            let newQty = currentQty + change;
            
            if (newQty < 1) return;
            
            // Animasi loading kecil
            btnElement.style.opacity = '0.5';
            btnElement.disabled = true;
            
            const formData = new FormData();
            formData.append('ajax_update_qty', '1');
            formData.append('cart_id', cartId);
            formData.append('qty', newQty);
            
            fetch('home.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                btnElement.style.opacity = '1';
                btnElement.disabled = false;
                
                if (data.status === 'success') {
                    // Update DOM
                    qtyDisplay.innerText = data.qty;
                    subtotalDisplay.innerText = 'Rp ' + new Intl.NumberFormat('id-ID').format(data.new_subtotal);
                    
                    const grandTotal = document.querySelector('.grand-total-display');
                    if(grandTotal) {
                        grandTotal.innerText = 'Rp ' + new Intl.NumberFormat('id-ID').format(data.grand_total);
                    }
                    
                    // Add subtle highlight animation
                    qtyDisplay.classList.add('text-green-500');
                    setTimeout(() => qtyDisplay.classList.remove('text-green-500'), 500);
                    
                    // Kalau turun ke 1, ubah button jadi hapus (reload lebih aman untuk hapus)
                    if (data.qty === 1) {
                        window.location.reload();
                    }
                } else {
                    alert(data.message || 'Gagal update quantity');
                }
            })
            .catch(err => {
                console.error(err);
                btnElement.style.opacity = '1';
                btnElement.disabled = false;
            });
        }
        // --- Selesai: Notification Toast & Cart ---

        // --- Bagian: Scroll Reveal Animation ---
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
        // --- Selesai: Scroll Reveal Animation ---

        // --- Bagian: Checkout Modal ---
        function showCheckoutModal() {
            const modal = document.getElementById('checkout-modal');
            if (!modal) return;
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.style.overflow = 'hidden';
            createSparkles();
        }

        function hideCheckoutModal() {
            const modal = document.getElementById('checkout-modal');
            if (!modal) return;
            const content = modal.querySelector('.checkout-modal-content');
            content.style.animation = 'checkoutPopOut 0.3s ease forwards';
            setTimeout(() => {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
                content.style.animation = '';
                document.body.style.overflow = document.getElementById('cart-drawer').classList.contains('active') ? 'hidden' : 'auto';
            }, 280);
        }

        function createSparkles() {
            const container = document.getElementById('checkout-sparkles');
            if (!container) return;
            container.innerHTML = '';
            const colors = ['#10b981', '#34d399', '#6ee7b7', '#fbbf24', '#f59e0b', '#a78bfa'];
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
        // --- Selesai: Checkout Modal ---

        // --- Bagian: Wishlist Toggle ---
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
        // --- Selesai: Wishlist Toggle ---

        // --- Window Load Initialization ---
        window.onload = function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('added')) {
                showToast();
                window.history.replaceState({}, document.title, window.location.pathname);
            }
            initScrollReveal();
        };
    </script>
    <!-- --- AKHIR JAVASCRIPT --- -->

</body>
</html>