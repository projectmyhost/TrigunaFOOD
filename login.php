<?php
session_start();
require_once 'config.php';

$message = "";
$messageType = "";

if (isset($_POST['login'])) {
    $identifier = mysqli_real_escape_string($conn, $_POST['identifier']);
    $password = $_POST['password'];

    // Check if login matches TrigunaDEV admin credentials (username or email)
    if (($identifier === 'TrigunaDEV' || $identifier === 'trigunadev@example.com') && $password === 'blueedryt123') {
        $_SESSION['user_id'] = 'admin_special';
        $_SESSION['username'] = 'TrigunaDEV';
        $_SESSION['role'] = 'admin';
        header("Location: admin.php");
        exit();
    }

    $query = "SELECT * FROM users WHERE username='$identifier' OR email='$identifier'";
    $result = mysqli_query($conn, $query);

    if (mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            
            header("Location: home.php");
            exit();
        } else {
            $message = "Password salah!";
            $messageType = "red";
        }
    } else {
        $message = "Akun tidak ditemukan!";
        $messageType = "red";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - TrigunaFOOD</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        
        /* Animasi Toast Notification */
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        @keyframes slideOutRight {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }
        
        .notification-slide-in {
            animation: slideInRight 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        
        .notification-slide-out {
            animation: slideOutRight 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        
        /* Bounce animation untuk icon */
        @keyframes bounceCheckmark {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.2);
            }
        }
        
        .checkmark-bounce {
            animation: bounceCheckmark 0.6s ease-out;
        }

        /* Form Entrance Animation */
        .card-enter {
            animation: cardEntrance 0.7s cubic-bezier(0.16, 1, 0.3, 1) forwards;
            opacity: 0;
        }

        @keyframes cardEntrance {
            from {
                opacity: 0;
                transform: translateY(30px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        /* Input Glow Effect */
        .input-glow:focus-within {
            box-shadow: 0 0 0 4px rgba(249, 115, 22, 0.15);
        }

        /* Floating Particles */
        .particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: -1;
        }
        .particle {
            position: absolute;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            animation: float 10s infinite linear;
        }
        @keyframes float {
            0% { transform: translateY(100vh) translateX(0); opacity: 0; }
            10% { opacity: 1; }
            90% { opacity: 1; }
            100% { transform: translateY(-100px) translateX(100px); opacity: 0; }
        }
    </style>
</head>
<body class="bg-gray-50 flex items-center justify-center min-h-screen p-4">


    <div class="fixed inset-0 -z-10 overflow-hidden bg-gradient-to-br from-emerald-900 to-orange-900">
        <img src="images/triguna3.jpeg" class="w-full h-full object-cover filter blur-md scale-110 opacity-40" alt="Background">
        <div class="absolute inset-0 bg-black/40"></div>
        <div id="particles" class="particles"></div>
    </div>

    <!-- SUCCESS NOTIFICATION TOAST -->
    <div id="successNotification" class="fixed top-6 right-6 z-50 hidden">
        <div class="bg-white rounded-2xl shadow-2xl border border-green-200 overflow-hidden">
            <div class="bg-gradient-to-r from-green-500 to-emerald-500 px-6 py-4 flex items-center gap-4">
                <div class="flex-shrink-0">
                    <div class="flex items-center justify-center h-12 w-12 rounded-full bg-white/20">
                        <svg id="successCheckmark" class="h-6 w-6 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                </div>
                <div class="flex-1">
                    <h3 class="text-lg font-bold text-white">Registrasi Berhasil!</h3>
                    <p class="text-green-50 text-sm mt-1">Silahkan Login Dengan Data Akun Anda.</p>
                </div>
                <button onclick="closeNotification()" class="text-white hover:text-green-100 transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="bg-white px-6 py-3 flex items-center gap-2">
                <div class="text-sm text-gray-600">
                    <span class="font-semibold text-gray-800">Tip:</span> Gunakan username atau email untuk login.
                </div>
            </div>
        </div>
    </div>

    <div class="card-enter w-full max-w-md bg-white/95 backdrop-blur-sm rounded-2xl shadow-2xl p-8 border border-white/20">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-extrabold tracking-tight">
                <span class="text-orange-500">Triguna</span><span class="text-green-600">FOOD</span>
            </h1>
            <p class="text-gray-500 text-sm mt-2">Selamat datang kembali!</p>
        </div>

        <?php if ($message): ?>
            <div class="mb-4 p-3 rounded-lg text-sm font-medium text-center bg-<?php echo $messageType; ?>-100 text-<?php echo $messageType; ?>-700 border border-<?php echo $messageType; ?>-200">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <form action="" method="POST" class="space-y-5">
            <div class="">
                <label class="block text-sm font-semibold text-gray-700 mb-1">Username atau Email</label>
                <input type="text" name="identifier" required 
                    class="w-full px-4 py-3 border border-gray-200 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 bg-gray-50/50 focus:bg-white outline-none transition-all duration-300" 
                    placeholder="Masukkan username/email">
            </div>

            <div class="">
                <label class="block text-sm font-semibold text-gray-700 mb-1">Password</label>
                <div class="relative">
                    <input type="password" id="password" name="password" required 
                        class="w-full px-4 py-3 border border-gray-200 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 bg-gray-50/50 focus:bg-white outline-none transition-all duration-300" 
                        placeholder="••••••••">
                    <button type="button" onclick="togglePassword()" class="absolute inset-y-0 right-0 pr-4 flex items-center hover:scale-110 transition-transform">
                        <svg id="eye-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-gray-400 hover:text-orange-500 transition-colors">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </button>
                </div>
            </div>

            <button type="submit" name="login" 
                class="w-full bg-orange-500 hover:bg-orange-600 text-white font-bold py-3 rounded-lg shadow-lg shadow-orange-200 transition duration-300 transform active:scale-95">
                Masuk
            </button>
        </form>

        <div class="mt-6 text-center">
            <p class="text-sm text-gray-600">
                Belum punya akun? <a href="register.php" class="text-green-600 font-semibold hover:underline">Daftar disini</a>
            </p>
        </div>
    </div>

    <script>
        function togglePassword() {
            const input = document.getElementById('password');
            const icon = document.getElementById('eye-icon');
            if (input.type === "password") {
                input.type = "text";
                icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.892 7.892L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />';
            } else {
                input.type = "password";
                icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />';
            }
        }

        // Fungsi untuk menampilkan notifikasi sukses
        function showSuccessNotification() {
            const notification = document.getElementById('successNotification');
            const checkmark = document.getElementById('successCheckmark');
            
            // Tampilkan notifikasi dengan animasi slide in
            notification.classList.remove('hidden');
            notification.querySelector('div').classList.add('notification-slide-in');
            
            // Tambahkan animasi bounce ke checkmark
            checkmark.classList.add('checkmark-bounce');
            
            // Auto-close setelah 6 detik
            setTimeout(() => {
                closeNotification();
            }, 6000);
        }

        // Fungsi untuk menutup notifikasi
        function closeNotification() {
            const notification = document.getElementById('successNotification');
            const notificationDiv = notification.querySelector('div');
            
            // Tambahkan animasi slide out
            notificationDiv.classList.remove('notification-slide-in');
            notificationDiv.classList.add('notification-slide-out');
            
            // Sembunyikan setelah animasi selesai
            setTimeout(() => {
                notification.classList.add('hidden');
                notificationDiv.classList.remove('notification-slide-out');
            }, 500);
        }

        // Cek apakah ada parameter status=success di URL
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('status') === 'success') {
                showSuccessNotification();
                
                // Hapus parameter dari URL tanpa reload
                window.history.replaceState({}, document.title, window.location.pathname);
            }
        });
        // Particles System
        function createParticles() {
            const container = document.getElementById('particles');
            const particleCount = 20;

            for (let i = 0; i < particleCount; i++) {
                const particle = document.createElement('div');
                particle.className = 'particle';
                
                const size = Math.random() * 8 + 4;
                particle.style.width = `${size}px`;
                particle.style.height = `${size}px`;
                
                particle.style.left = `${Math.random() * 100}%`;
                
                const duration = Math.random() * 10 + 10;
                particle.style.animationDuration = `${duration}s`;
                
                const delay = Math.random() * 10;
                particle.style.animationDelay = `-${delay}s`;
                
                container.appendChild(particle);
            }
        }
        
        window.addEventListener('DOMContentLoaded', createParticles);
    </script>
</body>
</html>