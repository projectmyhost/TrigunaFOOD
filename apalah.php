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
// --- AKHIR LOGIKA KERANJANG ---


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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="icon" href="https://i.imgur.com/o6FBULN.jpeg">

</head>
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
        .banner-slide { display: none; }
        .banner-active { display: block; }

        /* Animasi Drawer (Nav & Cart) */
        .mobile-nav, .cart-drawer { 
            transform: translateX(100%); 
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); 
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
                background: #ddd;
                border-radius: 2px;
            }
        }
    </style>
<body>
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

    <script>
        // --- Bagian: Slider Banner ---
        let slideIndex = 0;
        const slides = document.getElementsByClassName("banner-slide");

        function updateIndicators(index) {
            for (let i = 1; i <= 4; i++) {
                const dot = document.querySelector(`.indicator-dot-${i}`);
                if (dot) { dot.classList.remove('bg-white', 'w-6'); dot.classList.add('bg-white/30', 'w-2'); }
            }
            const activeDot = document.querySelector(`.indicator-dot-${index}`);
            if (activeDot) { activeDot.classList.add('bg-white', 'w-6'); activeDot.classList.remove('bg-white/30', 'w-2'); }
        }

        function showSlides() {
            for (let i = 0; i < slides.length; i++) slides[i].classList.remove("banner-active");
            slideIndex++;
            if (slideIndex > slides.length) slideIndex = 1;
            if (slides.length > 0) {
                slides[slideIndex - 1].classList.add("banner-active");
                updateIndicators(slideIndex);
            }
            setTimeout(showSlides, 5000); // Ganti slide setiap 5 detik
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

        // --- Bagian: Notification Toast ---
        function showToast() {
            const toast = document.getElementById('toast-notification');
            toast.classList.add('show');
            setTimeout(() => { toast.classList.remove('show'); }, 3000);
        }
        // --- Selesai: Notification Toast ---

        // --- Window Load Initialization ---
        window.onload = function() {
            showSlides();
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('added')) {
                showToast();
                window.history.replaceState({}, document.title, window.location.pathname);
            }
        };
    </script>
</body>
</html>