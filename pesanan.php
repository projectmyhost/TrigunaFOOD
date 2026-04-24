<?php
session_start();

require_once 'config.php';

if (!isset($_SESSION['username']) && !isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'] ?? null;
$user_id = null;
if (isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id'])) {
    $user_id = intval($_SESSION['user_id']);
} elseif ($username) {
    $safe = mysqli_real_escape_string($conn, $username);
    $u = mysqli_query($conn, "SELECT id FROM users WHERE username = '$safe' LIMIT 1");
    if ($u && mysqli_num_rows($u) > 0) {
        $ur = mysqli_fetch_assoc($u);
        $user_id = intval($ur['id']);
    }
}

// Function to get profile picture URL
function getProfilePictureUrl($foto_profil) {
    if ($foto_profil && file_exists("uploads/profiles/$foto_profil")) {
        return "uploads/profiles/$foto_profil";
    }
    return "https://commons.wikimedia.org/wiki/Special:FilePath/Profile_avatar_placeholder_large.png";
}

// Fetch user profile data for navbar
$user_profile_pic = null;
$username_display = $username ?? 'Guest';
if ($user_id) {
    $profile_query = mysqli_query($conn, "SELECT foto_profil FROM users WHERE id = $user_id LIMIT 1");
    if ($profile_query && mysqli_num_rows($profile_query) > 0) {
        $profile_data = mysqli_fetch_assoc($profile_query);
        $user_profile_pic = $profile_data['foto_profil'] ?? null;
    }
}
$profile_pic_url = getProfilePictureUrl($user_profile_pic);
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesanan Saya - TrigunaFOOD</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

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

        /* Animasi Drawer (Nav & Cart) */
        .mobile-nav, .cart-drawer { 
            transform: translateX(100%); 
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); 
        }
        .mobile-nav.active, .cart-drawer.active { transform: translateX(0); }

        /* Animasi Modal Logout */
        #logout-modal.show { display: flex; animation: fadeIn 0.2s ease-out; }
        @keyframes fadeInNavbar { from { opacity: 0; } to { opacity: 1; } }

        .no-scroll {
            overflow: hidden;
        }

        .gradient-header {
            background: linear-gradient(135deg, #10b981 0%, #059669 50%, #f97316 100%);
        }

        .order-card {
            background: linear-gradient(to bottom, #ffffff 0%, #f9fafb 100%);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid #e5e7eb;
        }

        .order-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 16px 32px rgba(16, 185, 129, 0.15);
            border-color: #10b981;
        }

        .status-badge {
            font-weight: 700;
            letter-spacing: 0.05em;
            transition: all 0.3s ease;
        }

        .status-pending {
            background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
            color: #78350f;
            box-shadow: 0 4px 12px rgba(251, 191, 36, 0.3);
        }

        .status-paid,
        .status-acc {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: #ecfdf5;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        .status-cancel,
        .status-ditolak {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: #fef2f2;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        }

        .section-label {
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: #6b7280;
        }

        .detail-item {
            padding: 12px 0;
            border-bottom: 1px solid #e5e7eb;
            transition: background 0.2s ease;
        }

        .detail-item:last-child {
            border-bottom: none;
        }

        .detail-item:hover {
            background: #f3f4f6;
            padding: 12px 8px;
            border-radius: 6px;
        }

        .price-highlight {
            background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 800;
        }

        .empty-state {
            animation: slideUpFade 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
            opacity: 0;
        }

        /* Enhanced Animations */
        .page-enter {
            animation: slideUpFade 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
            opacity: 0;
        }

        .stagger-item {
            opacity: 0;
            transform: translateY(15px);
            animation: slideUpFade 0.5s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        .stagger-1 { animation-delay: 0.1s; }
        .stagger-2 { animation-delay: 0.2s; }
        .stagger-3 { animation-delay: 0.3s; }
        .stagger-4 { animation-delay: 0.4s; }
        .stagger-5 { animation-delay: 0.5s; }

        @keyframes slideUpFade {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .back-button {
            background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
            transition: all 0.3s ease;
        }

        .back-button:hover {
            transform: translateX(-2px);
            box-shadow: 0 8px 16px rgba(249, 115, 22, 0.3);
        }

        .order-id {
            background: linear-gradient(135deg, #10b981 0%, #f97316 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .items-container {
            background: linear-gradient(to right, #f3f4f6 0%, #f9fafb 50%, #f3f4f6 100%);
            border-radius: 12px;
            padding: 16px;
        }

        /* Mobile Optimizations */
        @media (max-width: 768px) {
            .order-card {
                padding: 4px;
                margin: 0;
            }

            .order-card-header {
                padding: 1rem;
                border-bottom: 1px solid #e5e7eb;
            }

            .order-card-content {
                padding: 1rem;
            }

            .mobile-header-row {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                gap: 1rem;
                margin-bottom: 0.5rem;
            }

            .mobile-order-id {
                font-size: 1.125rem;
                font-weight: 700;
                flex: 1;
            }

            .mobile-status {
                flex-shrink: 0;
                font-size: 0.75rem;
                padding: 0.5rem 0.75rem !important;
            }

            .mobile-info-grid {
                display: grid;
                grid-template-columns: 1fr;
                gap: 0.75rem;
                margin-bottom: 0;
            }

            .mobile-info-item {
                padding: 0.75rem 0;
                border-bottom: 1px solid #e5e7eb;
            }

            .mobile-info-item:last-child {
                border-bottom: none;
            }

            .mobile-info-label {
                font-size: 0.65rem;
                font-weight: 700;
                text-transform: uppercase;
                color: #6b7280;
                margin-bottom: 0.25rem;
                display: flex;
                align-items: center;
                gap: 0.5rem;
            }

            .mobile-info-value {
                font-size: 0.95rem;
                color: #374151;
                font-weight: 500;
                line-height: 1.3;
            }

            .mobile-payment-badge {
                display: inline-block;
                font-size: 0.7rem;
                padding: 0.375rem 0.5rem;
                border-radius: 0.375rem;
                margin-top: 0.25rem;
            }

            .mobile-total {
                background: #fef7ee;
                padding: 0.75rem;
                border-radius: 0.75rem;
                margin-top: 0.5rem;
            }

            .mobile-total-label {
                font-size: 0.65rem;
                text-transform: uppercase;
                color: #6b7280;
                font-weight: 700;
                margin-bottom: 0.25rem;
            }

            .mobile-total-price {
                font-size: 1.5rem;
                font-weight: 800;
                color: #f97316;
            }

            .mobile-items-section {
                margin-top: 0.75rem;
                padding-top: 0.75rem;
                border-top: 1px solid #e5e7eb;
            }

            .mobile-items-header {
                font-size: 0.65rem;
                text-transform: uppercase;
                font-weight: 700;
                color: #6b7280;
                margin-bottom: 0.5rem;
                display: flex;
                align-items: center;
                gap: 0.5rem;
            }

            .mobile-items-container {
                background: #f9fafb;
                border-radius: 0.5rem;
                padding: 0.75rem;
                border: 1px solid #e5e7eb;
            }

            .mobile-item {
                padding: 0.5rem 0;
                border-bottom: 1px solid #e5e7eb;
            }

            .mobile-item:last-child {
                border-bottom: none;
            }

            .mobile-item-name {
                font-size: 0.9rem;
                font-weight: 600;
                color: #1f2937;
                margin-bottom: 0.25rem;
            }

            .mobile-item-details {
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 0.5rem;
            }

            .mobile-item-qty {
                font-size: 0.75rem;
                color: #6b7280;
            }

            .mobile-item-qty strong {
                color: #10b981;
                font-weight: 700;
            }

            .mobile-item-price {
                font-size: 0.85rem;
                font-weight: 700;
                color: #f97316;
            }

            .mobile-date {
                font-size: 0.75rem;
                color: #6b7280;
                font-weight: 500;
            }
        }
    </style>
</head>

<body class="bg-gradient-to-br from-slate-50 via-white to-emerald-50 min-h-screen">

    <!-- ==========================================
         NAVIGATION BAR (HEADER)
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
         MOBILE MENU DRAWER (SIDEBAR KANAN)
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

    <!-- Logout Modal -->
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

    <!-- Header Section -->
    <div class="page-enter gradient-header py-12 md:py-16 px-4 md:px-8 relative overflow-hidden">
        <div class="absolute top-0 right-0 w-64 h-64 bg-white opacity-5 rounded-full -mr-32 -mt-32"></div>
        <div class="absolute bottom-0 left-0 w-48 h-48 bg-white opacity-5 rounded-full -ml-24 -mb-24"></div>

        <div class="max-w-5xl mx-auto relative z-10">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-4xl md:text-5xl font-bold text-white mb-2">Pesanan Saya</h1>
                    <p class="text-emerald-50 text-sm md:text-base font-medium">Pantau status pesanan makanan Anda secara real-time</p>
                </div>
                <a href="home.php" class="back-button hidden md:flex items-center gap-2 px-6 py-3 rounded-xl text-white font-semibold text-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                    Kembali
                </a>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="px-4 md:px-8 py-8 md:py-12">
        <div class="max-w-5xl mx-auto">
            <?php
            if (!$user_id) {
                echo '
                <div class="empty-state">
                    <div class="bg-white rounded-3xl shadow-lg border-2 border-emerald-100 p-12 md:p-16 text-center">
                        <div class="mb-6">
                            <svg class="w-20 h-20 mx-auto text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <p class="text-gray-500 font-medium text-lg">User tidak dikenali</p>
                        <p class="text-gray-400 text-sm mt-2">Silakan login ulang untuk melanjutkan</p>
                        <a href="login.php" class="inline-block mt-6 px-6 py-2.5 bg-gradient-to-r from-emerald-500 to-emerald-600 text-white rounded-lg font-semibold hover:shadow-lg transition">
                            Login Kembali
                        </a>
                    </div>
                </div>
                ';
            } else {
                $query = "SELECT * FROM orders WHERE user_id = $user_id ORDER BY created_at DESC";
                $res = mysqli_query($conn, $query);

                if (mysqli_num_rows($res) > 0) {
                    echo '<div class="grid gap-6">';
                    $order_count = 0;
                    while ($row = mysqli_fetch_assoc($res)) {
                        $order_count++;
                        $status = $row['status'];
                        $status_lower = strtolower($status);

                        if ($status_lower === 'paid' || $status_lower === 'acc') {
                            $status_class = "status-paid";
                            $status_icon = "✓";
                        } elseif ($status_lower === 'cancel' || $status_lower === 'ditolak') {
                            $status_class = "status-cancel";
                            $status_icon = "✕";
                        } else {
                            $status_class = "status-pending";
                            $status_icon = "⏱";
                        }
                        
                        // Calculate stagger index (max 5)
                        $stagger_idx = min($order_count, 5);
            ?>

                        <div class="order-card rounded-2xl md:p-8 stagger-item stagger-<?php echo $stagger_idx; ?>">
                            <!-- Desktop Header Row -->
                            <div class="hidden md:flex flex-row items-center justify-between gap-4 pb-6 border-b border-gray-200">
                                <div class="flex-1">
                                    <h2 class="order-id text-2xl md:text-3xl font-bold mb-1">Pesanan #<?php echo $row['id']; ?></h2>
                                    <p class="text-sm text-gray-500 font-medium">
                                        <?php echo date('d F Y', strtotime($row['created_at'])); ?> ·
                                        <?php echo date('H:i', strtotime($row['created_at'])); ?>
                                    </p>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="text-2xl <?php echo $status_class; ?> status-badge px-4 py-2 rounded-full inline-flex items-center gap-2">
                                        <span><?php echo $status_icon; ?></span>
                                        <span><?php echo strtoupper($status); ?></span>
                                    </span>
                                </div>
                            </div>

                            <!-- Mobile Header (Simplified) -->
                            <div class="md:hidden order-card-header">
                                <div class="mobile-header-row">
                                    <div>
                                        <div class="mobile-order-id">Pesanan #<?php echo $row['id']; ?></div>
                                        <div class="mobile-date">
                                            <?php echo date('d/m/y', strtotime($row['created_at'])); ?> 
                                            <?php echo date('H:i', strtotime($row['created_at'])); ?>
                                        </div>
                                    </div>
                                    <span class="text-sm <?php echo $status_class; ?> status-badge mobile-status px-2 py-1.5 rounded-full inline-flex items-center gap-1">
                                        <span><?php echo $status_icon; ?></span>
                                        <span><?php echo strtoupper($status); ?></span>
                                    </span>
                                </div>
                            </div>

                            <!-- Desktop Info Grid -->
                            <div class="hidden md:grid grid-cols-1 md:grid-cols-2 gap-6 py-6 border-b border-gray-200">
                                <!-- Payment Method -->
                                <div class="md:col-span-2">
                                    <p class="section-label mb-2 flex items-center gap-2">
                                        <svg class="w-4 h-4 text-emerald-600" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M4 4a2 2 0 00-2 2v4a2 2 0 002 2V6h10a2 2 0 00-2-2H4zm2 6a2 2 0 012-2h8a2 2 0 012 2v4a2 2 0 01-2 2H8a2 2 0 01-2-2v-4zm6 4a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/>
                                        </svg>
                                        Metode Pembayaran
                                    </p>
                                    <?php 
                                    $metode = isset($row['metode_pembayaran']) ? $row['metode_pembayaran'] : 'COD';
                                    if ($metode === 'QRISS') {
                                        $badge_class = 'bg-blue-100 text-blue-700';
                                        $display_text = 'QRISS ALLPAY';
                                    } else {
                                        $badge_class = 'bg-green-100 text-green-700';
                                        $display_text = 'CASH ONLY / COD';
                                    }
                                    ?>
                                    <p class="text-gray-700 font-bold text-lg">
                                        <span class="<?php echo $badge_class; ?> px-4 py-2 rounded-lg inline-flex items-center gap-2">
                                            <?php echo $display_text; ?>
                                        </span>
                                    </p>
                                </div>

                                <!-- Address -->
                                <div class="group">
                                    <p class="section-label mb-2 flex items-center gap-2">
                                        <svg class="w-4 h-4 text-emerald-600" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd" />
                                        </svg>
                                        Alamat Pengiriman
                                    </p>
                                    <p class="text-gray-700 font-medium leading-relaxed"><?php echo htmlspecialchars($row['alamat']); ?></p>
                                </div>

                                <!-- Phone -->
                                <div class="group">
                                    <p class="section-label mb-2 flex items-center gap-2">
                                        <svg class="w-4 h-4 text-emerald-600" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773c.26.559.657 1.438 1.284 2.064.627.627 1.505 1.024 2.064 1.284l.773-1.548a1 1 0 011.06-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 4 14.18 4 9.5V5a1 1 0 011-1h2.153z" />
                                        </svg>
                                        Nomor Telepon
                                    </p>
                                    <p class="text-gray-700 font-medium"><?php echo htmlspecialchars($row['telepon']); ?></p>
                                </div>

                                <!-- Total -->
                                <div class="group md:col-span-2">
                                    <p class="section-label mb-2 flex items-center gap-2">
                                        <svg class="w-4 h-4 text-emerald-600" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M4 4a2 2 0 00-2 2v4a2 2 0 002 2V6h10a2 2 0 00-2-2H4zm2 6a2 2 0 012-2h8a2 2 0 012 2v4a2 2 0 01-2 2H8a2 2 0 01-2-2v-4zm6 4a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd" />
                                        </svg>
                                        Total Pembayaran
                                    </p>
                                    <p class="price-highlight text-3xl font-bold">Rp <?php echo number_format($row['total']); ?></p>
                                </div>
                            </div>

                            <!-- Mobile Info Grid (Simplified) -->
                            <div class="md:hidden order-card-content">
                                <div class="mobile-info-grid">
                                    <!-- Payment Method Mobile -->
                                    <div class="mobile-info-item">
                                        <div class="mobile-info-label">
                                            <svg class="w-3.5 h-3.5 text-emerald-600" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M4 4a2 2 0 00-2 2v4a2 2 0 002 2V6h10a2 2 0 00-2-2H4zm2 6a2 2 0 012-2h8a2 2 0 012 2v4a2 2 0 01-2 2H8a2 2 0 01-2-2v-4zm6 4a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/>
                                            </svg>
                                            Pembayaran
                                        </div>
                                        <?php 
                                        $metode = isset($row['metode_pembayaran']) ? $row['metode_pembayaran'] : 'COD';
                                        if ($metode === 'QRISS') {
                                            $badge_class = 'bg-blue-100 text-blue-700';
                                            $display_text = 'QRISS';
                                        } else {
                                            $badge_class = 'bg-green-100 text-green-700';
                                            $display_text = 'COD';
                                        }
                                        ?>
                                        <span class="<?php echo $badge_class; ?> mobile-payment-badge">
                                            <?php echo $display_text; ?>
                                        </span>
                                    </div>

                                    <!-- Address Mobile -->
                                    <div class="mobile-info-item">
                                        <div class="mobile-info-label">
                                            <svg class="w-3.5 h-3.5 text-emerald-600" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd" />
                                            </svg>
                                            Alamat
                                        </div>
                                        <p class="mobile-info-value"><?php echo htmlspecialchars($row['alamat']); ?></p>
                                    </div>

                                    <!-- Phone Mobile -->
                                    <div class="mobile-info-item">
                                        <div class="mobile-info-label">
                                            <svg class="w-3.5 h-3.5 text-emerald-600" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773c.26.559.657 1.438 1.284 2.064.627.627 1.505 1.024 2.064 1.284l.773-1.548a1 1 0 011.06-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 4 14.18 4 9.5V5a1 1 0 011-1h2.153z" />
                                            </svg>
                                            No. Telepon
                                        </div>
                                        <p class="mobile-info-value"><?php echo htmlspecialchars($row['telepon']); ?></p>
                                    </div>

                                    <!-- Total Mobile -->
                                    <div class="mobile-total">
                                        <div class="mobile-total-label">Total Pembayaran</div>
                                        <div class="mobile-total-price">Rp <?php echo number_format($row['total']); ?></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Desktop Items Section -->
                            <div class="hidden md:block pt-6">
                                <p class="section-label mb-4 flex items-center gap-2">
                                    <svg class="w-4 h-4 text-emerald-600" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M3 1a1 1 0 000 2h1.22l.305 1.222a.997.997 0 00.01.042l1.358 5.43-.893.892C3.74 11.846 4.632 14 6.414 14H15a1 1 0 000-2H6.414l1-1H14a1 1 0 00.894-.553l3-6A1 1 0 0017 6H6.28l-.31-1.243A1 1 0 005 4H3z" />
                                        <path fill-rule="evenodd" d="M16 16a2 2 0 11-4 0 2 2 0 014 0zM6 16a2 2 0 11-4 0 2 2 0 014 0z" clip-rule="evenodd" />
                                    </svg>
                                    Detail Item Pesanan
                                </p>

                                <div class="items-container">
                                    <div class="space-y-0">
                                        <?php
                                        $items = json_decode($row['items'], true) ?: [];
                                        foreach ($items as $index => $it):
                                        ?>
                                            <div class="detail-item flex items-center justify-between">
                                                <div class="flex-1">
                                                    <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($it['nama_produk']); ?></p>
                                                    <p class="text-xs text-gray-500 mt-1">Jumlah: <span class="font-bold text-emerald-600">x<?php echo intval($it['jumlah']); ?></span></p>
                                                </div>
                                                <div class="text-right">
                                                    <p class="font-bold text-orange-600">Rp <?php echo number_format($it['subtotal']); ?></p>
                                                    <p class="text-xs text-gray-500">/ item</p>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Mobile Items Section (Simplified) -->
                            <div class="md:hidden mobile-items-section">
                                <div class="mobile-items-header">
                                    <svg class="w-3.5 h-3.5 text-emerald-600" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M3 1a1 1 0 000 2h1.22l.305 1.222a.997.997 0 00.01.042l1.358 5.43-.893.892C3.74 11.846 4.632 14 6.414 14H15a1 1 0 000-2H6.414l1-1H14a1 1 0 00.894-.553l3-6A1 1 0 0017 6H6.28l-.31-1.243A1 1 0 005 4H3z" />
                                    </svg>
                                    Item Pesanan
                                </div>
                                <div class="mobile-items-container">
                                    <div class="space-y-0">
                                        <?php
                                        $items = json_decode($row['items'], true) ?: [];
                                        foreach ($items as $index => $it):
                                        ?>
                                            <div class="mobile-item">
                                                <div class="mobile-item-name"><?php echo htmlspecialchars($it['nama_produk']); ?></div>
                                                <div class="mobile-item-details">
                                                    <span class="mobile-item-qty">x<strong><?php echo intval($it['jumlah']); ?></strong></span>
                                                    <span class="mobile-item-price">Rp <?php echo number_format($it['subtotal']); ?></span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

            <?php
                    }
                    echo '</div>';
                } else {
                    echo '
                    <div class="empty-state">
                        <div class="bg-white rounded-3xl shadow-lg border-2 border-emerald-100 p-12 md:p-16 text-center">
                            <div class="mb-6">
                                <svg class="w-24 h-24 mx-auto text-gray-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                                </svg>
                            </div>
                            <p class="text-gray-700 font-bold text-xl">Belum Ada Pesanan</p>
                            <p class="text-gray-500 text-sm mt-2 mb-6">Mulai memesan makanan lezat sekarang juga!</p>
                            <a href="home.php" class="inline-block px-8 py-3 bg-gradient-to-r from-emerald-500 to-emerald-600 text-white rounded-xl font-semibold hover:shadow-lg transition">
                                Lihat Menu
                            </a>
                        </div>
                    </div>
                    ';
                }
            }
            ?>
        </div>
    </div>

    <!-- Back Button Mobile -->
    

    <!-- JavaScript Navbar -->
    <script>
        function toggleMobileNav() {
            const drawer = document.getElementById('mobile-nav-drawer');
            const overlay = document.getElementById('mobile-nav-overlay');
            drawer.classList.toggle('active');
            overlay.classList.toggle('hidden');
            document.body.style.overflow = drawer.classList.contains('active') ? 'hidden' : 'auto';
        }

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
    </script>

</body>

</html>