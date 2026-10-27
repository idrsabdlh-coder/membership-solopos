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

Pembayaran sukses membuat satu record langganan secara idempoten. Perpanjangan saat masa aktif masih berjalan dijadwalkan mulai setelah `end_date`; langganan yang telah berakhir mulai saat pembayaran sukses. Jalankan `php artisan schedule:run` setiap menit untuk mengaktifkan masa langganan terjadwal, menandai langganan berakhir, mengirim pengingat H-7/H-3/H-1 pada pukul 09.00 WIB, serta menjalankan task terjadwal lain. Sistem mengirim email transaksi dengan invoice dan email saat masa langganan berakhir; pengingat tidak dikirim bila sudah ada masa perpanjangan terjadwal.

API clipping memerlukan langganan aktif dengan kuota clipping. Gunakan `POST /api/clippings` untuk menyimpan artikel, `GET /api/clippings?search=` untuk daftar/pencarian, dan `DELETE /api/clippings/{id}` untuk menghapus serta membebaskan kuota. Clipping dari masa berlangganan yang berakhir tetap dapat dibaca selama 90 hari, tetapi tidak dapat diubah atau dihapus saat tidak ada langganan clipping aktif.

Pengelolaan edisi E-Paper tersedia untuk akun admin terverifikasi yang email-nya tercantum di `EPAPER_ADMIN_EMAILS` (daftar email dipisah koma; kosong secara default). Gunakan `POST /api/admin/epaper/editions` untuk ingest PDF/cover sebagai draft, `PATCH` untuk memperbarui metadata draft, lalu `POST /api/admin/epaper/editions/{id}/publish` untuk menerbitkan. File master dan thumbnail disimpan pada disk privat `EPAPER_STORAGE_DISK` (default `local`); jangan arahkan ke disk public. FPDI memvalidasi PDF serta menghitung halaman. Rentang edisi hanya satu tanggal atau Sabtu-Minggu. Ikuti proses persetujuan bisnis sebelum memasukkan edisi produksi.

Reader E-Paper memakai `GET /api/epaper/editions?from=&to=&year=` untuk daftar edisi dan `GET /api/epaper/editions/{id}/pages/{page}` untuk memperoleh signed URL halaman setelah server memvalidasi langganan aktif dan irisan tanggal edisi. URL halaman berlaku lima menit; file master tidak pernah disajikan langsung. Unduhan menggunakan `GET /api/epaper/editions/{id}/download` dan mengembalikan `202 processing`; worker `php artisan queue:work` membuat PDF ber-watermark email dan hash unik. Poll `GET /api/epaper/downloads/{id}` untuk signed URL file yang berlaku lima menit. `GET /api/epaper/downloads` memberi riwayat owner-scoped dan kuota harian WIB; batas default tiga unduhan per edisi dan sepuluh per hari. Jalankan `php artisan schedule:run` setiap menit di production; scheduler membersihkan file generated yang kedaluwarsa setiap jam tanpa menghapus riwayat audit.

Kontrol perangkat menggunakan database session store (`SESSION_DRIVER=database`): satu akun dibatasi maksimal dua sesi aktif. Sesi tertua dicabut saat login ketiga, dan sesi lain dapat dicabut dari halaman `/pengaturan`. Produksi harus memakai database session store yang sama pada seluruh instance aplikasi.

Pembayaran memakai Midtrans Snap hosted redirect. Untuk sandbox, isi `MIDTRANS_SERVER_KEY` dan `MIDTRANS_CLIENT_KEY` di `.env`, pertahankan `MIDTRANS_IS_PRODUCTION=false`, lalu jalankan worker dengan `php artisan queue:work`. Queue worker juga mengirim email transaksi/pengingat dan menghasilkan PDF E-Paper; jalankan terus-menerus di production. Mailer lokal menggunakan `MAIL_MAILER=log`; konfigurasi kredensial mailer production yang disetujui sebelum mengirim email nyata. Status dan percobaan email tersimpan di `notification_deliveries`; setelah memperbaiki penyebab kegagalan, antrekan ulang dengan `php artisan notifications:retry-failed`. Atur URL notifikasi di dashboard Midtrans ke `https://<api-host>/api/payments/webhook`. Endpoint bayar hanya menerima metode dan nominal yang dihitung server; jangan menyimpan atau mengirim data kartu. Jangan aktifkan mode production sebelum akun merchant, webhook, biaya/refund, dan persetujuan operasional diverifikasi.

## Pemeriksaan

```powershell
# Dari backend
php artisan test

# Dari frontend
npm run lint
npm run build
```
