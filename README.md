# Solopos Membership

## Persyaratan lokal

- PHP 8.3 atau lebih baru dengan ekstensi PDO SQLite
- Composer
- Node.js dan npm

Untuk development dasar, backend memakai SQLite. Docker, MySQL, dan Redis tidak diperlukan.

## Setup pertama kali

Di PowerShell:

```powershell
Set-Location .\backend
composer install
Copy-Item .env.example .env
New-Item -ItemType File .\database\database.sqlite
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
```

Jika `.env` atau `database.sqlite` sudah ada, lewati perintah `Copy-Item` atau `New-Item` yang sesuai agar file lokal tidak tertimpa.

Di terminal kedua:

```powershell
Set-Location .\frontend
npm ci
```

## Menjalankan aplikasi

Jalankan backend pada terminal pertama:

```powershell
Set-Location .\backend
php artisan serve
```

Jalankan frontend pada terminal kedua:

```powershell
Set-Location .\frontend
npm run dev
```

Buka `http://localhost:3000`. Endpoint backend tersedia di `http://127.0.0.1:8000/api`; frontend menggunakan alamat tersebut sebagai default.

Autentikasi browser memakai cookie session Sanctum. Sebelum login, frontend perlu meminta `/sanctum/csrf-cookie`, lalu memakai `credentials: "include"` untuk permintaan API dan mengirim header `X-XSRF-TOKEN` dari cookie `XSRF-TOKEN`. Domain lokal diizinkan melalui `SANCTUM_STATEFUL_DOMAINS` dan `CORS_ALLOWED_ORIGINS`; ubah kedua nilai tersebut untuk domain frontend yang disetujui IT. Tautan reset sandi mengarah ke `FRONTEND_URL/reset-password`. Avatar disimpan pada disk public dan disajikan melalui symlink yang dibuat oleh `storage:link`.

## Pemeriksaan

```powershell
# Dari backend
php artisan test

# Dari frontend
npm run lint
npm run build
```
