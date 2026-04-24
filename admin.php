<?php
session_start();

// Set timezone ke Indonesia (WIB - Waktu Indonesia Barat)
date_default_timezone_set('Asia/Jakarta');

require_once 'config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
$slot = isset($_GET['slot']) ? intval($_GET['slot']) : 1;
$message = "";

// TAMBAH PRODUK
if (isset($_POST['add_product'])) {
    $nama = mysqli_real_escape_string($conn, $_POST['nama_produk']);
    $harga = $_POST['harga'];
    $stok = $_POST['stok'];
    $deskripsi = mysqli_real_escape_string($conn, $_POST['deskripsi']);
    $target_dir = "uploads/products/";
    if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);

    $filename = null;
    if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] === UPLOAD_ERR_OK) {
        $origName = basename($_FILES['gambar']['name']);
        $filename = time() . "_" . preg_replace('/[^A-Za-z0-9._-]/', '_', $origName);
        $target_path = $target_dir . $filename;
        if (!move_uploaded_file($_FILES['gambar']['tmp_name'], $target_path)) {
            $filename = null;
            $message = "Gagal upload gambar produk.";
        }
    }

    if ($filename) {
        $img_esc = mysqli_real_escape_string($conn, $filename);
        $query = "INSERT INTO products (nama_produk, harga, stok, deskripsi, gambar) VALUES ('$nama', '$harga', '$stok', '$deskripsi', '$img_esc')";
    } else {
        $query = "INSERT INTO products (nama_produk, harga, stok, deskripsi) VALUES ('$nama', '$harga', '$stok', '$deskripsi')";
    }

    if (mysqli_query($conn, $query)) {
        $message = "Produk berhasil ditambah!";
    }
}

// UPDATE PRODUK (Edit Semua Field Termasuk Gambar)
if (isset($_POST['update_product'])) {
    $id = intval($_POST['product_id']);
    $nama = mysqli_real_escape_string($conn, $_POST['nama_produk']);
    $harga = intval($_POST['harga']);
    $stok = intval($_POST['stok']);
    $deskripsi = mysqli_real_escape_string($conn, $_POST['deskripsi']);

    $query_update = "UPDATE products SET nama_produk='$nama', harga='$harga', stok='$stok', deskripsi='$deskripsi'";

    // Cek jika ada upload gambar baru
    if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] === UPLOAD_ERR_OK) {
        $target_dir = "uploads/products/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);

        $origName = basename($_FILES['gambar']['name']);
        $filename = time() . "_" . preg_replace('/[^A-Za-z0-9._-]/', '_', $origName);
        $target_path = $target_dir . $filename;

        if (move_uploaded_file($_FILES['gambar']['tmp_name'], $target_path)) {
            // Hapus gambar lama agar tidak menumpuk di server
            $old_data = mysqli_fetch_assoc(mysqli_query($conn, "SELECT gambar FROM products WHERE id = $id LIMIT 1"));
            if ($old_data && !empty($old_data['gambar']) && file_exists($target_dir . $old_data['gambar'])) {
                @unlink($target_dir . $old_data['gambar']);
            }

            $img_esc = mysqli_real_escape_string($conn, $filename);
            $query_update .= ", gambar='$img_esc'";
        }
    }

    $query_update .= " WHERE id = $id";

    if (mysqli_query($conn, $query_update)) {
        $message = "Detail produk berhasil diperbarui!";
    } else {
        $message = "Gagal memperbarui produk.";
    }
}

// UPLOAD BANNER
if (isset($_POST['upload_banner'])) {
    $position = isset($_POST['position']) ? intval($_POST['position']) : 1;

    $target_dir = "uploads/";
    if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);

    $filename = time() . "_" . basename($_FILES["img"]["name"]);
    $target_file = $target_dir . $filename;

    if (move_uploaded_file($_FILES["img"]["tmp_name"], $target_file)) {
        $existing = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM banners WHERE position = $position LIMIT 1"));
        if ($existing) {
            if (!empty($existing['img']) && file_exists($target_dir . $existing['img'])) {
                @unlink($target_dir . $existing['img']);
            }
            $query = "UPDATE banners SET img = '$filename' WHERE position = $position";
        } else {
            $query = "INSERT INTO banners (position, img) VALUES ($position, '$filename')";
        }

        if (mysqli_query($conn, $query)) {
            $message = "Banner slot $position berhasil diupdate!";
        }
    } else {
        $message = "Gagal upload gambar.";
    }
}

