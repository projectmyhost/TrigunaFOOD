<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (!isset($conn) && file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}

$user_id = null;
if (isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id'])) {
    $user_id = intval($_SESSION['user_id']);
} elseif (isset($_SESSION['username']) && isset($conn)) {
    $username_safe = mysqli_real_escape_string($conn, $_SESSION['username']);
    $u = mysqli_query($conn, "SELECT id FROM users WHERE username = '$username_safe' LIMIT 1");
    if ($u && mysqli_num_rows($u) > 0) {
        $user_row = mysqli_fetch_assoc($u);
        $user_id = intval($user_row['id']);
    }
}

function getProfilePictureUrl($foto_profil) {
    if ($foto_profil && file_exists(__DIR__ . "/uploads/profiles/$foto_profil")) {
        return "uploads/profiles/$foto_profil";
    }
    return "https://commons.wikimedia.org/wiki/Special:FilePath/Profile_avatar_placeholder_large.png";
}

$username_display = isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Tamu';
$profile_pic_url = getProfilePictureUrl(null);

if (isset($conn) && $user_id) {
    $user_profile = mysqli_query($conn, "SELECT username, foto_profil FROM users WHERE id = $user_id LIMIT 1");
    $user_data = $user_profile ? mysqli_fetch_assoc($user_profile) : null;
    $username_display = htmlspecialchars($user_data['username'] ?? $_SESSION['username']);
    $profile_pic_url = getProfilePictureUrl($user_data['foto_profil'] ?? null);
    $cart_items = mysqli_query($conn, "SELECT cart.*, products.nama_produk, products.harga, products.stok, products.gambar FROM cart JOIN products ON cart.product_id = products.id WHERE cart.user_id = '$user_id'");
    $cart_count = $cart_items ? mysqli_num_rows($cart_items) : 0;
} else {
    if (isset($conn)) {
        $cart_items = mysqli_query($conn, "SELECT * FROM cart WHERE 0");
    } else {
        $cart_items = [];
    }
    $cart_count = 0;
}

$current = basename($_SERVER['PHP_SELF'] ?? '');
?>

<!-- Shared Navigation (copied/adapted from home.php) -->
<nav class="bg-white/80 backdrop-blur-md shadow-sm sticky top-0 z-50 px-5 py-4 flex justify-between items-center border-b border-gray-100">
    <h1 class="text-2xl font-extrabold tracking-tight italic"><span class="text-orange-500">Triguna</span><span class="text-green-600">FOOD</span></h1>

    <div class="flex items-center gap-4">
        <div class="hidden md:flex space-x-4 items-center">
            <div class="flex items-center gap-3">
                <img src="<?php echo $profile_pic_url; ?>" alt="Profile" class="navbar-profile-pic" title="<?php echo $username_display; ?>">
                <span class="text-sm font-semibold text-gray-600">Halo, <?php echo $username_display; ?></span>
            </div>
            <?php if ($user_id): ?>
                <a href="profile.php" class="bg-blue-50 text-blue-600 px-5 py-2.5 rounded-xl text-sm font-bold border border-blue-100 hover:bg-blue-100 transition">Profile</a>
                <a href="pesanan.php" class="bg-orange-600 text-white px-5 py-2.5 rounded-xl text-sm font-bold shadow-lg shadow-orange-100 hover:bg-orange-700 transition">Pesanan</a>
                <button onclick="showLogoutConfirm()" class="bg-red-50 text-red-600 px-5 py-2.5 rounded-xl text-sm font-bold border border-red-100 hover:bg-red-100 transition">Logout</button>
            <?php else: ?>
                <a href="login.php" class="bg-blue-50 text-blue-600 px-5 py-2.5 rounded-xl text-sm font-bold border border-blue-100 hover:bg-blue-100 transition">Login</a>
                <a href="register.php" class="bg-green-50 text-green-600 px-5 py-2.5 rounded-xl text-sm font-bold border border-green-100 hover:bg-green-100 transition">Daftar</a>
            <?php endif; ?>
        </div>

        <button onclick="toggleMobileNav()" class="md:hidden p-2 text-gray-700">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-7 h-7">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
            </svg>
        </button>
    </div>
</nav>

