<?php
// ==========================================
// 1. SESSION & DATABASE CONNECTION
// ==========================================
session_start();
require_once 'config.php';

// Protect page - redirect if not logged in
if (!isset($_SESSION['user_id']) && !isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// Resolve user_id
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

if (empty($user_id) || $user_id <= 0) {
    header("Location: login.php");
    exit();
}

// Auto-migrate: Add profile columns if not exist
$checkColumns = mysqli_query($conn, "SELECT * FROM information_schema.COLUMNS WHERE TABLE_NAME='users' AND COLUMN_NAME IN ('nomor_hp', 'alamat', 'foto_profil')");
if (!$checkColumns || mysqli_num_rows($checkColumns) < 3) {
    @mysqli_query($conn, "ALTER TABLE users ADD COLUMN nomor_hp VARCHAR(20) DEFAULT NULL");
    @mysqli_query($conn, "ALTER TABLE users ADD COLUMN alamat TEXT DEFAULT NULL");
    @mysqli_query($conn, "ALTER TABLE users ADD COLUMN foto_profil VARCHAR(255) DEFAULT NULL");
}

$success_msg = null;
$error_msg = null;
$default_avatar_url = "https://commons.wikimedia.org/wiki/Special:FilePath/Profile_avatar_placeholder_large.png";

// Function to get profile picture URL
function getProfilePictureUrl($foto_profil) {
    if ($foto_profil && file_exists("uploads/profiles/$foto_profil")) {
        return "uploads/profiles/$foto_profil";
    }
    return "https://commons.wikimedia.org/wiki/Special:FilePath/Profile_avatar_placeholder_large.png";
}

// ==========================================
// 2. HANDLE FORM SUBMISSION
// ==========================================
if (isset($_POST['delete_profile_photo'])) {
    $currentPhotoRes = mysqli_query($conn, "SELECT foto_profil FROM users WHERE id = $user_id LIMIT 1");
    if ($currentPhotoRes && mysqli_num_rows($currentPhotoRes) > 0) {
        $currentPhoto = mysqli_fetch_assoc($currentPhotoRes);
        if (!empty($currentPhoto['foto_profil'])) {
            $currentPath = "uploads/profiles/" . $currentPhoto['foto_profil'];
            if (file_exists($currentPath)) {
                @unlink($currentPath);
            }
        }
    }
    if (mysqli_query($conn, "UPDATE users SET foto_profil = NULL WHERE id = $user_id")) {
        $success_msg = "Foto profil berhasil dihapus dan kembali ke avatar default.";
    } else {
        $error_msg = "Gagal menghapus foto profil. Silakan coba lagi.";
    }
} elseif (isset($_POST['update_profile'])) {
    $nomor_hp = isset($_POST['nomor_hp']) ? trim($_POST['nomor_hp']) : '';
    $alamat = isset($_POST['alamat']) ? trim($_POST['alamat']) : '';

    // Validation
    if (empty($nomor_hp) || empty($alamat)) {
        $error_msg = "Nomor HP dan Alamat tidak boleh kosong!";
    } elseif (strlen($alamat) > 500) {
        $error_msg = "Alamat terlalu panjang (maksimal 500 karakter)!";
    } else {
        // Handle photo upload
        $foto_filename = null;
        $upload_error = false;

        if (isset($_FILES['foto_profil']) && $_FILES['foto_profil']['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['foto_profil']['error'] === UPLOAD_ERR_OK) {
                $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
                $max_size = 5 * 1024 * 1024; // 5MB

                if (!in_array($_FILES['foto_profil']['type'], $allowed_types)) {
                    $error_msg = "Format foto hanya boleh JPG atau PNG!";
                    $upload_error = true;
                } elseif ($_FILES['foto_profil']['size'] > $max_size) {
                    $error_msg = "Ukuran foto maksimal 5MB!";
                    $upload_error = true;
                } else {
                    $target_dir = "uploads/profiles/";
                    if (!is_dir($target_dir)) {
                        mkdir($target_dir, 0777, true);
                    }

                    $file_ext = strtolower(pathinfo($_FILES['foto_profil']['name'], PATHINFO_EXTENSION));
                    $filename = $user_id . "_" . time() . "." . $file_ext;
                    $target_path = $target_dir . $filename;

                    if (move_uploaded_file($_FILES['foto_profil']['tmp_name'], $target_path)) {
                        // Delete old photo if exists
                        $oldPhotoRes = mysqli_query($conn, "SELECT foto_profil FROM users WHERE id = $user_id LIMIT 1");
                        if ($oldPhotoRes && mysqli_num_rows($oldPhotoRes) > 0) {
                            $oldPhoto = mysqli_fetch_assoc($oldPhotoRes);
                            if (!empty($oldPhoto['foto_profil'])) {
                                $oldPath = "uploads/profiles/" . $oldPhoto['foto_profil'];
                                if (file_exists($oldPath)) {
                                    @unlink($oldPath);
                                }
                            }
                        }
                        $foto_filename = $filename;
                    } else {
                        $error_msg = "Gagal upload foto. Silakan coba lagi!";
                        $upload_error = true;
                    }
                }
            } else {
                $error_msg = "Terjadi kesalahan upload. Silakan coba lagi!";
                $upload_error = true;
            }
        }

        // If no upload error, proceed with profile update
        if (!$upload_error) {
            $nomor_hp_safe = mysqli_real_escape_string($conn, $nomor_hp);
            $alamat_safe = mysqli_real_escape_string($conn, $alamat);

            if ($foto_filename) {
                $foto_safe = mysqli_real_escape_string($conn, $foto_filename);
                $update_query = "UPDATE users SET nomor_hp = '$nomor_hp_safe', alamat = '$alamat_safe', foto_profil = '$foto_safe' WHERE id = $user_id";
            } else {
                $update_query = "UPDATE users SET nomor_hp = '$nomor_hp_safe', alamat = '$alamat_safe' WHERE id = $user_id";
            }

            if (mysqli_query($conn, $update_query)) {
                $success_msg = "Profil Anda berhasil diperbarui!";
            } else {
                $error_msg = "Gagal menyimpan data. Silakan coba lagi!";
            }
        }
    }
}

// ==========================================
// 3. FETCH USER DATA
// ==========================================
$userRes = mysqli_query($conn, "SELECT username, email, nomor_hp, alamat, foto_profil FROM users WHERE id = $user_id LIMIT 1");
$user = $userRes ? mysqli_fetch_assoc($userRes) : null;

if (!$user) {
    header("Location: login.php");
    exit();
}

$username = htmlspecialchars($user['username']);
$email = htmlspecialchars($user['email']);
$nomor_hp = htmlspecialchars($user['nomor_hp'] ?? '');
$alamat = htmlspecialchars($user['alamat'] ?? '');
$foto_profil = $user['foto_profil'] ?? null;
$user_profile_pic = $foto_profil;
$username_display = $username;
$profile_pic_url = getProfilePictureUrl($foto_profil);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="icon" href="https://i.imgur.com/o6FBULN.jpeg">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css">

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

        /* Loading Animation */
        .loading-spinner {
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }   
        }

        .cropper-view-box,
        .cropper-face {
            border-radius: 50%;
            outline: 2px solid #10b981;
            outline-offset: -1px;
        }

        .cropper-line,
        .cropper-point {
            background-color: #10b981;
        }

        .cropper-modal {
            background-color: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(4px);
        }

        .no-scroll {
            overflow: hidden;
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
        .stagger-6 { animation-delay: 0.6s; }

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
    </style>
</head>

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

    <!-- Main Content Container -->
    <div class="min-h-screen flex flex-col items-center justify-start p-4 md:p-8 space-y-4 bg-slate-50">
    
    <!-- Notifikasi Sukses Atas -->
    <div id="successAlert" class="w-full max-w-xl bg-[#f0fdf4] border border-[#bbf7d0] text-[#15803d] p-4 rounded-2xl flex items-start gap-3 shadow-sm transition-all duration-300 opacity-0 transform translate-y-[-10px] pointer-events-none hidden">
        <div class="p-1 bg-[#dcfce7] rounded-full text-[#16a34a] mt-0.5">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"></path>
            </svg>
        </div>
        <div>
            <h4 class="font-bold text-sm">Profil Anda berhasil diperbarui!</h4>
            <p class="text-xs text-[#16a34a]/90 mt-0.5">Data profil Anda telah tersimpan.</p>
        </div>
    </div>

    <!-- Notifikasi Error -->
    <div id="errorAlert" class="w-full max-w-xl bg-[#fef2f2] border border-[#fecaca] text-[#991b1b] p-4 rounded-2xl flex items-start gap-3 shadow-sm transition-all duration-300 opacity-0 transform translate-y-[-10px] pointer-events-none hidden">
        <div class="p-1 bg-[#fee2e2] rounded-full text-[#dc2626] mt-0.5">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </div>
        <div>
            <h4 class="font-bold text-sm">Terjadi Kesalahan</h4>
            <p class="text-xs text-[#dc2626]/90 mt-0.5" id="errorMessage"></p>
        </div>
    </div>

    <!-- Header Banner Gradient -->
    <div class="page-enter w-full max-w-xl bg-gradient-to-r from-[#f97316] via-[#ea580c] to-[#10b981] rounded-3xl p-8 text-white text-center shadow-md relative overflow-hidden flex items-center justify-between">
        <div class="relative z-10 flex-1">
            <h1 class="text-3xl font-extrabold tracking-tight">Profil Saya</h1>
            <p class="text-sm opacity-90 mt-1.5 font-medium">Kelola informasi dan foto profil Anda</p>
        </div>
        
        <a href="home.php" class="hidden md:flex items-center gap-1.5 bg-white/20 hover:bg-white/30 transition-all px-4 py-2 rounded-xl text-sm font-semibold">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
            </svg>
            Kembali
        </a>
        <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full blur-2xl -mr-16 -mt-16"></div>
    </div>

    <!-- Main Card Form -->
    <div class="page-enter w-full max-w-xl bg-white rounded-3xl shadow-sm border border-slate-100 p-6 md:p-8 space-y-8" style="animation-delay: 0.1s;">

        <!-- Section Foto Profil -->
        <div class="space-y-6">
            <h3 class="text-xs font-bold text-slate-500 tracking-wider uppercase">FOTO PROFIL</h3>

            <!-- Avatar Bulat -->
            <div class="flex justify-center">
                <div class="relative group cursor-pointer" id="avatarContainer">
                    <div class="w-32 h-32 rounded-full overflow-hidden border border-slate-200 bg-slate-100 shadow-inner relative flex items-center justify-center">
                        <img id="profilePreview" src="<?php
                                                        if ($foto_profil && file_exists("uploads/profiles/$foto_profil")) {
                                                            echo "uploads/profiles/$foto_profil";
                                                        } else {
                                                            echo $default_avatar_url;
                                                        }
                                                        ?>" alt="Pratinjau" class="w-full h-full object-cover">
                        <div class="absolute inset-0 bg-black/40 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                            <span class="text-white text-xs font-semibold tracking-wide">Ubah Foto</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="flex justify-center">
                <form id="deletePhotoForm" method="POST">
                    <input type="hidden" name="delete_profile_photo" value="1">
                    <button type="button" id="deletePhotoBtn" class="text-sm font-semibold text-red-600 hover:text-red-800 transition">Hapus Foto Profil</button>
                </form>
            </div>

            <!-- Upload Dropzone Box -->
            <div id="dropzone" class="border-2 border-dashed border-[#10b981]/60 bg-[#f0fdf4]/40 hover:bg-[#f0fdf4]/80 transition-all rounded-2xl p-6 text-center cursor-pointer flex flex-col items-center justify-center space-y-1">
                <input type="file" id="imageInput" accept="image/*" class="hidden">
                <span class="font-bold text-slate-800 text-sm">Foto</span>
                <span class="text-xs text-slate-500">Klik atau seret foto ke sini</span>
                <span class="text-[10px] text-slate-400">Format: JPG, PNG | Maksimal: 5MB</span>
            </div>
        </div>

        <hr class="border-slate-100">

        <!-- Input Form Fields -->
        <form id="profileForm" method="POST" enctype="multipart/form-data" class="space-y-5">
            <div class="space-y-2 stagger-item stagger-1">
                <label for="username" class="text-xs font-bold text-slate-500 uppercase tracking-wider">Nama Pengguna</label>
                <input type="text" id="username" value="<?php echo $username; ?>" readonly class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 cursor-not-allowed outline-none transition-all text-sm text-slate-600 font-semibold">
            </div>

            <div class="space-y-2 stagger-item stagger-2">
                <label for="email" class="text-xs font-bold text-slate-500 uppercase tracking-wider">Email</label>
                <input type="email" id="email" value="<?php echo $email; ?>" readonly class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 cursor-not-allowed outline-none transition-all text-sm text-slate-600 font-semibold">
            </div>

            <div class="space-y-2 stagger-item stagger-3">
                <label for="phone" class="text-xs font-bold text-slate-500 uppercase tracking-wider">Nomor Telepon</label>
                <input type="text" id="phone" name="nomor_hp" value="<?php echo $nomor_hp; ?>" required class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 hover:bg-slate-100/50 focus:bg-white focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100 outline-none transition-all text-sm text-slate-800">
                <p class="text-[10px] text-slate-400">Nomor ini akan digunakan untuk pengiriman pesanan.</p>
            </div>

            <div class="space-y-2 stagger-item stagger-4">
                <label for="address" class="text-xs font-bold text-slate-500 uppercase tracking-wider">Alamat Lengkap</label>
                <textarea id="address" name="alamat" rows="3" required class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 hover:bg-slate-100/50 focus:bg-white focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100 outline-none transition-all text-sm text-slate-800 resize-none"><?php echo $alamat; ?></textarea>
                <p class="text-[10px] text-slate-400">Maksimal 500 karakter. Alamat ini akan digunakan untuk pengiriman.</p>
            </div>

            <input type="hidden" name="update_profile" value="1">
            <!-- Hidden input untuk cropped image -->
            <input type="file" id="croppedImageInput" name="foto_profil" class="hidden" accept="image/*">

            <div class="pt-2 flex gap-3 stagger-item stagger-5">
                <button type="submit" name="update_profile" value="1" class="flex-1 py-4 bg-[#059669] hover:bg-[#047857] active:bg-[#065f46] text-white rounded-2xl text-sm font-bold tracking-wide transition-all shadow-md shadow-emerald-100 hover:shadow-lg hover:-translate-y-1">
                    Simpan Perubahan
                </button>
            </div>
        </form>
    </div>

    <!-- OVERLAY POPUP: Image Cropper Modal (Circle Design) -->
    <div id="cropperModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 opacity-0 pointer-events-none transition-opacity duration-300">
        <div class="absolute inset-0 bg-slate-950/85 backdrop-blur-md"></div>

        <div class="relative bg-white w-full max-w-lg rounded-3xl shadow-2xl overflow-hidden border border-slate-100 flex flex-col transform scale-95 transition-transform duration-300" id="modalContent">

            <div class="p-6 border-b border-slate-100 flex justify-between items-center bg-slate-50">
                <div>
                    <h3 class="text-md font-bold text-slate-900">Sesuaikan Foto Profil</h3>
                    <p class="text-[11px] text-slate-500">Geser atau zoom agar wajah pas di dalam lingkaran.</p>
                </div>
                <button type="button" id="btnCloseModal" class="p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-xl transition-all">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <div class="p-6 flex-1 flex flex-col items-center justify-center bg-slate-900 min-h-[280px] max-h-[400px]">
                <div class="w-full h-full max-h-[320px] overflow-hidden rounded-xl flex items-center justify-center">
                    <img id="cropperImage" class="max-w-full block" src="" alt="Sumber Gambar">
                </div>
            </div>

            <div class="px-6 py-4 bg-slate-50 border-t border-b border-slate-100 flex justify-center gap-2 flex-wrap">
                <button type="button" id="btnZoomIn" class="px-3 py-2 bg-white hover:bg-slate-100 border border-slate-200 text-slate-700 rounded-xl transition-all flex items-center gap-1.5 text-xs font-semibold">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v6m3-3H7"></path>
                    </svg>
                    Zoom In
                </button>
                <button type="button" id="btnZoomOut" class="px-3 py-2 bg-white hover:bg-slate-100 border border-slate-200 text-slate-700 rounded-xl transition-all flex items-center gap-1.5 text-xs font-semibold">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM4 10h12"></path>
                    </svg>
                    Zoom Out
                </button>
                <button type="button" id="btnRotateLeft" class="px-3 py-2 bg-white hover:bg-slate-100 border border-slate-200 text-slate-700 rounded-xl transition-all flex items-center gap-1.5 text-xs font-semibold">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3"></path>
                    </svg>
                    Rotasi
                </button>
                <button type="button" id="btnResetCropper" class="px-3 py-2 bg-rose-50 hover:bg-rose-100 border border-rose-100 text-rose-600 rounded-xl transition-all flex items-center gap-1.5 text-xs font-semibold">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"></path>
                    </svg>
                    Reset
                </button>
            </div>

            <div class="p-6 bg-white flex justify-end gap-3">
                <button type="button" id="btnCancelCropper" class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold transition-all">
                    Batal
                </button>
                <button type="button" id="btnSaveCropped" class="px-5 py-2.5 bg-[#059669] hover:bg-[#047857] text-white rounded-xl text-xs font-bold transition-all flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"></path>
                    </svg>
                    Simpan Foto
                </button>
            </div>
        </div>
    </div>

    <!-- Modal Konfirmasi Modern -->
    <div id="confirmDeleteModal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-sm">
        <div class="w-full max-w-md rounded-3xl bg-white border border-slate-200 shadow-2xl overflow-hidden">
            <div class="p-6">
                <div class="flex items-start gap-4">
                    <div class="mt-1 flex h-12 w-12 items-center justify-center rounded-2xl bg-red-50 text-red-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.25 19.5a9 9 0 1113.5 0L12 21l-6.75-1.5z"></path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900">Konfirmasi Hapus Foto Profil</h3>
                        <p class="text-sm text-slate-500 mt-2">Foto profil akan dihapus dan diganti dengan avatar default. Aksi ini tidak dapat dibatalkan.</p>
                    </div>
                </div>
            </div>
            <div class="px-6 py-4 bg-slate-50 flex flex-col gap-3 sm:flex-row sm:justify-end">
                <button type="button" id="cancelDeleteBtn" class="w-full sm:w-auto px-4 py-3 rounded-2xl bg-slate-100 text-slate-700 hover:bg-slate-200 transition font-semibold">Batal</button>
                <button type="button" id="confirmDeleteBtn" class="w-full sm:w-auto px-4 py-3 rounded-2xl bg-red-600 text-white hover:bg-red-700 transition font-semibold">Hapus Foto</button>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>
    <script>
        // --- Bagian: Toggle Drawer (Mobile Nav) ---
        function toggleMobileNav() {
            const drawer = document.getElementById('mobile-nav-drawer');
            const overlay = document.getElementById('mobile-nav-overlay');
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

        let cropper = null;
        let originalFileType = "image/png";
        let selectedFile = null;

        const imageInput = document.getElementById('imageInput');
        const cropperModal = document.getElementById('cropperModal');
        const modalContent = document.getElementById('modalContent');
        const cropperImage = document.getElementById('cropperImage');
        const profilePreview = document.getElementById('profilePreview');
        const avatarContainer = document.getElementById('avatarContainer');
        const dropzone = document.getElementById('dropzone');
        const successAlert = document.getElementById('successAlert');
        const errorAlert = document.getElementById('errorAlert');
        const profileForm = document.getElementById('profileForm');
        const croppedImageInput = document.getElementById('croppedImageInput');

        const btnCloseModal = document.getElementById('btnCloseModal');
        const btnCancelCropper = document.getElementById('btnCancelCropper');
        const btnSaveCropped = document.getElementById('btnSaveCropped');

        const btnZoomIn = document.getElementById('btnZoomIn');
        const btnZoomOut = document.getElementById('btnZoomOut');
        const btnRotateLeft = document.getElementById('btnRotateLeft');
        const btnResetCropper = document.getElementById('btnResetCropper');

        /* Event Click Handlers */
        avatarContainer.addEventListener('click', () => imageInput.click());
        dropzone.addEventListener('click', () => imageInput.click());

        /* Drag and Drop Handlers untuk Area Dropzone */
        ['dragenter', 'dragover'].forEach(eventName => {
            dropzone.addEventListener(eventName, (e) => {
                e.preventDefault();
                dropzone.classList.add('bg-[#dcfce7]/60');
            }, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropzone.addEventListener(eventName, (e) => {
                e.preventDefault();
                dropzone.classList.remove('bg-[#dcfce7]/60');
            }, false);
        });

        dropzone.addEventListener('drop', (e) => {
            const dt = e.dataTransfer;
            const files = dt.files;
            if (files && files.length > 0) {
                handleFileSelect(files[0]);
            }
        });

        imageInput.addEventListener('change', function(e) {
            const files = e.target.files;
            if (files && files.length > 0) {
                handleFileSelect(files[0]);
            }
        });

        function handleFileSelect(file) {
            originalFileType = file.type;
            selectedFile = file;
            const reader = new FileReader();
            reader.onload = function(e) {
                cropperImage.src = e.target.result;
                openModal();
            };
            reader.readAsDataURL(file);
        }

        function openModal() {
            document.body.classList.add('no-scroll');
            cropperModal.classList.remove('pointer-events-none', 'opacity-0');
            cropperModal.classList.add('opacity-100');
            modalContent.classList.remove('scale-95');
            modalContent.classList.add('scale-100');
            setTimeout(initializeCropper, 300);
        }

        function closeModal() {
            document.body.classList.remove('no-scroll');
            cropperModal.classList.remove('opacity-100');
            cropperModal.classList.add('opacity-0', 'pointer-events-none');
            modalContent.classList.remove('scale-100');
            modalContent.classList.add('scale-95');
            if (cropper) {
                cropper.destroy();
                cropper = null;
            }
            imageInput.value = '';
        }

        btnCloseModal.addEventListener('click', closeModal);
        btnCancelCropper.addEventListener('click', closeModal);

        function initializeCropper() {
            if (cropper) {
                cropper.destroy();
            }
            cropper = new Cropper(cropperImage, {
                aspectRatio: 1,
                viewMode: 1,
                dragMode: 'move',
                autoCropArea: 0.8,
                restore: false,
                guides: true,
                center: true,
                highlight: false,
                cropBoxMovable: true,
                cropBoxResizable: true,
                toggleDragModeOnDblclick: false,
                responsive: true,
                checkOrientation: true,
            });
        }

        /* Kontrol Cropper */
        btnZoomIn.addEventListener('click', () => {
            if (cropper) cropper.zoom(0.1);
        });
        btnZoomOut.addEventListener('click', () => {
            if (cropper) cropper.zoom(-0.1);
        });
        btnRotateLeft.addEventListener('click', () => {
            if (cropper) cropper.rotate(-90);
        });
        btnResetCropper.addEventListener('click', () => {
            if (cropper) cropper.reset();
        });

        /* Potong & Terapkan Gambar */
        btnSaveCropped.addEventListener('click', () => {
            if (!cropper) return;

            const croppedCanvas = cropper.getCroppedCanvas({
                width: 300,
                height: 300,
                imageSmoothingEnabled: true,
                imageSmoothingQuality: 'high',
            });

            /* Setup hasil bundar dengan background transparan */
            const finalCanvas = document.createElement('canvas');
            finalCanvas.width = 300;
            finalCanvas.height = 300;
            const ctx = finalCanvas.getContext('2d');

            ctx.beginPath();
            ctx.arc(150, 150, 150, 0, 2 * Math.PI);
            ctx.clip();
            ctx.drawImage(croppedCanvas, 0, 0, 300, 300);

            profilePreview.src = finalCanvas.toDataURL(originalFileType);

            // Convert canvas to blob and create file
            finalCanvas.toBlob(function(blob) {
                const dt = new DataTransfer();
                const file = new File([blob], 'cropped_image.' + (originalFileType === 'image/png' ? 'png' : 'jpg'), {
                    type: originalFileType
                });
                dt.items.add(file);
                croppedImageInput.files = dt.files;

                // AUTO SUBMIT FORM SETELAH CROP SELESAI
                closeModal();
                setTimeout(() => {
                    if (typeof profileForm.requestSubmit === 'function') {
                        profileForm.requestSubmit();
                    } else {
                        profileForm.submit();
                    }
                }, 300);
            }, originalFileType);
        });

        /* Handling Form Submit & Tampilkan Banner Notifikasi Sukses */
        profileForm.addEventListener('submit', (e) => {
            // Let form submit normally - PHP will handle it
        });

        const deletePhotoBtn = document.getElementById('deletePhotoBtn');
        const deletePhotoForm = document.getElementById('deletePhotoForm');
        const confirmDeleteModal = document.getElementById('confirmDeleteModal');
        const cancelDeleteBtn = document.getElementById('cancelDeleteBtn');
        const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');

        function openConfirmModal() {
            confirmDeleteModal.classList.remove('hidden');
            document.body.classList.add('no-scroll');
        }

        function closeConfirmModal() {
            confirmDeleteModal.classList.add('hidden');
            document.body.classList.remove('no-scroll');
        }

        deletePhotoBtn.addEventListener('click', openConfirmModal);
        cancelDeleteBtn.addEventListener('click', closeConfirmModal);
        confirmDeleteBtn.addEventListener('click', () => {
            closeConfirmModal();
            deletePhotoForm.submit();
        });

        // Show success if exists from PHP
        <?php if ($success_msg): ?>
            successAlert.classList.remove('hidden', 'pointer-events-none');
            setTimeout(() => {
                successAlert.classList.remove('opacity-0', 'translate-y-[-10px]');
                successAlert.classList.add('opacity-100', 'translate-y-0');
            }, 50);
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
            setTimeout(() => {
                successAlert.classList.remove('opacity-100', 'translate-y-0');
                successAlert.classList.add('opacity-0', 'translate-y-[-10px]');
                setTimeout(() => {
                    successAlert.classList.add('hidden', 'pointer-events-none');
                }, 300);
            }, 5000);
        <?php endif; ?>

        // Show error if exists from PHP
        <?php if ($error_msg): ?>
            errorAlert.classList.remove('hidden', 'pointer-events-none');
            document.getElementById('errorMessage').textContent = '<?php echo htmlspecialchars($error_msg); ?>';
            setTimeout(() => {
                errorAlert.classList.remove('opacity-0', 'translate-y-[-10px]');
                errorAlert.classList.add('opacity-100', 'translate-y-0');
            }, 50);
            setTimeout(() => {
                errorAlert.classList.remove('opacity-100', 'translate-y-0');
                errorAlert.classList.add('opacity-0', 'translate-y-[-10px]');
                setTimeout(() => {
                    errorAlert.classList.add('hidden', 'pointer-events-none');
                }, 300);
            }, 5000);
        <?php endif; ?>
    </script>
</body>

</html>