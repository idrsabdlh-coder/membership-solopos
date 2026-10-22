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

Order pending kedaluwarsa setelah 24 jam. Di production, jadwalkan `php artisan schedule:run` setiap menit agar order kedaluwarsa dan reservasi kuota voucher dilepas oleh task `orders:expire-pending`.

Pembayaran sukses membuat satu record langganan secara idempoten. Perpanjangan saat masa aktif masih berjalan dijadwalkan mulai setelah `end_date`; langganan yang telah berakhir mulai saat pembayaran sukses. Jalankan `php artisan schedule:run` setiap menit untuk mengaktifkan masa langganan terjadwal dan menandai langganan berakhir. `GET /api/subscriptions` menampilkan status saat ini, riwayat, dan entitlement paket untuk akun yang login.

Kontrol perangkat menggunakan database session store (`SESSION_DRIVER=database`): satu akun dibatasi maksimal dua sesi aktif. Sesi tertua dicabut saat login ketiga, dan sesi lain dapat dicabut dari halaman `/pengaturan`. Produksi harus memakai database session store yang sama pada seluruh instance aplikasi.

Pembayaran memakai Midtrans Snap hosted redirect. Untuk sandbox, isi `MIDTRANS_SERVER_KEY` dan `MIDTRANS_CLIENT_KEY` di `.env`, pertahankan `MIDTRANS_IS_PRODUCTION=false`, lalu jalankan worker dengan `php artisan queue:work`. Atur URL notifikasi di dashboard Midtrans ke `https://<api-host>/api/payments/webhook`. Endpoint bayar hanya menerima metode dan nominal yang dihitung server; jangan menyimpan atau mengirim data kartu. Jangan aktifkan mode production sebelum akun merchant, webhook, biaya/refund, dan persetujuan operasional diverifikasi.

## Pemeriksaan

```powershell
# Dari backend
php artisan test

# Dari frontend
npm run lint
npm run build
```