// DELETE BANNER
if (isset($_GET['delete_banner_pos'])) {
    $pos = intval($_GET['delete_banner_pos']);
    $row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM banners WHERE position = $pos LIMIT 1"));
    if ($row && !empty($row['img']) && file_exists('uploads/' . $row['img'])) @unlink('uploads/' . $row['img']);
    mysqli_query($conn, "DELETE FROM banners WHERE position = $pos");
    header("Location: admin.php?page=manage_banner&slot=$pos");
    exit();
}

// DELETE PRODUCT
if (isset($_GET['delete_product'])) {
    $id = intval($_GET['delete_product']);
    $row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT gambar FROM products WHERE id = $id LIMIT 1"));
    if ($row && !empty($row['gambar']) && file_exists('uploads/products/' . $row['gambar'])) @unlink('uploads/products/' . $row['gambar']);
    mysqli_query($conn, "DELETE FROM products WHERE id = $id");
    header("Location: admin.php?page=manage_cart");
    exit();
}

// STATISTIK
$count_user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM users"))['total'] ?? 0;
$count_product = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM products"))['total'] ?? 0;
$count_order = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM orders"))['total'] ?? 0;

// TOTAL PEMASUKAN - akumulasi semua total dari pesanan
$total_pemasukan_result = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(total), 0) as total FROM orders"));
$total_pemasukan = $total_pemasukan_result['total'] ?? 0;