<!-- Mobile Drawer -->
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
        <div class="mb-8 text-center">
            <img src="<?php echo $profile_pic_url; ?>" alt="Profile" class="mobile-menu-profile">
            <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Akun Saya</p>
            <p class="text-lg font-black text-gray-800"><?php echo $username_display; ?></p>
        </div>
        <div class="space-y-4">
            <?php if ($current !== 'home.php' && $current !== 'index.php'): ?>
                <a href="home.php" class="flex items-center gap-3 font-bold text-gray-700 hover:text-orange-500 transition">⬅️ Kembali ke Home</a>
            <?php endif; ?>
            <a href="home.php" class="flex items-center gap-3 font-bold text-gray-700 hover:text-orange-500 transition">Home</a>
            <?php if ($user_id): ?>
                <a href="profile.php" class="flex items-center gap-3 font-bold text-gray-700 hover:text-blue-500 transition">Profile Saya</a>
                <a href="pesanan.php" class="flex items-center gap-3 font-bold text-gray-700 hover:text-orange-500 transition">Pesanan Saya</a>
                <button onclick="showLogoutConfirm(); toggleMobileNav();" class="flex items-center gap-3 font-bold text-red-500 transition w-full">Logout</button>
            <?php else: ?>
                <a href="login.php" class="flex items-center gap-3 font-bold text-gray-700 hover:text-blue-500 transition">Login</a>
                <a href="register.php" class="flex items-center gap-3 font-bold text-gray-700 hover:text-green-500 transition">Daftar</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Cart Drawer -->
<div id="cart-overlay" class="hidden fixed inset-0 bg-black/60 z-[80] backdrop-blur-sm" onclick="toggleCart()"></div>
<div id="cart-drawer" class="cart-drawer fixed top-0 right-0 w-full md:w-[400px] h-full bg-white z-[90] shadow-2xl flex flex-col">
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
    <div class="flex-1 overflow-y-auto p-6 space-y-4 md:space-y-4 mobile-cart-list gap-3">
        <?php
        $grand_total = 0;
        if ($cart_count > 0 && $cart_items instanceof mysqli_result) {
            mysqli_data_seek($cart_items, 0);
            while ($item = mysqli_fetch_assoc($cart_items)) {
                $grand_total += $item['subtotal'];
        ?>
                <div class="flex gap-3 p-2 bg-gray-50 rounded-2xl border border-gray-100 md:p-3">
                    <div class="w-12 h-12 md:w-20 md:h-20 bg-gray-200 rounded-xl overflow-hidden shrink-0">
                        <img src="uploads/products/<?php echo $item['gambar'] ?? 'default.jpg'; ?>" onerror="this.src='https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=200'" class="w-full h-full object-cover md:object-cover object-contain">
                    </div>
                    <div class="flex-1 flex flex-col justify-between py-1">
                        <div class="flex justify-between items-start">
                            <h4 class="text-sm font-bold text-gray-800 leading-tight"><?php echo $item['nama_produk']; ?></h4>
                            <a href="?remove=<?php echo $item['id']; ?>" class="text-gray-300 hover:text-red-500 transition ml-2">✖</a>
                        </div>
                        <div class="flex justify-between items-end">
                            <span class="text-xs font-semibold text-gray-500">x<?php echo $item['jumlah']; ?></span>
                            <span class="text-sm font-bold text-orange-500">Rp <?php echo number_format($item['subtotal']); ?></span>
                        </div>
                    </div>
                </div>
        <?php
            }
        } else {
        ?>
            <div class="h-full flex flex-col items-center justify-center text-center opacity-40 py-20">
                <p class="text-lg">Wah, belum ada makanan nih.</p>
            </div>
        <?php } ?>
    </div>
    <?php if ($cart_count > 0): ?>
        <div class="p-6 border-t bg-gray-50/50 backdrop-blur-md">
            <div class="flex justify-between items-center mb-6">
                <span class="text-gray-500 font-medium">Total Pembayaran</span>
                <span class="text-2xl font-extrabold text-orange-600">Rp <?php echo number_format($grand_total); ?></span>
            </div>
            <a href="checkout.php" class="w-full bg-green-600 text-white py-4 rounded-2xl font-bold text-center block shadow-lg hover:bg-green-700 transition">Checkout Sekarang</a>
        </div>
    <?php endif; ?>
</div>

<script>
function toggleMobileNav(){
    document.getElementById('mobile-nav-overlay').classList.toggle('hidden');
    document.getElementById('mobile-nav-drawer').classList.toggle('active');
}
function toggleCart(){
    document.getElementById('cart-overlay').classList.toggle('hidden');
    document.getElementById('cart-drawer').classList.toggle('active');
}
function showLogoutConfirm(){
    if(confirm('Anda yakin ingin logout?')){ window.location.href='logout.php'; }
}
</script>
