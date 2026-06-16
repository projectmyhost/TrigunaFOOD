# TrigunaFOOD - Web E-Commerce & Product Catalog Platform

TrigunaFOOD adalah platform web e-commerce dan katalog produk responsif yang dibangun menggunakan PHP dan MySQL. Dilengkapi sistem autentikasi multi-role (Admin dan Customer), manajemen kategori dinamis, drawer checkout modern dengan konfirmasi modal estetik, pelacakan riwayat pesanan, serta dashboard manajemen inventaris admin.

## Fitur Utama
- **Autentikasi & Otorisasi**: Login dan registrasi aman dengan pemisahan hak akses Admin dan Pelanggan.
- **Katalog Produk Interaktif**: Tampilan produk dinamis dengan filter kategori dan preview gambar.
- **Image Cropper Tool**: Integrasi Cropper.js untuk standarisasi gambar produk dan profil.
- **Drawer Checkout & Pesanan**: Slider drawer belanja yang mulus dan modal konfirmasi estetik.
- **Riwayat Pesanan**: Pelacakan status pemesanan lengkap dengan rincian invoice dan status pembayaran.
- **Panel Admin**: Dashboard lengkap untuk inventaris stok produk dan verifikasi pesanan.

## Teknologi
- **Backend**: PHP 8.x
- **Database**: MySQL / MariaDB (mysqli)
- **Frontend**: HTML5, CSS3, JavaScript, Bootstrap
- **Media Engine**: Cropper.js Integration

## Setup Database
1. Jalankan `setup_database.php` di browser untuk membuat skema tabel.
2. Atur kredensial koneksi di `config.php`.
3. Verifikasi status koneksi melalui `test_database.php`.