// UPDATE ORDER STATUS
if (isset($_GET['update_order_status']) && isset($_GET['order_id'])) {
    $order_id = intval($_GET['order_id']);
    $raw_status = $_GET['status'] ?? 'Pending';
    $normalized = trim($raw_status);
    if (strtoupper($normalized) === 'ACC') {
        $normalized = 'Paid';
    }

    $allowed = ['Pending', 'Paid', 'Cancel'];
    $new_status = 'Pending';
    foreach ($allowed as $a) {
        if (strcasecmp($a, $normalized) === 0) {
            $new_status = $a;
            break;
        }
    }

    $new_status_esc = mysqli_real_escape_string($conn, $new_status);
    mysqli_query($conn, "UPDATE orders SET status = '$new_status_esc' WHERE id = $order_id");
    header('Location: admin.php?page=list_pesanan');
    exit();
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - TrigunaFOOD</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="icon" href="https://i.imgur.com/o6FBULN.jpeg">
    <style>
        * {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        .gradient-sidebar {
            background: linear-gradient(180deg, #10b981 0%, #065f46 100%);
        }

        .gradient-header {
            background: linear-gradient(135deg, #10b981 0%, #059669 50%, #f97316 100%);
        }

        .card-stat {
            background: linear-gradient(135deg, #ffffff 0%, #f0fdf4 100%);
            border: 1px solid #d1fae5;
        }

        .card-stat-green {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }

        .card-stat-orange {
            background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
        }

        .nav-active {
            background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
            box-shadow: 0 4px 12px rgba(249, 115, 22, 0.3);
        }

        .nav-item:hover:not(.nav-active) {
            background: rgba(16, 185, 129, 0.1);
        }

        .table-header {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }

        .status-pending {
            background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
            color: #78350f;
        }

        .status-paid {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }

        .status-cancel {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
        }

        .action-btn {
            transition: all 0.2s ease;
        }

        .action-btn:hover {
            transform: translateY(-1px);
        }

        .order-card {
            background: linear-gradient(to right, #f0fdf4 0%, #ffffff 100%);
            border: 1px solid #d1fae5;
        }
    </style>
</head>

<body class="bg-gradient-to-br from-green-50 via-white to-orange-50 min-h-screen"> 
    

    <!-- Sidebar -->
    <aside class="fixed left-0 top-0 h-full w-64 gradient-sidebar shadow-2xl z-40 hidden md:block">
        <div class="p-6 border-b border-green-700/20">
            <h1 class="text-2xl font-extrabold italic text-white">Triguna<span class="text-orange-300">FOOD</span></h1>
            <p class="text-xs text-green-200 mt-1 uppercase tracking-widest font-semibold">Admin Panel</p>
        </div>
        <nav class="flex-1 px-4 py-6 space-y-2">
            <a href="?page=dashboard" class="flex items-center gap-3 px-4 py-3 text-sm font-semibold rounded-xl transition text-white <?php echo $page === 'dashboard' ? 'nav-active' : 'hover:bg-white/10'; ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                </svg>
                Dashboard
            </a>
            <a href="?page=manage_cart" class="flex items-center gap-3 px-4 py-3 text-sm font-semibold rounded-xl transition text-white <?php echo $page === 'manage_cart' ? 'nav-active' : 'hover:bg-white/10'; ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z" />
                </svg>
                Manage Cart
            </a>
            <a href="?page=list_pengguna" class="flex items-center gap-3 px-4 py-3 text-sm font-semibold rounded-xl transition text-white <?php echo $page === 'list_pengguna' ? 'nav-active' : 'hover:bg-white/10'; ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
                List Pengguna
            </a>
            <a href="?page=manage_banner" class="flex items-center gap-3 px-4 py-3 text-sm font-semibold rounded-xl transition text-white <?php echo $page === 'manage_banner' ? 'nav-active' : 'hover:bg-white/10'; ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                Manage Banner
            </a>
            <a href="?page=list_pesanan" class="flex items-center gap-3 px-4 py-3 text-sm font-semibold rounded-xl transition text-white <?php echo $page === 'list_pesanan' ? 'nav-active' : 'hover:bg-white/10'; ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                </svg>
                List Pesanan
            </a>
        </nav>
        <div class="p-4 border-t border-green-700/20">
            <a href="#" onclick="showLogoutConfirm()" class="flex items-center gap-3 px-4 py-3 text-sm font-semibold text-red-300 hover:text-white hover:bg-red-500/20 rounded-xl transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                </svg>
                Keluar
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <div class="md:ml-64 flex flex-col min-h-screen">
        <!-- Header -->
        <header class="gradient-header px-6 md:px-8 py-4 shadow-lg">
            <div class="flex justify-between items-center">
                <div>
                    <h2 class="text-2xl font-extrabold text-white capitalize"><?php echo str_replace('_', ' ', $page); ?></h2>
                    <p class="text-green-100 text-sm font-medium">TrigunaFOOD Admin Control Panel</p>
                </div>
                <div class="flex items-center gap-4">
                    <div class="bg-white/20 backdrop-blur px-4 py-2 rounded-xl">
                        <span class="text-sm font-semibold text-white">Admin: <?php echo $_SESSION['username'] ?? 'Admin'; ?></span>
                    </div>
                </div>
            </div>
        </header>

        <!-- Content Area -->
        <main class="flex-1 p-6 md:p-8">
            <?php if ($message): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 px-4 py-3 rounded-r-xl mb-6 shadow-sm flex items-center gap-2">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <?php
            switch ($page) {
                case 'dashboard': ?>
                    <!-- Dashboard Stats -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                        <div class="card-stat p-6 rounded-2xl shadow-sm">
                            <div class="flex items-center justify-between mb-3">
                                <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center">
                                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                    </svg>
                                </div>
                            </div>
                            <p class="text-gray-500 text-sm font-semibold">Total Produk</p>
                            <h3 class="text-3xl font-extrabold mt-1 text-gray-800"><?php echo $count_product; ?></h3>
                        </div>
                        <div class="card-stat p-6 rounded-2xl shadow-sm">
                            <div class="flex items-center justify-between mb-3">
                                <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center">
                                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                    </svg>
                                </div>
                            </div>
                            <p class="text-gray-500 text-sm font-semibold">Total User</p>
                            <h3 class="text-3xl font-extrabold mt-1 text-gray-800"><?php echo $count_user; ?></h3>
                        </div>
                        <div class="card-stat p-6 rounded-2xl shadow-sm">
                            <div class="flex items-center justify-between mb-3">
                                <div class="w-12 h-12 bg-orange-100 rounded-xl flex items-center justify-center">
                                    <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                                    </svg>
                                </div>
                            </div>
                            <p class="text-gray-500 text-sm font-semibold">Total Pesanan</p>
                            <h3 class="text-3xl font-extrabold mt-1 text-orange-600"><?php echo $count_order; ?></h3>
                        </div>
                        <div class="card-stat-green p-6 rounded-2xl shadow-lg">
                            <div class="flex items-center justify-between mb-3">
                                <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center">
                                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                            </div>
                            <p class="text-green-100 text-sm font-semibold">Total Pemasukan</p>
                            <h3 class="text-2xl font-extrabold mt-1 text-white">Rp <?php echo number_format($total_pemasukan, 0, ',', '.'); ?></h3>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <h3 class="text-lg font-bold text-gray-800 mb-4">Aksi Cepat</h3>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <a href="?page=manage_cart" class="flex flex-col items-center gap-2 p-4 bg-green-50 rounded-xl hover:bg-green-100 transition border border-green-200">
                                <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                </svg>
                                <span class="text-sm font-semibold text-green-700">Tambah Produk</span>
                            </a>
                            <a href="?page=list_pesanan" class="flex flex-col items-center gap-2 p-4 bg-orange-50 rounded-xl hover:bg-orange-100 transition border border-orange-200">
                                <svg class="w-8 h-8 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                </svg>
                                <span class="text-sm font-semibold text-orange-700">Cek Pesanan</span>
                            </a>
                            <a href="?page=manage_banner" class="flex flex-col items-center gap-2 p-4 bg-blue-50 rounded-xl hover:bg-blue-100 transition border border-blue-200">
                                <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                <span class="text-sm font-semibold text-blue-700">Atur Banner</span>
                            </a>
                            <a href="?page=list_pengguna" class="flex flex-col items-center gap-2 p-4 bg-purple-50 rounded-xl hover:bg-purple-100 transition border border-purple-200">
                                <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                </svg>
                                <span class="text-sm font-semibold text-purple-700">List User</span>
                            </a>
                        </div>
                    </div>
                <?php break;

                case 'manage_cart': ?>
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-6">
                        <h3 class="text-lg font-bold mb-4 text-green-600 flex items-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                            </svg>
                            Tambah Produk Baru
                        </h3>
                        <form method="POST" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <input type="text" name="nama_produk" placeholder="Nama Produk" class="p-3 bg-green-50 border border-green-200 rounded-xl focus:ring-2 focus:ring-green-500 outline-none transition" required>
                            <input type="number" name="harga" placeholder="Harga (Rp)" class="p-3 bg-green-50 border border-green-200 rounded-xl focus:ring-2 focus:ring-green-500 outline-none transition" required>
                            <input type="number" name="stok" placeholder="Stok" class="p-3 bg-green-50 border border-green-200 rounded-xl focus:ring-2 focus:ring-green-500 outline-none transition" required>
                            <input type="text" name="deskripsi" placeholder="Deskripsi Singkat" class="p-3 bg-green-50 border border-green-200 rounded-xl focus:ring-2 focus:ring-green-500 outline-none transition">
                            <div class="md:col-span-2">
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Foto Produk</label>
                                <input type="file" name="gambar" class="w-full p-3 border border-green-200 bg-green-50 rounded-xl text-sm focus:ring-2 focus:ring-green-500 outline-none" accept="image/*" required>
                            </div>
                            <button type="submit" name="add_product" class="md:col-span-2 bg-gradient-to-r from-green-500 to-green-600 text-white py-3 rounded-xl font-bold hover:from-green-600 hover:to-green-700 transition shadow-lg shadow-green-200">Simpan Produk</button>
                        </form>
                    </div>

                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-100 bg-gradient-to-r from-green-50 to-emerald-50">
                            <h3 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                                <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                </svg>
                                Daftar Produk
                            </h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left">
                                <thead>
                                    <tr class="bg-gray-50 text-gray-500 text-sm border-b">
                                        <th class="py-4 px-6 font-bold">Foto</th>
                                        <th class="py-4 px-6 font-bold">Produk</th>
                                        <th class="py-4 px-6 font-bold">Harga</th>
                                        <th class="py-4 px-6 font-bold">Stok</th>
                                        <th class="py-4 px-6 font-bold text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $res = mysqli_query($conn, "SELECT * FROM products ORDER BY id DESC");
                                    while ($row = mysqli_fetch_assoc($res)): ?>
                                        <tr class="border-b border-gray-50 hover:bg-green-50/30 transition">
                                            <td class="py-4 px-6">
                                                <div class="w-14 h-14 rounded-xl overflow-hidden bg-white border border-gray-200 shadow-sm">
                                                    <?php if (!empty($row['gambar'])): ?>
                                                        <img src="uploads/products/<?php echo $row['gambar']; ?>" class="w-full h-full object-cover">
                                                    <?php else: ?>
                                                        <div class="w-full h-full flex items-center justify-center text-xs text-gray-400">No Img</div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td class="py-4 px-6">
                                                <p class="font-bold text-gray-800"><?php echo htmlspecialchars($row['nama_produk']); ?></p>
                                                <p class="text-xs text-gray-400 truncate w-48"><?php echo htmlspecialchars($row['deskripsi'] ?? '-'); ?></p>
                                            </td>
                                            <td class="py-4 px-6 font-bold text-green-600">Rp <?php echo number_format($row['harga']); ?></td>
                                            <td class="py-4 px-6">
                                                <span class="bg-orange-100 text-orange-700 px-3 py-1.5 rounded-full text-xs font-bold"><?php echo $row['stok']; ?> pcs</span>
                                            </td>
                                            <td class="py-4 px-6 text-center">
                                                <div class="flex items-center justify-center gap-2">
                                                    <button type="button"
                                                        data-id="<?php echo $row['id']; ?>"
                                                        data-nama="<?php echo htmlspecialchars($row['nama_produk'], ENT_QUOTES); ?>"
                                                        data-harga="<?php echo $row['harga']; ?>"
                                                        data-stok="<?php echo $row['stok']; ?>"
                                                        data-deskripsi="<?php echo htmlspecialchars($row['deskripsi'] ?? '', ENT_QUOTES); ?>"
                                                        onclick="openEditModal(this)"
                                                        class="action-btn text-blue-600 text-xs bg-blue-50 px-4 py-2 rounded-lg hover:bg-blue-100 font-bold transition border border-blue-100">
                                                        Edit
                                                    </button>
                                                    <a href="?page=manage_cart&delete_product=<?php echo $row['id']; ?>" class="action-btn text-red-600 text-xs bg-red-50 px-4 py-2 rounded-lg hover:bg-red-100 font-bold transition border border-red-100" onclick="return confirm('Hapus produk ini?')">Hapus</a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php break;

                case 'list_pengguna': ?>
                    <h3 class="text-lg font-bold mb-4">User Terdaftar</h3>
                    <table class="w-full text-left">
                        <thead>
                            <tr class="border-b text-gray-400 text-sm">
                                <th class="py-2">Username</th>
                                <th class="py-2">Email</th>
                                <th class="py-2">Role</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $res = mysqli_query($conn, "SELECT * FROM users");
                            while ($row = mysqli_fetch_assoc($res)): ?>
                                <tr class="border-b">
                                    <td class="py-3"><?php echo $row['username']; ?></td>
                                    <td><?php echo $row['email']; ?></td>
                                    <td><span class="px-2 py-1 rounded text-xs <?php echo (isset($row['role']) && $row['role'] == 'admin') ? 'bg-red-100 text-red-600' : 'bg-blue-100 text-blue-600'; ?>"><?php echo $row['role'] ?? 'user'; ?></span></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php break;

                case 'manage_banner': ?>
                    <?php $cur = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM banners WHERE position = $slot LIMIT 1")); ?>

                    <div class="flex space-x-2 mb-8 overflow-x-auto pb-2">
                        <?php for ($i = 1; $i <= 4; $i++): ?>
                            <a href="?page=manage_banner&slot=<?php echo $i; ?>" class="px-4 py-2 rounded-lg text-sm font-semibold transition <?php echo $slot == $i ? 'bg-orange-500 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'; ?>">Slot <?php echo $i; ?></a>
                        <?php endfor; ?>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                        <div class="md:col-span-1">
                            <h3 class="text-lg font-bold mb-4 text-orange-500">Upload Banner Slot <?php echo $slot; ?></h3>
                            <form method="POST" enctype="multipart/form-data" class="space-y-4">
                                <input type="hidden" name="position" value="<?php echo $slot; ?>">
                                <div>
                                    <label class="text-xs font-semibold text-gray-500">Gambar Banner</label>
                                    <input type="file" name="img" class="w-full p-2 border rounded-lg text-sm" <?php echo $cur ? '' : 'required'; ?> accept="image/*">
                                </div>
                                <button type="submit" name="upload_banner" class="w-full bg-orange-500 text-white py-2 rounded-lg font-bold">Update Slot <?php echo $slot; ?></button>
                                <?php if ($cur): ?>
                                    <a href="?page=manage_banner&delete_banner_pos=<?php echo $slot; ?>" class="block text-center text-sm text-red-500 hover:underline mt-2">Hapus Banner Ini</a>
                                <?php endif; ?>
                            </form>
                        </div>
                        <div class="md:col-span-2">
                            <h3 class="text-lg font-bold mb-4">Live Preview</h3>
                            <?php if ($cur): ?>
                                <div class="relative rounded-2xl overflow-hidden shadow-lg border h-64 bg-gray-200">
                                    <img src="uploads/<?php echo $cur['img']; ?>" class="w-full h-full object-cover">
                                </div>
                            <?php else: ?>
                                <div class="h-64 border-2 border-dashed rounded-2xl flex items-center justify-center text-gray-400">Belum ada banner di slot <?php echo $slot; ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php break;

                case 'list_pesanan':
                ?>
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                        <div class="px-6 py-5 border-b border-slate-100 bg-slate-50 flex justify-between items-center">
                            <h3 class="text-xl font-extrabold text-slate-800 tracking-tight">Data Pesanan Masuk</h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="bg-white border-b border-slate-200 text-slate-500 text-xs uppercase tracking-wider">
                                        <th class="px-6 py-4 font-bold">Order ID</th>
                                        <th class="px-6 py-4 font-bold">Pelanggan</th>
                                        <th class="px-6 py-4 font-bold">Kontak & Alamat</th>
                                        <th class="px-6 py-4 font-bold">Total</th>
                                        <th class="px-6 py-4 font-bold">Metode Pembayaran</th>
                                        <th class="px-6 py-4 font-bold">Bukti Pembayaran</th>
                                        <th class="px-6 py-4 font-bold">Status</th>
                                        <th class="px-6 py-4 font-bold">Waktu</th>
                                        <th class="px-6 py-4 font-bold">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <?php
                                    $res = mysqli_query($conn, "SELECT o.*, u.foto_profil FROM orders o LEFT JOIN users u ON o.username = u.username ORDER BY o.created_at DESC");
                                    while ($row = mysqli_fetch_assoc($res)):
                                        $waktu_pesanan = date('d F Y', strtotime($row['created_at']));
                                        $metode = isset($row['metode_pembayaran']) ? strtoupper($row['metode_pembayaran']) : 'COD';
                                        // Determine profile picture URL
                                        $profilePicUrl = 'https://commons.wikimedia.org/wiki/Special:FilePath/Profile_avatar_placeholder_large.png';
                                        if (!empty($row['foto_profil']) && file_exists("uploads/profiles/" . $row['foto_profil'])) {
                                            $profilePicUrl = "uploads/profiles/" . htmlspecialchars($row['foto_profil']);
                                        }
                                    ?>
                                        <tr class="hover:bg-slate-50 transition-colors duration-200">
                                            <td class="px-6 py-4 text-sm font-bold text-slate-700">#<?php echo $row['id']; ?></td>
                                            <td class="px-6 py-4 text-sm font-semibold">
                                                <div class="flex items-center gap-3">
                                                    <div class="w-10 h-10 rounded-full overflow-hidden border-2 border-indigo-200 bg-gray-100 flex-shrink-0 shadow-sm">
                                                        <img src="<?php echo $profilePicUrl; ?>" alt="<?php echo htmlspecialchars($row['username'] ?? ''); ?>" class="w-full h-full object-cover">
                                                    </div>
                                                    <span class="text-indigo-600"><?php echo htmlspecialchars($row['username'] ?? ''); ?></span>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="text-sm font-medium text-slate-800"><?php echo htmlspecialchars($row['telepon']); ?></div>
                                                <div class="text-xs text-slate-500 mt-1 max-w-[220px] break-words whitespace-normal leading-relaxed">
                                                    <?php echo htmlspecialchars($row['alamat']); ?>
                                                </div> 
                                            </td>
                                            <td class="px-6 py-4 text-sm font-bold text-emerald-600">Rp <?php echo number_format($row['total'], 0, ',', '.'); ?></td>
                                            <td class="px-6 py-4 text-sm">
                                                <?php
                                                if ($metode === 'QRISS') {
                                                    $badgeClass = 'bg-blue-100 text-blue-700 border-blue-200';
                                                } else {
                                                    $badgeClass = 'bg-green-100 text-green-700 border-green-200';
                                                }
                                                ?>
                                                <span class="px-3 py-1 rounded-full text-[11px] font-bold border <?php echo $badgeClass; ?> tracking-wide">
                                                    <?php echo $metode; ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 text-sm">
                                                <?php if ($metode === 'QRISS' && !empty($row['bukti_pembayaran'])): ?>
                                                    <div class="flex items-center gap-2">
                                                        <div class="w-10 h-10 rounded-lg overflow-hidden border border-slate-200 bg-slate-100 cursor-pointer hover:shadow-md transition" onclick="openBuktiModal('<?php echo $row['id']; ?>', '<?php echo htmlspecialchars($row['username'] ?? ''); ?>', 'uploads/bukti_pembayaran/<?php echo htmlspecialchars($row['bukti_pembayaran']); ?>')">
                                                            <img src="uploads/bukti_pembayaran/<?php echo htmlspecialchars($row['bukti_pembayaran']); ?>" alt="Bukti" class="w-full h-full object-cover">
                                                        </div>
                                                        <a href="uploads/bukti_pembayaran/<?php echo htmlspecialchars($row['bukti_pembayaran']); ?>" target="_blank" class="text-xs text-blue-600 hover:text-blue-800 font-semibold">Download</a>
                                                    </div>
                                                <?php elseif ($metode === 'QRISS'): ?>
                                                    <span class="text-xs text-slate-400 italic">Belum upload</span>
                                                <?php else: ?>
                                                    <span class="text-xs text-slate-400 italic">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4 text-sm">
                                                <?php
                                                $status = $row['status'];
                                                $badgeClass = $status == 'Paid' ? 'bg-emerald-100 text-emerald-700 border-emerald-200' : ($status == 'Cancel' ? 'bg-rose-100 text-rose-700 border-rose-200' :
                                                    'bg-amber-100 text-amber-700 border-amber-200');
                                                ?>
                                                <span class="px-3 py-1 rounded-full text-[11px] font-bold border <?php echo $badgeClass; ?> tracking-wide">
                                                    <?php echo htmlspecialchars($status); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 text-sm">
                                                <div class="font-medium text-slate-700"><?php echo $waktu_pesanan; ?></div>
                                                <div class="text-xs text-slate-500 font-semibold mt-0.5">Asia/Jakarta</div>
                                            </td>
                                            <td class="px-6 py-4 text-sm">
                                                <div class="flex flex-wrap gap-2 mb-3">
                                                    <a href="admin.php?page=list_pesanan&order_id=<?php echo $row['id']; ?>&status=Pending&update_order_status=1" class="text-xs px-3 py-1.5 bg-white text-amber-600 font-medium border border-amber-200 rounded-lg hover:bg-amber-50 transition shadow-sm">Pending</a>
                                                    <a href="admin.php?page=list_pesanan&order_id=<?php echo $row['id']; ?>&status=Paid&update_order_status=1" class="text-xs px-3 py-1.5 bg-white text-emerald-600 font-medium border border-emerald-200 rounded-lg hover:bg-emerald-50 transition shadow-sm">Paid</a>
                                                    <a href="admin.php?page=list_pesanan&order_id=<?php echo $row['id']; ?>&status=Cancel&update_order_status=1" class="text-xs px-3 py-1.5 bg-white text-rose-600 font-medium border border-rose-200 rounded-lg hover:bg-rose-50 transition shadow-sm">Cancel</a>
                                                </div>
                                                <details class="group relative">
                                                    <summary class="text-xs font-bold text-indigo-500 cursor-pointer hover:text-indigo-700 list-none inline-flex items-center gap-1">
                                                        Cek Items <span class="group-open:rotate-180 transition-transform duration-200">▾</span>
                                                    </summary>
                                                    <div class="absolute right-0 md:left-0 mt-2 w-64 p-3 bg-white rounded-xl border border-slate-200 shadow-xl z-10">
                                                        <?php
                                                        $items = json_decode($row['items'], true) ?: [];
                                                        foreach ($items as $it) {
                                                            echo "<div class='flex justify-between items-center border-b border-slate-100 last:border-0 py-2 gap-3'>";
                                                            echo "<div class='text-xs font-medium text-slate-700'>" . htmlspecialchars($it['nama_produk']) . " <span class='text-slate-400 ml-1'>x" . intval($it['jumlah']) . "</span></div>";
                                                            echo "<div class='text-xs font-bold text-slate-800'>Rp " . number_format($it['subtotal'], 0, ',', '.') . "</div>";
                                                            echo "</div>";
                                                        }
                                                        ?>
                                                    </div>
                                                </details>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
            <?php
                    break;

                default:
                    echo '<h3 class="text-lg font-bold">Dashboard Overview</h3><p class="text-gray-600">Gunakan sidebar untuk mengelola data TrigunaFOOD secara real-time.</p>';
            }
            ?>
        </main>
    </div>

    <!-- MODAL BUKTI PEMBAYARAN -->
    <div id="buktiModal" class="hidden fixed inset-0 z-[100] items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="closeBuktiModal()"></div>
        <div class="bg-white rounded-3xl p-8 z-10 max-w-2xl w-full shadow-2xl border border-gray-100 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h3 class="text-2xl font-bold text-gray-900">Bukti Pembayaran QRISS</h3>
                    <p class="text-sm text-slate-500 mt-1">Pesanan dari: <span id="buktiUsername" class="font-semibold text-slate-700"></span></p>
                </div>
                <button onclick="closeBuktiModal()" class="text-2xl text-gray-400 hover:text-gray-600 transition">&times;</button>
            </div>
            <div class="mb-4 rounded-xl overflow-hidden border border-gray-200 bg-gray-50">
                <img id="buktiImage" src="" alt="Bukti Pembayaran" class="w-full h-auto max-h-96 object-contain">
            </div>
            <div class="flex gap-3">
                <a id="buktiDownload" href="" target="_blank" class="flex-1 bg-blue-600 text-white py-3 rounded-xl font-semibold hover:bg-blue-700 transition text-center">
                    Download Foto
                </a>
                <button onclick="closeBuktiModal()" class="flex-1 bg-gray-100 text-gray-600 py-3 rounded-xl font-semibold hover:bg-gray-200 transition">
                    Tutup
                </button>
            </div>
        </div>
    </div>

    <!-- MODAL EDIT PRODUK (BARU) -->
    <div id="edit-modal" class="hidden fixed inset-0 z-[100] items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="closeEditModal()"></div>
        <div class="bg-white rounded-3xl p-8 z-10 max-w-md w-full shadow-2xl border border-gray-100 max-h-[90vh] overflow-y-auto">
            <h3 class="text-2xl font-bold mb-6 text-gray-900">Edit Produk</h3>
            <form method="POST" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" name="product_id" id="edit-id">

                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Nama Produk</label>
                    <input type="text" name="nama_produk" id="edit-nama" class="w-full p-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-orange-500 outline-none transition" required>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Harga (Rp)</label>
                        <input type="number" name="harga" id="edit-harga" class="w-full p-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-orange-500 outline-none transition" required>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Stok Tersedia</label>
                        <input type="number" name="stok" id="edit-stok" class="w-full p-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-orange-500 outline-none transition" required min="0">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Deskripsi</label>
                    <textarea name="deskripsi" id="edit-deskripsi" rows="3" class="w-full p-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-orange-500 outline-none transition resize-none"></textarea>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Ubah Gambar <span class="text-gray-400 font-normal">(Opsional)</span></label>
                    <input type="file" name="gambar" class="w-full p-2 border border-gray-200 bg-gray-50 rounded-xl text-sm" accept="image/*">
                    <p class="text-[10px] text-gray-400 mt-1 italic">*Biarkan kosong jika tidak ingin mengubah gambar.</p>
                </div>

                <div class="flex flex-col gap-3 mt-6">
                    <button type="submit" name="update_product" class="w-full bg-orange-500 text-white py-3.5 rounded-xl font-bold hover:bg-orange-600 transition active:scale-95 shadow-md shadow-orange-100">Simpan Perubahan</button>
                    <button type="button" onclick="closeEditModal()" class="w-full bg-gray-100 text-gray-600 py-3.5 rounded-xl font-bold hover:bg-gray-200 transition">Batal</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODERN LOGOUT MODAL -->
    <div id="logout-modal" class="hidden fixed inset-0 z-[100] items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="closeLogoutConfirm()"></div>
        <div class="bg-white rounded-[2.5rem] p-8 z-10 max-w-sm w-full text-center shadow-2xl transform transition-all border border-gray-100">
            <div class="w-20 h-20 bg-red-50 rounded-full flex items-center justify-center mx-auto mb-6">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-10 h-10 text-red-500">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6A2.25 2.25 0 005.25 5.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" />
                </svg>
            </div>
            <h3 class="text-2xl font-black mb-2 text-gray-900">Konfirmasi Keluar</h3>
            <p class="text-gray-500 mb-8 leading-relaxed font-medium">Apakah kamu yakin ingin keluar dari akun <b><?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?></b>?</p>
            <div class="flex flex-col gap-3">
                <button onclick="proceedLogout()" class="w-full bg-red-600 text-white py-4 rounded-2xl font-extrabold hover:bg-red-700 transition active:scale-95 shadow-lg shadow-red-100">Ya, Keluar</button>
                <button onclick="closeLogoutConfirm()" class="w-full bg-gray-50 text-gray-600 py-4 rounded-2xl font-bold hover:bg-gray-100 transition active:scale-95">Batal</button>
            </div>
        </div>
    </div>

</body>

</html>

<script>
    // JS Logic untuk Modal Edit Produk
    function openEditModal(btn) {
        // Ambil data dari attribute button
        document.getElementById('edit-id').value = btn.getAttribute('data-id');
        document.getElementById('edit-nama').value = btn.getAttribute('data-nama');
        document.getElementById('edit-harga').value = btn.getAttribute('data-harga');
        document.getElementById('edit-stok').value = btn.getAttribute('data-stok');
        document.getElementById('edit-deskripsi').value = btn.getAttribute('data-deskripsi');

        const modal = document.getElementById('edit-modal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function closeEditModal() {
        const modal = document.getElementById('edit-modal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    // Modal Bukti Pembayaran
    function openBuktiModal(orderId, username, imagePath) {
        document.getElementById('buktiUsername').textContent = username;
        document.getElementById('buktiImage').src = imagePath;
        document.getElementById('buktiDownload').href = imagePath;

        const modal = document.getElementById('buktiModal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function closeBuktiModal() {
        const modal = document.getElementById('buktiModal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    // Modal Logout
    function showLogoutConfirm() {
        const modal = document.getElementById('logout-modal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function closeLogoutConfirm() {
        const modal = document.getElementById('logout-modal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    function proceedLogout() {
        window.location.href = 'logout.php';
    }
</script>

</body>

</html>