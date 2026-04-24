<?php
// Quick Start Guide - Run this to understand the category feature

require_once 'config.php';

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📦 Panduan Fitur Kategori - TrigunaFOOD</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-gradient-to-br from-orange-50 to-green-50"> 
    <?php include 'navbar.php'; ?>

<div class="max-w-4xl mx-auto px-4 py-12">
    <!-- Header -->
    <div class="text-center mb-12">
        <h1 class="text-5xl font-black mb-2">
            <span class="text-orange-500">📦 Fitur Kategori</span>
            <span class="text-green-600">Produk</span>
        </h1>
        <p class="text-gray-600 text-lg">Panduan Cepat Penggunaan</p>
    </div>

    <!-- Quick Start -->
    <div class="bg-white rounded-3xl shadow-xl p-8 mb-8 border-l-4 border-orange-500">
        <h2 class="text-2xl font-bold text-gray-800 mb-4">🚀 Quick Start</h2>
        <div class="space-y-3">
            <p class="flex items-start gap-3">
                <span class="bg-orange-500 text-white rounded-full w-6 h-6 flex items-center justify-center flex-shrink-0 text-sm font-bold">1</span>
                <span class="text-gray-700"><strong>Buka halaman:</strong> <code class="bg-gray-100 px-2 py-1 rounded">home.php</code> atau <code class="bg-gray-100 px-2 py-1 rounded">index.php</code></span>
            </p>
            <p class="flex items-start gap-3">
                <span class="bg-orange-500 text-white rounded-full w-6 h-6 flex items-center justify-center flex-shrink-0 text-sm font-bold">2</span>
                <span class="text-gray-700"><strong>Lihat kategori buttons:</strong> Semua, Makanan, Minuman, Camilan</span>
            </p>
            <p class="flex items-start gap-3">
                <span class="bg-orange-500 text-white rounded-full w-6 h-6 flex items-center justify-center flex-shrink-0 text-sm font-bold">3</span>
                <span class="text-gray-700"><strong>Klik kategori</strong> untuk melihat produk yang dipilih</span>
            </p>
        </div>
    </div>

    <!-- Features Grid -->
    <div class="grid md:grid-cols-2 gap-6 mb-8">
        <!-- Feature 1 -->
        <div class="bg-white rounded-2xl shadow-lg p-6 border-t-4 border-orange-400">
            <h3 class="text-xl font-bold text-gray-800 mb-3">🔖 Filter Buttons</h3>
            <div class="space-y-2">
                <p class="text-sm text-gray-600">4 kategori tersedia:</p>
                <ul class="space-y-1 text-sm">
                    <li>✓ 🔖 <strong>Semua</strong> - Tampilkan semua produk</li>
                    <li>✓ 🍽️ <strong>Makanan</strong> - Produk makanan</li>
                    <li>✓ 🥤 <strong>Minuman</strong> - Produk minuman</li>
                    <li>✓ 🍿 <strong>Camilan</strong> - Snack & camilan</li>
                </ul>
            </div>
        </div>

        <!-- Feature 2 -->
        <div class="bg-white rounded-2xl shadow-lg p-6 border-t-4 border-green-400">
            <h3 class="text-xl font-bold text-gray-800 mb-3">🎨 Category Badge</h3>
            <div class="space-y-2">
                <p class="text-sm text-gray-600">Setiap produk menampilkan:</p>
                <ul class="space-y-1 text-sm">
                    <li>✓ Kategori dengan emoji</li>
                    <li>✓ Badge warna orange</li>
                    <li>✓ Posisi di atas gambar</li>
                    <li>✓ Modern & responsive</li>
                </ul>
            </div>
        </div>

        <!-- Feature 3 -->
        <div class="bg-white rounded-2xl shadow-lg p-6 border-t-4 border-blue-400">
            <h3 class="text-xl font-bold text-gray-800 mb-3">📱 Responsive Design</h3>
            <div class="space-y-2">
                <p class="text-sm text-gray-600">Bekerja sempurna di:</p>
                <ul class="space-y-1 text-sm">
                    <li>✓ Mobile (scroll horizontal)</li>
                    <li>✓ Tablet (full width)</li>
                    <li>✓ Desktop (optimal layout)</li>
                    <li>✓ Semua browser modern</li>
                </ul>
            </div>
        </div>

        <!-- Feature 4 -->
        <div class="bg-white rounded-2xl shadow-lg p-6 border-t-4 border-red-400">
            <h3 class="text-xl font-bold text-gray-800 mb-3">🔒 Secure & Safe</h3>
            <div class="space-y-2">
                <p class="text-sm text-gray-600">Proteksi penuh:</p>
                <ul class="space-y-1 text-sm">
                    <li>✓ SQL Injection protection</li>
                    <li>✓ XSS protection</li>
                    <li>✓ Input validation</li>
                    <li>✓ Safe escaping</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- URL Examples -->
    <div class="bg-white rounded-3xl shadow-xl p-8 mb-8 border-l-4 border-blue-500">
        <h2 class="text-2xl font-bold text-gray-800 mb-4">🔗 URL Filter Examples</h2>
        <div class="space-y-2 font-mono text-sm">
            <div class="bg-gray-100 p-3 rounded-lg">
                <code class="text-blue-600">?kategori=Semua</code>
                <span class="text-gray-600"> - Tampilkan semua produk</span>
            </div>
            <div class="bg-gray-100 p-3 rounded-lg">
                <code class="text-blue-600">?kategori=Makanan</code>
                <span class="text-gray-600"> - Filter makanan</span>
            </div>
            <div class="bg-gray-100 p-3 rounded-lg">
                <code class="text-blue-600">?kategori=Minuman</code>
                <span class="text-gray-600"> - Filter minuman</span>
            </div>
            <div class="bg-gray-100 p-3 rounded-lg">
                <code class="text-blue-600">?kategori=Camilan</code>
                <span class="text-gray-600"> - Filter camilan</span>
            </div>
        </div>
    </div>

    <!-- Setup Guide -->
    <div class="bg-white rounded-3xl shadow-xl p-8 mb-8 border-l-4 border-green-500">
        <h2 class="text-2xl font-bold text-gray-800 mb-4">⚙️ Setup & Verifikasi</h2>
        <div class="space-y-4">
            <div>
                <h3 class="font-bold text-gray-700 mb-2">1. Setup Database (Jika Belum)</h3>
                <p class="text-gray-600 text-sm mb-2">Akses script setup untuk menambahkan kategori ke database:</p>
                <a href="add_category_feature.php" class="inline-block bg-orange-500 text-white px-4 py-2 rounded-lg font-bold hover:bg-orange-600 transition">
                    ▶ Jalankan Setup Script
                </a>
            </div>
            <div>
                <h3 class="font-bold text-gray-700 mb-2">2. Verifikasi Instalasi</h3>
                <p class="text-gray-600 text-sm mb-2">Check status dan distribusi kategori:</p>
                <a href="verify_category_feature.php" class="inline-block bg-blue-500 text-white px-4 py-2 rounded-lg font-bold hover:bg-blue-600 transition">
                    ▶ Verifikasi Status
                </a>
            </div>
            <div>
                <h3 class="font-bold text-gray-700 mb-2">3. Gunakan Fitur</h3>
                <p class="text-gray-600 text-sm mb-2">Buka halaman dan gunakan kategori filter:</p>
                <div class="flex gap-2">
                    <a href="home.php" class="inline-block bg-green-500 text-white px-4 py-2 rounded-lg font-bold hover:bg-green-600 transition">
                        ▶ Home Page
                    </a>
                    <a href="index.php" class="inline-block bg-green-500 text-white px-4 py-2 rounded-lg font-bold hover:bg-green-600 transition">
                        ▶ Index Page
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Files Modified -->
    <div class="bg-white rounded-3xl shadow-xl p-8 mb-8">
        <h2 class="text-2xl font-bold text-gray-800 mb-4">📂 File yang Dimodifikasi</h2>
        <div class="grid md:grid-cols-2 gap-4">
            <div class="bg-gray-50 p-4 rounded-lg">
                <h3 class="font-bold text-gray-700 mb-2">✏️ Modified</h3>
                <ul class="text-sm text-gray-600 space-y-1">
                    <li>✓ home.php</li>
                    <li>✓ index.php</li>
                </ul>
            </div>
            <div class="bg-gray-50 p-4 rounded-lg">
                <h3 class="font-bold text-gray-700 mb-2">➕ Ditambahkan</h3>
                <ul class="text-sm text-gray-600 space-y-1">
                    <li>✓ add_category_feature.php</li>
                    <li>✓ verify_category_feature.php</li>
                    <li>✓ CATEGORY_FEATURE_README.md</li>
                    <li>✓ SETUP_COMPLETE.md</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="text-center py-8 border-t border-gray-200">
        <p class="text-gray-600 mb-2">
            <strong>Fitur Kategori Produk v1.0</strong>
        </p>
        <p class="text-sm text-gray-500">
            Desain responsif • Secure • Production Ready
        </p>
        <p class="text-xs text-gray-400 mt-4">
            © 2024 TrigunaFOOD - All Rights Reserved
        </p>
    </div>
</div>

</body>
</html>
