# 🔗 Collabify - Realtime Collaborative Editor (Google Docs Clone)

Collabify adalah aplikasi web editor dokumen kolaboratif realtime (seperti Google Docs) yang dibangun menggunakan **Laravel 13**, **Inertia.js v3**, **Vue 3**, **Tailwind CSS v4**, **MySQL**, dan **Laravel Reverb (WebSockets)**. 

Aplikasi ini dirancang khusus agar dapat langsung terhubung dan digunakan berkolaborasi oleh banyak perangkat (HP, laptop, tablet) yang tersambung pada **jaringan WiFi yang sama** secara dinamis tanpa perlu konfigurasi IP manual.

---

## ✨ Fitur Utama

- **✍️ Kolaborasi Teks Realtime:** Sinkronisasi teks instan antar perangkat dengan latensi sangat rendah menggunakan channel WebSockets.
- **📍 Pelacakan Posisi Kursor:** Kursor dari pengguna lain akan tampil berupa warna dan bendera nama yang bergerak secara dinamis mengikuti posisi mengetik mereka. Isu perpindahan caret yang meloncat (cursor jumping) telah diselesaikan.
- **⚡ Autosave Latar Belakang (Debounced):** Aplikasi menyimpan pekerjaan Anda ke database MySQL secara otomatis dalam 1 detik setelah Anda berhenti mengetik (debounced) dengan status reaktif (`⚡ Menyimpan...` dan `✓ Tersimpan ke DB`).
- **🗂️ Sinkronisasi Dashboard Realtime:** Jika salah satu pengguna mengubah nama (rename) atau menghapus (delete) dokumen, perubahan tersebut akan langsung tercermin secara instan di layar dashboard pengguna lain tanpa perlu memuat ulang halaman.
- **🔗 Deteksi Otomatis IP Jaringan (WiFi):** Dashboard mendeteksi IP lokal server secara otomatis dan menampilkan tautan koneksi (misalnya `http://192.168.1.15:8000`) untuk dibuka di perangkat lain.
- **🚀 Masuk Cepat Tamu (Quick Guest Join):** Pengguna dapat berkolaborasi secara instan hanya dengan memasukkan nama tamu (tanpa email & password rumit) untuk memudahkan pengujian.

---

## 🛠️ Tech Stack

- **Backend:** Laravel 13 (PHP 8.5)
- **WebSockets Server:** Laravel Reverb v1
- **Frontend SPA Protocol:** Inertia.js v3 (dengan fitur baru `useHttp` standalone request)
- **Client Framework:** Vue 3 & TypeScript
- **Styling:** Tailwind CSS v4
- **Database:** MySQL

---

## 🚀 Panduan Setup Cepat (Otomatis)

Kami telah menyediakan skrip PowerShell untuk mempermudah proses instalasi dan persiapan lingkungan kerja di Windows.

### Prasyarat
1. Pastikan Anda memiliki **PHP 8.2+** dan **Composer** terinstal di command line.
2. Pastikan Anda memiliki **Node.js** dan **npm** terinstal.
3. Pastikan server database **MySQL** Anda aktif (misalnya melalui Laragon, XAMPP, dsb) pada port default `3306` (username: `root`, tanpa password).

### Langkah Instalasi
1. Buka Terminal / PowerShell di komputer Anda, lalu clone repositori ini:
   ```bash
   git clone https://github.com/Muslimgunawan/collabify.git
   ```
2. Masuk ke direktori project yang baru saja di-clone:
   ```bash
   cd Collabify
   ```
3. Pastikan database server MySQL Anda sudah menyala/aktif (misalnya di Laragon atau XAMPP).
4. Jalankan skrip setup otomatis di PowerShell untuk menyelesaikan konfigurasi secara instan:
   ```powershell
   ./setup.ps1
   ```
   *Skrip ini akan secara otomatis membuat berkas `.env`, menginstal dependensi Composer & NPM, membuat database `collabify` jika belum ada di MySQL Anda, melakukan `key:generate`, menjalankan migrasi tabel dengan seeding, dan mengompilasi aset.*

### Akun Pengguna Utama (Seeded)
Setelah instalasi selesai, database Anda secara otomatis terisi akun utama default yang dapat langsung digunakan untuk masuk:
- **Email:** `utama@collabify.local`
- **Password:** `password123`

---

## 💻 Cara Menjalankan Server

Setelah setup selesai, Anda dapat menyalakan ketiga server yang dibutuhkan (Laravel Web, Reverb WebSocket, dan Vite Dev) dalam bentuk tab Windows Terminal sekaligus menggunakan skrip otomatis:

```powershell
./start-servers.ps1
```

Skrip di atas akan membuka Windows Terminal dengan tab-tab terpisah untuk:
1. `php artisan serve --host=0.0.0.0 --port=8000` (Akses web)
2. `php artisan reverb:start --host=0.0.0.0 --port=8080` (WebSocket server)
3. `npm run dev` (Vite assets compilation)

---

## 🌐 Cara Menguji Kolaborasi Multi-Device (WiFi Sama)

Dalam ekosistem kolaborasi lokal ini, terdapat perbedaan peran antar perangkat yang terhubung:

### 1. Komputer Utama / Server (Main Client)
Ini adalah komputer tempat kode aplikasi, database MySQL, dan server WebSocket Reverb dijalankan.
- Perangkat ini **wajib** melakukan setup lengkap (menjalankan `./setup.ps1` untuk konfigurasi & database seeding, lalu menyalakan server dengan `./start-servers.ps1`).
- Perangkat inilah yang bertindak sebagai "tuan rumah" penyedia database dan pusat sinkronisasi realtime.

### 2. Perangkat Lain / Kolaborator (Client Biasa)
Ini adalah HP, tablet, atau laptop lain yang ingin ikut mengedit dokumen dalam jaringan WiFi yang sama.
- Perangkat ini **TIDAK PERLU** meng-clone repositori, **tidak perlu** menginstal PHP/Node, dan **tidak perlu** menyalakan database MySQL.
- Mereka cukup membuka browser di perangkat mereka (misalnya Google Chrome di HP) dan langsung **mengetikkan alamat IP lokal** yang tertera pada layar komputer utama (contoh: `http://192.168.1.100:8000`).

---

### Langkah Pengujian:

1. Pada **Main Client**, jalankan aplikasi menggunakan `./start-servers.ps1`.
2. Buka browser di **Main Client** ke `http://localhost:8000`.
3. Anda akan melihat kotak informasi di dashboard yang menampilkan IP lokal server Anda, misalnya: **`http://192.168.1.100:8000`**.
4. Ambil HP atau laptop kedua Anda (**Client Biasa**), pastikan terhubung ke **WiFi yang sama** dengan Main Client, lalu buka alamat IP lokal tersebut (`http://192.168.1.100:8000`) di browser.
5. Masuk sebagai tamu dengan nama berbeda (misal: "Lynx" di Main Client dan "Hp1 (Guest)" di HP Client Biasa), lalu buka dokumen yang sama.
6. Anda sekarang dapat mengetik dan melihat perubahan teks, posisi kursor, serta avatar pengguna lain tersinkronisasi secara realtime!
7. Cobalah kembali ke dashboard, lakukan aksi **Ubah Nama** atau **Hapus** pada salah satu perangkat, dan saksikan daftar dokumen di perangkat lainnya terupdate secara realtime!
#
