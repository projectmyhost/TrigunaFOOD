<?php
session_start();

require_once 'config.php';

if (!isset($_SESSION['username']) && !isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'] ?? null;
$email = $_SESSION['email'] ?? null;
$user_id = null;

if (isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id'])) {
    $user_id = intval($_SESSION['user_id']);
}

// If email is not in session, try to resolve from DB using user_id or username
if (empty($email)) {
    if ($user_id) {
        $r = mysqli_query($conn, "SELECT username, email FROM users WHERE id = $user_id LIMIT 1");
    } elseif (!empty($username)) {
        $safe = mysqli_real_escape_string($conn, $username);
        $r = mysqli_query($conn, "SELECT id, email FROM users WHERE username = '$safe' LIMIT 1");
    } else {
        $r = false;
    }

    if ($r && mysqli_num_rows($r) > 0) {
        $urow = mysqli_fetch_assoc($r);
        if (empty($username) && isset($urow['username'])) $username = $urow['username'];
        if (isset($urow['email'])) $email = $urow['email'];
        if (!$user_id && isset($urow['id'])) $user_id = intval($urow['id']);
    }

    if (empty($email)) {
        $email = 'Email tidak ditemukan';
    }
}

// Fetch user profile data for pre-fill
$nomor_hp_default = '';
$alamat_default = '';
if ($user_id) {
    $profileRes = mysqli_query($conn, "SELECT nomor_hp, alamat FROM users WHERE id = $user_id LIMIT 1");
    if ($profileRes && mysqli_num_rows($profileRes) > 0) {
        $profileData = mysqli_fetch_assoc($profileRes);
        $nomor_hp_default = $profileData['nomor_hp'] ?? '';
        $alamat_default = $profileData['alamat'] ?? '';
    }
}

if (isset($_POST['buat_pesanan'])) {
    $telepon = mysqli_real_escape_string($conn, $_POST['telepon']);
    $alamat = mysqli_real_escape_string($conn, $_POST['alamat']);
    $metode_pembayaran = isset($_POST['metode_pembayaran']) ? mysqli_real_escape_string($conn, $_POST['metode_pembayaran']) : 'COD';
    $bukti_pembayaran = null;

    // Handle bukti pembayaran upload untuk QRISS
    if ($metode_pembayaran === 'QRISS') {
        if (!isset($_FILES['bukti_pembayaran']) || $_FILES['bukti_pembayaran']['error'] === UPLOAD_ERR_NO_FILE) {
            echo "<script>alert('Mohon upload bukti pembayaran terlebih dahulu!'); history.back();</script>";
            exit();
        }

        if ($_FILES['bukti_pembayaran']['error'] !== UPLOAD_ERR_OK) {
            echo "<script>alert('Terjadi kesalahan upload. Silakan coba lagi.'); history.back();</script>";
            exit();
        }

        // Validasi file
        $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
        $max_size = 5 * 1024 * 1024; // 5MB

        if (!in_array($_FILES['bukti_pembayaran']['type'], $allowed_types)) {
            echo "<script>alert('Format file hanya boleh JPG, PNG. Silakan upload kembali.'); history.back();</script>";
            exit();
        }

        if ($_FILES['bukti_pembayaran']['size'] > $max_size) {
            echo "<script>alert('Ukuran file maksimal 5MB. Silakan upload file yang lebih kecil.'); history.back();</script>";
            exit();
        }

        // Create uploads directory if not exists
        $target_dir = "uploads/bukti_pembayaran/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);

        // Generate unique filename
        $filename = time() . "_" . preg_replace('/[^A-Za-z0-9._-]/', '_', basename($_FILES['bukti_pembayaran']['name']));
        $target_path = $target_dir . $filename;

        if (!move_uploaded_file($_FILES['bukti_pembayaran']['tmp_name'], $target_path)) {
            echo "<script>alert('Gagal upload file. Silakan coba lagi.'); history.back();</script>";
            exit();
        }

        $bukti_pembayaran = $filename;
    }

    // resolve numeric user id if available
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

    if (empty($user_id)) {
        echo "
        <!DOCTYPE html>
        <html lang='id'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Error - TrigunaFOOD</title>
            <script src='https://cdn.tailwindcss.com'></script>
            <link href='https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap' rel='stylesheet'>
            <style>* { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
        </head>
        <body class='bg-gradient-to-br from-red-50 to-orange-50 flex items-center justify-center min-h-screen p-4'>
            <div class='max-w-md w-full bg-white rounded-3xl shadow-2xl border border-red-100 p-8 text-center'>
                <h1 class='text-2xl font-bold text-red-600 mb-2'>User Tidak Valid</h1>
                <p class='text-gray-600 mb-6'>Silakan login kembali untuk melanjutkan.</p>
                <a href='login.php' class='inline-block px-6 py-3 bg-gradient-to-r from-red-500 to-orange-500 text-white rounded-xl font-semibold hover:shadow-lg transition'>
                    Kembali ke Login
                </a>
            </div>
        </body>
        </html>
        ";
        exit();
    }

    // fetch cart items with current stock
    $cartRes = mysqli_query($conn, "SELECT cart.*, products.stok, products.nama_produk FROM cart JOIN products ON cart.product_id = products.id WHERE cart.user_id = $user_id");
    if (!$cartRes || mysqli_num_rows($cartRes) == 0) {
        echo "
        <!DOCTYPE html>
        <html lang='id'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Keranjang Kosong - TrigunaFOOD</title>
            <script src='https://cdn.tailwindcss.com'></script>
            <link href='https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap' rel='stylesheet'>
            <style>* { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
        </head>
        <body class='bg-gradient-to-br from-yellow-50 to-orange-50 flex items-center justify-center min-h-screen p-4'>
            <div class='max-w-md w-full bg-white rounded-3xl shadow-2xl border border-yellow-100 p-8 text-center'>
                <h1 class='text-2xl font-bold text-yellow-600 mb-2'>Keranjang Kosong</h1>
                <p class='text-gray-600 mb-6'>Silakan pilih makanan terlebih dahulu sebelum checkout.</p>
                <a href='home.php' class='inline-block px-6 py-3 bg-gradient-to-r from-yellow-500 to-orange-500 text-white rounded-xl font-semibold hover:shadow-lg transition'>
                    Kembali ke Menu
                </a>
            </div>
        </body>
        </html>
        ";
        exit();
    }

    // check availability
    $insufficient = [];
    $items = [];
    while ($row = mysqli_fetch_assoc($cartRes)) {
        $items[] = $row;
        if (intval($row['stok']) < intval($row['jumlah'])) {
            $insufficient[] = $row['nama_produk'];
        }
    }

    if (!empty($insufficient)) {
        $msg = implode(', ', $insufficient);
        echo "
        <!DOCTYPE html>
        <html lang='id'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Stok Tidak Cukup - TrigunaFOOD</title>
            <script src='https://cdn.tailwindcss.com'></script>
            <link href='https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap' rel='stylesheet'>
            <style>* { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
        </head>
        <body class='bg-gradient-to-br from-red-50 to-orange-50 flex items-center justify-center min-h-screen p-4'>
            <div class='max-w-md w-full bg-white rounded-3xl shadow-2xl border border-red-100 p-8 text-center'>
                <h1 class='text-2xl font-bold text-red-600 mb-2'>Stok Tidak Cukup</h1>
                <p class='text-gray-600 mb-3'>Item berikut stoknya tidak mencukupi:</p>
                <div class='bg-red-50 border border-red-200 rounded-lg p-3 mb-6'>
                    <p class='text-sm text-red-700 font-semibold'>$msg</p>
                </div>
                <a href='home.php' class='inline-block px-6 py-3 bg-gradient-to-r from-red-500 to-orange-500 text-white rounded-xl font-semibold hover:shadow-lg transition'>
                    Kembali ke Menu
                </a>
            </div>
        </body>
        </html>
        ";
        exit();
    }
    // prepare order data
    $order_items = [];
    $order_total = 0;
    foreach ($items as $it) {
        $order_items[] = [
            'product_id' => intval($it['product_id']),
            'nama_produk' => $it['nama_produk'],
            'jumlah' => intval($it['jumlah']),
            'subtotal' => floatval($it['subtotal'])
        ];
        $order_total += floatval($it['subtotal']);
    }

    // ensure orders table exists with metode_pembayaran column
    $createOrdersSQL = "CREATE TABLE IF NOT EXISTS orders (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        user_id INT DEFAULT NULL,
        username VARCHAR(191) DEFAULT NULL,
        telepon VARCHAR(50) DEFAULT NULL,
        alamat TEXT DEFAULT NULL,
        items TEXT,
        total DECIMAL(12,2) DEFAULT 0,
        metode_pembayaran VARCHAR(50) DEFAULT 'COD',
        bukti_pembayaran VARCHAR(255) DEFAULT NULL,
        status VARCHAR(50) DEFAULT 'Pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    mysqli_query($conn, $createOrdersSQL);

    // Check if columns exist, if not add them
    $checkCol1 = mysqli_query($conn, "SELECT * FROM information_schema.COLUMNS WHERE TABLE_NAME='orders' AND COLUMN_NAME='metode_pembayaran'");
    if (!$checkCol1 || mysqli_num_rows($checkCol1) == 0) {
        mysqli_query($conn, "ALTER TABLE orders ADD COLUMN metode_pembayaran VARCHAR(50) DEFAULT 'COD'");
    }

    $checkCol2 = mysqli_query($conn, "SELECT * FROM information_schema.COLUMNS WHERE TABLE_NAME='orders' AND COLUMN_NAME='bukti_pembayaran'");
    if (!$checkCol2 || mysqli_num_rows($checkCol2) == 0) {
        mysqli_query($conn, "ALTER TABLE orders ADD COLUMN bukti_pembayaran VARCHAR(255) DEFAULT NULL");
    }

    $items_json = mysqli_real_escape_string($conn, json_encode($order_items));
    $username_esc = mysqli_real_escape_string($conn, $username ?? '');
    $telepon_esc = mysqli_real_escape_string($conn, $telepon);
    $alamat_esc = mysqli_real_escape_string($conn, $alamat);
    $total_esc = number_format($order_total, 2, '.', '');
    $bukti_esc = $bukti_pembayaran ? mysqli_real_escape_string($conn, $bukti_pembayaran) : null;

    $insSql = "INSERT INTO orders (user_id, username, telepon, alamat, items, total, metode_pembayaran, bukti_pembayaran, status) VALUES ($user_id, '$username_esc', '$telepon_esc', '$alamat_esc', '$items_json', $total_esc, '$metode_pembayaran', " . ($bukti_esc ? "'$bukti_esc'" : "NULL") . ", 'Pending')";
    if (!mysqli_query($conn, $insSql)) {
        echo "
        <!DOCTYPE html>
        <html lang='id'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Error - TrigunaFOOD</title>
            <script src='https://cdn.tailwindcss.com'></script>
            <link href='https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap' rel='stylesheet'>
            <style>* { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
        </head>
        <body class='bg-gradient-to-br from-red-50 to-orange-50 flex items-center justify-center min-h-screen p-4'>
            <div class='max-w-md w-full bg-white rounded-3xl shadow-2xl border border-red-100 p-8 text-center'>
                <h1 class='text-2xl font-bold text-red-600 mb-2'>Gagal Membuat Pesanan</h1>
                <p class='text-gray-600 mb-6'>Terjadi kesalahan saat memproses pesanan Anda. Silakan coba lagi.</p>
                <a href='home.php' class='inline-block px-6 py-3 bg-gradient-to-r from-red-500 to-orange-500 text-white rounded-xl font-semibold hover:shadow-lg transition'>
                    Kembali ke Menu
                </a>
            </div>
        </body>
        </html>
        ";
        exit();
    }

    $order_id = mysqli_insert_id($conn);

    // decrement stock
    foreach ($items as $it) {
        $pid = intval($it['product_id']);
        $qty = intval($it['jumlah']);
        mysqli_query($conn, "UPDATE products SET stok = GREATEST(0, stok - $qty) WHERE id = $pid");
    }

    // clear user's cart
    mysqli_query($conn, "DELETE FROM cart WHERE user_id = $user_id");

    // redirect to pesanan.php to view order
    echo "
    <!DOCTYPE html>
    <html lang='id'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Pesanan Berhasil - TrigunaFOOD</title>
        <script src='https://cdn.tailwindcss.com'></script>
        <link href='https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap' rel='stylesheet'>
        <style>
            * { font-family: 'Plus Jakarta Sans', sans-serif; }
            @keyframes slideDown {
                from { transform: translateY(-30px); opacity: 0; }
                to { transform: translateY(0); opacity: 1; }
            }
            .success-card { animation: slideDown 0.5s ease; }
        </style>
    </head>
    <body class='bg-gradient-to-br from-emerald-50 to-green-50 flex items-center justify-center min-h-screen p-4'>
        <div class='max-w-md w-full bg-white rounded-3xl shadow-2xl border-2 border-emerald-200 p-8 text-center success-card'>
            <h1 class='text-3xl font-bold text-emerald-600 mb-2'>Pesanan Berhasil!</h1>
            <p class='text-gray-600 mb-4'>Nomor Pesanan Anda:</p>
            <div class='bg-emerald-50 border-2 border-emerald-200 rounded-2xl px-6 py-4 mb-6'>
                <p class='text-4xl font-bold text-emerald-600'>#$order_id</p>
            </div>
            <p class='text-sm text-gray-500 mb-6'>Pesanan Anda sedang diproses. Silakan cek status pesanan di halaman riwayat pesanan.</p>
            <a href='pesanan.php' class='inline-block w-full px-6 py-3 bg-gradient-to-r from-emerald-500 to-green-500 text-white rounded-xl font-semibold hover:shadow-lg transition'>
                Lihat Pesanan Saya
            </a>
        </div>
    </body>
    </html>
    ";
    exit();
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - TrigunaFOOD</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        .payment-option {
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            padding: 16px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .payment-option:hover {
            border-color: #10b981;
            background-color: #f0fdf4;
        }

        .payment-option.active {
            border-color: #10b981;
            background-color: #ecfdf5;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }

        .payment-radio {
            width: 20px;
            height: 20px;
            border: 2px solid #d1d5db;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }

        .payment-option.active .payment-radio {
            border-color: #10b981;
            background-color: #10b981;
        }

        .payment-radio::after {
            content: '✓';
            color: white;
            font-weight: bold;
            display: none;
        }

        .payment-option.active .payment-radio::after {
            display: block;
        }

        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 50;
            animation: fadeIn 0.25s ease;
            padding: 16px;
            box-sizing: border-box;
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

        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .animate-shake {
            animation: shake 0.4s cubic-bezier(0.36, 0.07, 0.19, 0.97) both;
            border-color: #ef4444 !important;
            box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.2);
        }

        @keyframes shake {
            10%, 90% { transform: translate3d(-1px, 0, 0); }
            20%, 80% { transform: translate3d(2px, 0, 0); }
            30%, 50%, 70% { transform: translate3d(-4px, 0, 0); }
            40%, 60% { transform: translate3d(4px, 0, 0); }
        }

        /* File Upload Styles */
        .file-upload-area {
            border: 2px dashed #10b981;
            border-radius: 12px;
            padding: 16px;
            text-align: center;
            background: #f0fdf4;
            cursor: pointer;
            transition: all 0.24s ease;
            max-width: 100%;
            box-sizing: border-box;
            white-space: normal;
            overflow-wrap: break-word;
        }

        .file-upload-area:hover {
            border-color: #059669;
            background: #ecfdf5;
        }

        .file-upload-area.drag-over {
            border-color: #059669;
            background: #ecfdf5;
            transform: scale(1.01);
        }

        .file-input-hidden {
            display: none;
        }

        .file-preview {
            margin-top: 12px;
            padding: 12px;
            background: white;
            border-radius: 8px;
            text-align: left;
            border: 1px solid #10b981;
            box-sizing: border-box;
            max-width: 100%;
        }

        .file-preview-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 0;
        }

        .file-preview-name {
            font-size: 13px;
            color: #059669;
            font-weight: 600;
            max-width: calc(100% - 48px);
            word-break: break-word;
        }

        .file-remove-btn {
            color: #ef4444;
            cursor: pointer;
            font-weight: bold;
            margin-left: 8px;
        }

        .modal-content {
            background: white;
            border-radius: 24px;
            padding: 12px 12px 88px;
            /* bottom padding to allow sticky footer */
            max-width: 520px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 8px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.22);
            animation: slideUp 0.28s ease;
            box-sizing: border-box;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(12px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .qriss-container {
            background: white;
            border: 3px solid #10b981;
            border-radius: 12px;
            padding: 0;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            max-width: 320px;
            margin: 0 auto;
            box-sizing: border-box;
        }

        .qriss-image {
            max-width: 100%;
            width: 320px;
            height: 320px;
            object-fit: contain;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            display: block;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }

        /* Footer / Buttons - keep visible */
        .modal-footer {
            position: sticky;
            bottom: 0;
            left: 0;
            right: 0;
            display: flex;
            gap: 12px;
            padding: 12px 0;
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.9), rgba(255, 255, 255, 0.95));
            border-top-left-radius: 12px;
            border-top-right-radius: 12px;
            box-shadow: 0 -6px 18px rgba(0, 0, 0, 0.04);
            z-index: 60;
        }

        .modal-footer .btn-primary,
        .modal-footer .btn-secondary {
            flex: 1;
            min-width: 0;
        }

        .btn-primary {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 12px 20px;
            border-radius: 12px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.18s ease;
        }

        .btn-primary:hover:not([disabled]) {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(16, 185, 129, 0.18);
        }

        .btn-primary:disabled,
        .btn-primary[disabled] {
            background: linear-gradient(135deg, #bbf7d0 0%, #bbf7d0 100%);
            color: white;
            opacity: 0.98;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
            pointer-events: none;
        }

        .btn-secondary {
            background: white;
            color: #f97316;
            padding: 12px 20px;
            border-radius: 12px;
            font-weight: 600;
            border: 2px solid #f97316;
            cursor: pointer;
            transition: all 0.18s ease;
        }

        .btn-secondary:hover {
            background-color: #fff7ed;
            transform: translateY(-2px);
        }

        @media (max-width: 640px) {
            .modal-content {
                padding: 12px 12px 100px;
            }

            .qriss-image {
                width: 280px;
                height: 280px;
            }

            .qriss-container {
                max-width: 280px;
            }

            .modal-footer {
                padding: 10px 0;
            }
        }

        /* Toast Notification Styles */
        .toast-container {
            position: fixed;
            top: 24px;
            right: 24px;
            z-index: 99999;
            display: flex;
            flex-direction: column;
            gap: 12px;
            pointer-events: none;
        }

        .toast {
            background: white;
            border-radius: 16px;
            padding: 16px 20px;
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
            border-left: 5px solid;
            display: flex;
            align-items: center;
            gap: 14px;
            min-width: 320px;
            max-width: 420px;
            animation: toastSlideIn 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            pointer-events: auto;
            position: relative;
            overflow: hidden;
        }

        .toast-close {
            position: absolute;
            top: 8px;
            right: 10px;
            background: none;
            border: none;
            cursor: pointer;
            color: #9ca3af;
            font-size: 16px;
            line-height: 1;
            padding: 4px;
            border-radius: 50%;
            transition: all 0.2s ease;
        }

        .toast-close:hover {
            background: rgba(0,0,0,0.06);
            color: #374151;
        }

        .toast-progress {
            position: absolute;
            bottom: 0;
            left: 0;
            height: 3px;
            border-radius: 0 0 16px 16px;
            animation: toastProgress linear forwards;
        }

        .toast.success .toast-progress { background: #10b981; }
        .toast.error .toast-progress { background: #ef4444; }
        .toast.warning .toast-progress { background: #f59e0b; }
        .toast.info .toast-progress { background: #3b82f6; }

        .toast.success {
            border-left-color: #10b981;
            background: linear-gradient(to right, #f0fdf4 0%, white 100%);
        }

        .toast.error {
            border-left-color: #ef4444;
            background: linear-gradient(to right, #fef2f2 0%, white 100%);
        }

        .toast.warning {
            border-left-color: #f59e0b;
            background: linear-gradient(to right, #fffbeb 0%, white 100%);
        }

        .toast.info {
            border-left-color: #3b82f6;
            background: linear-gradient(to right, #eff6ff 0%, white 100%);
        }

        .toast-icon {
            font-size: 24px;
            flex-shrink: 0;
        }

        .toast-content {
            flex: 1;
        }

        .toast-title {
            font-weight: 700;
            font-size: 14px;
            margin-bottom: 4px;
        }

        .toast-message {
            font-size: 13px;
            opacity: 0.8;
            line-height: 1.4;
        }

        .toast.success .toast-title {
            color: #10b981;
        }

        .toast.success .toast-message {
            color: #059669;
        }

        .toast.error .toast-title {
            color: #ef4444;
        }

        .toast.error .toast-message {
            color: #dc2626;
        }

        .toast.warning .toast-title {
            color: #f59e0b;
        }

        .toast.warning .toast-message {
            color: #d97706;
        }

        .toast.info .toast-title {
            color: #3b82f6;
        }

        .toast.info .toast-message {
            color: #1d4ed8;
        }

        @keyframes toastSlideIn {
            from {
                transform: translateX(400px);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes toastSlideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }

            to {
                transform: translateX(400px);
                opacity: 0;
            }
        }

        @keyframes toastProgress {
            from { width: 100%; }
            to { width: 0%; }
        }

        .toast.removing {
            animation: toastSlideOut 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        /* Mobile responsive */
        @media (max-width: 640px) {
            .toast-container {
                left: 12px;
                right: 12px;
                bottom: auto;
            }

            .toast {
                min-width: unset;
                width: 100%;
            }
        }
    </style>
</head>

<body class="bg-gradient-to-br from-slate-50 via-white to-emerald-50 flex items-center justify-center min-h-screen p-4 overflow-x-hidden">

    <div class="page-enter max-w-md w-full bg-white rounded-3xl shadow-2xl border border-gray-100 overflow-hidden">
        <div class="bg-gradient-to-r from-emerald-500 to-orange-500 p-8 text-white relative overflow-hidden">
            <div class="absolute top-0 right-0 w-32 h-32 bg-white opacity-10 rounded-full -mr-10 -mt-10"></div>
            <h1 class="text-3xl font-bold mb-2 relative z-10">Checkout</h1>
            <p class="text-emerald-50 text-sm font-medium relative z-10">Konfirmasi dan pilih metode pembayaran Anda</p>
        </div>

        <form method="POST" enctype="multipart/form-data" class="p-8 space-y-6" id="mainForm">
            <!-- Username -->
            <div class="stagger-item stagger-1">
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Nama Pengguna</label>
                <input type="text" value="<?php echo htmlspecialchars($username); ?>" readonly
                    class="w-full p-3 bg-gray-100 border border-gray-200 rounded-xl text-gray-600 font-semibold cursor-not-allowed outline-none focus:ring-2 focus:ring-emerald-500 transition">
            </div>

            <!-- Email -->
            <div class="stagger-item stagger-2">
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Email</label>
                <input type="email" value="<?php echo htmlspecialchars($email); ?>" readonly
                    class="w-full p-3 bg-gray-100 border border-gray-200 rounded-xl text-gray-600 font-semibold cursor-not-allowed outline-none focus:ring-2 focus:ring-emerald-500 transition">
            </div>

            <!-- Phone -->
            <div class="stagger-item stagger-3">
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Nomor Telepon</label>
                <input type="number" name="telepon" value="<?php echo htmlspecialchars($nomor_hp_default); ?>" placeholder="Contoh: 08123456789" required
                    class="w-full p-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none transition group-hover:border-emerald-300">
                <?php if ($nomor_hp_default): ?>
                    <p class="text-xs text-green-600 mt-1">✓ Nomor dari profil Anda</p>
                <?php endif; ?>
            </div>

            <!-- Address -->
            <div class="stagger-item stagger-4">
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Alamat Lengkap</label>
                <textarea name="alamat" rows="3" placeholder="Masukkan alamat pengiriman..." required
                    class="w-full p-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none transition resize-none hover:border-emerald-300"><?php echo htmlspecialchars($alamat_default); ?></textarea>
                <?php if ($alamat_default): ?>
                    <p class="text-xs text-green-600 mt-1">✓ Alamat dari profil Anda</p>
                <?php endif; ?>
            </div>

            <!-- Payment Method -->
            <div class="stagger-item stagger-5">
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-3">Metode Pembayaran</label>

                <!-- COD Option -->
                <div class="payment-option active" onclick="selectPayment('COD', this)">
                    <div class="payment-radio"></div>
                    <div class="flex-1 text-left">
                        <p class="font-bold text-gray-800">Cash On Delivery (COD)</p>
                        <p class="text-xs text-gray-500 mt-0.5">Bayar saat pesanan tiba</p>
                    </div>
                </div>

                <!-- QRISS Option -->
                <div class="payment-option mt-3" onclick="selectPayment('QRISS', this)">
                    <div class="payment-radio"></div>
                    <div class="flex-1 text-left">
                        <p class="font-bold text-gray-800">QRISS AllPay</p>
                        <p class="text-xs text-gray-500 mt-0.5">Scan QR code untuk membayar sekarang</p>
                    </div>
                </div>

                <input type="hidden" name="metode_pembayaran" id="metode_pembayaran" value="COD">
                <input type="file" id="buktiPembayaranInput" name="bukti_pembayaran" style="display: none;">
            </div>

            <!-- Submit Button -->
            <div class="pt-4 space-y-3 stagger-item stagger-5">
                <button type="button" onclick="handlePayment()" id="submitPaymentBtn"
                    class="w-full bg-gradient-to-r from-emerald-500 to-orange-500 text-white py-4 rounded-2xl font-bold text-lg hover:shadow-xl transition-all active:scale-[0.98] flex justify-center items-center gap-2">
                    <span>Lanjutkan Pembayaran</span>
                </button>
                <a href="home.php" class="block text-center text-sm font-semibold text-gray-400 hover:text-gray-600 transition">
                    Kembali ke Beranda
                </a>
            </div>
        </form>
    </div>

    <!-- Toast Container -->
    <div id="toastContainer" class="toast-container"></div>

    <!-- QRISS Payment Modal -->
    <div id="qrissModal" class="hidden modal-overlay" onclick="closeQrissModal(event)">
        <div class="modal-content" onclick="event.stopPropagation()">
            <div class="text-center mb-2">
                <h2 class="text-xl font-bold text-gray-800 mb-1">Scan & Upload Bukti</h2>
                <p class="text-gray-500 text-xs">Scan QR code dengan e-wallet Anda</p>
            </div>

            <div class="qriss-container mb-3">
                <img src="uploads/qriss-allpay.jpeg" alt="QRISS AllPay QR Code" class="qriss-image" id="qrissImage">
            </div>

            <!-- File Upload Section -->
            <div class="bg-amber-50 border-2 border-amber-200 rounded-lg p-3 mb-3">
                <label class="block text-xs font-bold text-amber-900 mb-2">Upload Bukti</label>
                <div class="file-upload-area" id="uploadArea" ondrop="handleDrop(event)" ondragover="handleDragOver(event)" ondragleave="handleDragLeave(event)" style="padding: 12px;">
                    <div class="text-sm mb-1 font-semibold">Foto</div>
                    <p class="text-xs font-semibold text-amber-800">Klik atau seret foto</p>
                    <p class="text-xs text-amber-600">Max 5MB</p>
                </div>
                <input type="file" id="buktiFile" class="file-input-hidden" accept="image/jpeg,image/png,image/jpg" onchange="handleFileSelect(event)">
                <div id="filePreview" class="file-preview hidden" style="margin-top: 8px;">
                    <div class="file-preview-item">
                        <span class="file-preview-name" id="fileName" style="font-size: 12px;"></span>
                        <span class="file-remove-btn" onclick="removeFile()">✕</span>
                    </div>
                </div>
            </div>

            <div class="flex gap-2">
                <button type="button" class="btn-secondary flex-1 !py-2.5 !text-sm" onclick="closeQrissModal()">
                    Batal
                </button>
                <button type="button" class="btn-primary flex-1 !py-2.5 !text-sm" id="submitQrissBtn" onclick="completeQrissPayment()" disabled>
                    Lanjut
                </button>
            </div>
        </div>
    </div>

    <script>
        let currentPaymentMethod = 'COD';
        let isProcessing = false;
        let selectedFile = null;

        // Toast Notification Function
        function showToast(title, message, type = 'info', duration = 4000) {
            const container = document.getElementById('toastContainer');
            if (!container) return;

            const icons = {
                success: '✅',
                error: '❌',
                warning: '⚠️',
                info: 'ℹ️'
            };

            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.innerHTML = `
                <div class="toast-icon">${icons[type] || icons.info}</div>
                <div class="toast-content">
                    <div class="toast-title">${title}</div>
                    <div class="toast-message">${message}</div>
                </div>
                <button class="toast-close" onclick="dismissToast(this.parentElement)" aria-label="Close">✕</button>
                <div class="toast-progress" style="animation-duration: ${duration}ms;"></div>
            `;

            container.appendChild(toast);

            // Auto dismiss
            const autoTimer = setTimeout(() => {
                dismissToast(toast);
            }, duration);
            toast._autoTimer = autoTimer;
        }

        function dismissToast(toast) {
            if (!toast || toast._dismissing) return;
            toast._dismissing = true;
            if (toast._autoTimer) clearTimeout(toast._autoTimer);
            toast.classList.add('removing');
            setTimeout(() => {
                if (toast.parentNode) toast.remove();
            }, 400);
        }

        function selectPayment(method, element) {
            currentPaymentMethod = method;
            document.getElementById('metode_pembayaran').value = method;

            document.querySelectorAll('.payment-option').forEach(opt => {
                opt.classList.remove('active');
            });

            element.classList.add('active');

            if (method === 'QRISS') {
                showToast('QRISS Dipilih', 'Anda akan menggunakan metode pembayaran QRISS AllPay', 'info', 3000);
            } else {
                showToast('COD Dipilih', 'Pembayaran akan dilakukan saat pesanan tiba', 'info', 3000);
            }
        }

        function handlePayment() {
            const form = document.getElementById('mainForm');
            const telepon = form.querySelector('input[name="telepon"]').value;
            const alamat = form.querySelector('textarea[name="alamat"]').value;

            if (!telepon || !alamat) {
                showToast('Data Tidak Lengkap', 'Mohon isi nomor telepon dan alamat terlebih dahulu', 'warning', 4000);
                // Shake animation for empty fields
                if(!telepon) form.querySelector('input[name="telepon"]').classList.add('animate-shake');
                if(!alamat) form.querySelector('textarea[name="alamat"]').classList.add('animate-shake');
                setTimeout(() => {
                    form.querySelector('input[name="telepon"]').classList.remove('animate-shake');
                    form.querySelector('textarea[name="alamat"]').classList.remove('animate-shake');
                }, 500);
                return;
            }

            if (currentPaymentMethod === 'QRISS') {
                document.getElementById('qrissModal').classList.remove('hidden');
                selectedFile = null;
                document.getElementById('buktiPembayaranInput').value = '';
                document.getElementById('filePreview').classList.add('hidden');
                document.getElementById('submitQrissBtn').disabled = true;

                showToast('Siap Bayar', 'Scan QR code AllPay dan upload bukti pembayaran', 'info', 3000);
            } else if (currentPaymentMethod === 'COD') {
                showToast('Memproses', 'Membuat pesanan Anda...', 'info', 2000);
                
                const submitBtn = document.getElementById('submitPaymentBtn');
                submitBtn.innerHTML = '<div class="loading-spinner"></div><span>Memproses...</span>';
                submitBtn.disabled = true;
                submitBtn.style.opacity = '0.8';

                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'buat_pesanan';
                input.value = '1';
                form.appendChild(input);

                setTimeout(() => {
                    form.submit();
                }, 800);
            }
        }

        function handleDragOver(e) {
            e.preventDefault();
            e.stopPropagation();
            document.getElementById('uploadArea').classList.add('drag-over');
        }

        function handleDragLeave(e) {
            e.preventDefault();
            e.stopPropagation();
            document.getElementById('uploadArea').classList.remove('drag-over');
        }

        function handleDrop(e) {
            e.preventDefault();
            e.stopPropagation();
            document.getElementById('uploadArea').classList.remove('drag-over');

            if (e.dataTransfer.files.length > 0) {
                handleFileSelection(e.dataTransfer.files[0]);
            }
        }

        function handleFileSelect(e) {
            if (e.target.files.length > 0) {
                handleFileSelection(e.target.files[0]);
            }
        }

        function handleFileSelection(file) {
            const maxSize = 5 * 1024 * 1024;
            const allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];

            if (file.size > maxSize) {
                showToast('File Terlalu Besar', 'Ukuran file maksimal 5MB.', 'error', 4000);
                return;
            }

            if (!allowedTypes.includes(file.type)) {
                showToast('Format Tidak Didukung', 'Hanya JPG dan PNG yang didukung.', 'error', 4000);
                return;
            }

            selectedFile = file;
            document.getElementById('fileName').textContent = file.name + ' (' + (file.size / 1024).toFixed(0) + ' KB)';
            document.getElementById('filePreview').classList.remove('hidden');
            document.getElementById('submitQrissBtn').disabled = false;

            // Update the actual file input
            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(file);
            document.getElementById('buktiPembayaranInput').files = dataTransfer.files;

            showToast('Berhasil', 'Foto bukti pembayaran siap diunggah', 'success', 3000);
        }

        function removeFile() {
            selectedFile = null;
            document.getElementById('buktiPembayaranInput').value = '';
            document.getElementById('filePreview').classList.add('hidden');
            document.getElementById('submitQrissBtn').disabled = true;
            showToast('Dibatalkan', 'Foto bukti pembayaran dihapus', 'info', 2000);
        }

        function completeQrissPayment() {
            if (!selectedFile) {
                showToast('Foto Belum Diupload', 'Mohon upload bukti pembayaran terlebih dahulu', 'warning', 3000);
                return;
            }

            if (isProcessing) return;
            isProcessing = true;

            showToast('Memproses', 'Menyimpan pesanan Anda...', 'info', 2000);
            
            const submitQrissBtn = document.getElementById('submitQrissBtn');
            submitQrissBtn.innerHTML = '<div class="loading-spinner"></div>';
            submitQrissBtn.disabled = true;

            const form = document.getElementById('mainForm');
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'buat_pesanan';
            input.value = '1';
            form.appendChild(input);

            setTimeout(() => {
                form.submit();
            }, 800);
        }

        function closeQrissModal(event) {
            // If called with event from overlay click, only close if clicking directly on the overlay
            if (event && event.target && event.target.id !== 'qrissModal') return;
            document.getElementById('qrissModal').classList.add('hidden');
            isProcessing = false;
        }

        function openWhatsApp() {
            const phoneNumber = '628xxxxxxxxx';
            const message = 'Halo admin, saya ingin konfirmasi pembayaran QRISS untuk pesanan saya.';
            const whatsappURL = `https://wa.me/${phoneNumber}?text=${encodeURIComponent(message)}`;
            window.open(whatsappURL, '_blank');
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', () => {
            const uploadArea = document.getElementById('uploadArea');
            const buktiFile = document.getElementById('buktiFile');

            if (uploadArea && buktiFile) {
                uploadArea.addEventListener('click', () => {
                    buktiFile.click();
                });
            }
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeQrissModal();
            }
        });
    </script>

</body>

</html>