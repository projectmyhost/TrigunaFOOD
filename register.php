<?php
require_once 'config.php';

$message = "";
$messageType = "";

function mysqli_real_escape_with_str($conn, $data) {
    return mysqli_real_escape_string($conn, htmlspecialchars($data));
}

if (isset($_POST['register'])) {
    $username = mysqli_real_escape_with_str($conn, $_POST['username']);
    $email = mysqli_real_escape_with_str($conn, $_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if ($password !== $confirm_password) {
        $message = "Password dan konfirmasi tidak cocok!";
        $messageType = "red";
    } else {
        $check = mysqli_query($conn, "SELECT * FROM users WHERE username='$username' OR email='$email'");
        if (mysqli_num_rows($check) > 0) {
            $message = "Username atau Email sudah terdaftar!";
            $messageType = "red";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $query = "INSERT INTO users (username, email, password) VALUES ('$username', '$email', '$hashed_password')";
            
            if (mysqli_query($conn, $query)) {
                header("Location: login.php?status=success");
                exit();
            } else {
                $message = "Gagal registrasi: " . mysqli_error($conn);
                $messageType = "red";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - TrigunaFOOD</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }

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
<body class="relative flex items-center justify-center min-h-screen p-4 overflow-x-hidden">

    <!-- 
       Menggunakan gambar dari folder images/bg.jpg. 
       scale-110 digunakan agar bagian pinggir gambar yang ter-blur tidak menyisakan garis putih di layar.
    -->
    <div class="fixed inset-0 -z-10 overflow-hidden bg-gradient-to-br from-emerald-900 to-orange-900">
        <img src="images/triguna3.jpeg" class="w-full h-full object-cover filter blur-md scale-110 opacity-40" alt="Background">
        <div class="absolute inset-0 bg-black/40"></div>
        <div id="particles" class="particles"></div>
    </div>

    <!-- 
       Menggunakan efek glassmorphism (bg-white/95 dan backdrop-blur) 
       agar form terlihat menyatu secara elegan dengan background blur di belakangnya.
    -->
    <div class="card-enter w-full max-w-md bg-white/95 backdrop-blur-sm rounded-2xl shadow-2xl p-8 border border-white/20 transition-all duration-300">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-extrabold tracking-tight">
                <span class="text-orange-500">Triguna</span><span class="text-green-600">FOOD</span>
            </h1>
            <p class="text-gray-500 text-sm mt-2">Buat akun untuk mulai memesan</p>
        </div>

        <?php if ($message): ?>
            <div class="mb-4 p-3 rounded-lg text-sm font-medium text-center bg-<?php echo $messageType; ?>-100 text-<?php echo $messageType; ?>-700 border border-<?php echo $messageType; ?>-200">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <form action="" method="POST" class="space-y-5">
            <div class="">
                <label class="block text-sm font-semibold text-gray-700 mb-1">Username</label>
                <input type="text" name="username" required 
                    class="w-full px-4 py-3 border border-gray-200 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 bg-gray-50/50 focus:bg-white outline-none transition-all duration-300" 
                    placeholder="Masukkan username">
            </div>

            <div class="">
                <label class="block text-sm font-semibold text-gray-700 mb-1">Email</label>
                <input type="email" name="email" required 
                    class="w-full px-4 py-3 border border-gray-200 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 bg-gray-50/50 focus:bg-white outline-none transition-all duration-300" 
                    placeholder="contoh@email.com">
            </div>

            <div class="">
                <label class="block text-sm font-semibold text-gray-700 mb-1">Password</label>
                <div class="relative">
                    <input type="password" id="password" name="password" required oninput="checkPasswordMatch()"
                        class="w-full px-4 py-3 border border-gray-200 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 bg-gray-50/50 focus:bg-white outline-none transition-all duration-300" 
                        placeholder="••••••••">
                    <button type="button" onclick="togglePassword('password', 'eye-icon-1')" class="absolute inset-y-0 right-0 pr-4 flex items-center hover:scale-110 transition-transform">
                        <svg id="eye-icon-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-gray-400 hover:text-orange-500 transition-colors">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </button>
                </div>
                <!-- Password Strength Meter -->
                <div class="mt-2 hidden" id="password-strength-container">
                    <div class="flex gap-1 h-1 w-full rounded-full overflow-hidden bg-gray-200">
                        <div id="strength-bar-1" class="h-full w-1/3 transition-colors duration-300"></div>
                        <div id="strength-bar-2" class="h-full w-1/3 transition-colors duration-300"></div>
                        <div id="strength-bar-3" class="h-full w-1/3 transition-colors duration-300"></div>
                    </div>
                    <p id="strength-text" class="text-xs mt-1 font-medium text-gray-500">Kekuatan password</p>
                </div>
            </div>

            <div class="">
                <label class="block text-sm font-semibold text-gray-700 mb-1">Konfirmasi Password</label>
                <div class="relative">
                    <input type="password" id="confirm_password" name="confirm_password" required oninput="checkPasswordMatch()"
                        class="w-full px-4 py-3 border border-gray-200 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 bg-gray-50/50 focus:bg-white outline-none transition-all duration-300" 
                        placeholder="••••••••">
                    
                    <!-- Match Indicator -->
                    <div id="match-indicator" class="absolute inset-y-0 right-10 pr-2 flex items-center hidden">
                        <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </div>

                    <button type="button" onclick="togglePassword('confirm_password', 'eye-icon-2')" class="absolute inset-y-0 right-0 pr-4 flex items-center hover:scale-110 transition-transform">
                        <svg id="eye-icon-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-gray-400 hover:text-orange-500 transition-colors">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </button>
                </div>
                <p id="match-text" class="text-xs mt-1 font-medium text-red-500 hidden">Password tidak cocok</p>
            </div>

            <button type="submit" name="register" 
                class="w-full bg-orange-500 hover:bg-orange-600 text-white font-bold py-3 rounded-lg shadow-lg shadow-orange-500/20 transition duration-300 transform active:scale-95">
                Daftar Sekarang
            </button>
        </form>

        <div class="mt-6 text-center">
            <p class="text-sm text-gray-600">
                Sudah punya akun? <a href="login.php" class="text-green-600 font-semibold hover:underline">Login disini</a>
            </p>
        </div>
    </div>

    <script>
        function togglePassword(inputId, iconId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(iconId);
            if (input.type === "password") {
                input.type = "text";
                icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.892 7.892L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />';
            } else {
                input.type = "password";
                icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />';
            }
        }

        function checkPasswordMatch() {
            const pwd = document.getElementById('password').value;
            const confirmPwd = document.getElementById('confirm_password').value;
            
            // Handle strength meter
            const strengthContainer = document.getElementById('password-strength-container');
            const bar1 = document.getElementById('strength-bar-1');
            const bar2 = document.getElementById('strength-bar-2');
            const bar3 = document.getElementById('strength-bar-3');
            const text = document.getElementById('strength-text');
            
            if (pwd.length > 0) {
                strengthContainer.classList.remove('hidden');
                let strength = 0;
                if (pwd.length > 5) strength++;
                if (pwd.length > 7 && /[A-Z]/.test(pwd)) strength++;
                if (pwd.length > 7 && /[0-9]/.test(pwd) && /[^A-Za-z0-9]/.test(pwd)) strength++;

                // Reset
                bar1.className = 'h-full w-1/3 transition-colors duration-300';
                bar2.className = 'h-full w-1/3 transition-colors duration-300';
                bar3.className = 'h-full w-1/3 transition-colors duration-300';

                if (strength === 0 || strength === 1) {
                    bar1.classList.add('bg-red-500');
                    text.innerText = "Lemah";
                    text.className = "text-xs mt-1 font-medium text-red-500";
                } else if (strength === 2) {
                    bar1.classList.add('bg-yellow-500');
                    bar2.classList.add('bg-yellow-500');
                    text.innerText = "Sedang";
                    text.className = "text-xs mt-1 font-medium text-yellow-600";
                } else {
                    bar1.classList.add('bg-green-500');
                    bar2.classList.add('bg-green-500');
                    bar3.classList.add('bg-green-500');
                    text.innerText = "Kuat";
                    text.className = "text-xs mt-1 font-medium text-green-600";
                }
            } else {
                strengthContainer.classList.add('hidden');
            }

            // Handle match indicator
            const matchIndicator = document.getElementById('match-indicator');
            const matchText = document.getElementById('match-text');
            
            if (confirmPwd.length > 0) {
                if (pwd === confirmPwd) {
                    matchIndicator.classList.remove('hidden');
                    matchText.classList.add('hidden');
                } else {
                    matchIndicator.classList.add('hidden');
                    matchText.classList.remove('hidden');
                }
            } else {
                matchIndicator.classList.add('hidden');
                matchText.classList.add('hidden');
            }
        }

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