# Panduan Agen — Solopos Membership

## Ringkasan

Solopos Membership adalah monorepo untuk layanan keanggotaan Solopos: katalog paket, autentikasi dan profil, checkout/pembayaran, langganan dan entitlement, clipping, serta E-Paper. Frontend Next.js menyajikan pengalaman web; backend Laravel menyediakan API, aturan bisnis, penyimpanan, dan pemrosesan terjadwal/antrean.

## Struktur

- `backend/` — Laravel API, PHP, Composer, migration, seeder, dan tes.
- `frontend/` — Next.js App Router, React, TypeScript, dan npm.
- `PRD.md` — kebutuhan produk.
- `DESIGN.md` — keputusan baseline dan kontrak desain/API.
- `TASK-LIST.md` — status dan pembagian pekerjaan.

Instruksi backend ada di [`backend/AGENTS.md`](./backend/AGENTS.md); instruksi Next.js ada di [`frontend/AGENTS.md`](./frontend/AGENTS.md). Baca dan ikuti instruksi folder saat mengerjakan bagian tersebut; jangan menyalin atau menggantikannya dengan panduan ini.

## Setup, Jalankan, dan Validasi

Jalankan perintah dari folder bagian terkait. Persyaratan backend: PHP 8.3+ dengan PDO SQLite dan Composer; frontend: Node.js dan npm.

### Backend

```powershell
Set-Location .\backend
composer install
$initializeEnv = -not (Test-Path .env)
if ($initializeEnv) { Copy-Item .env.example .env }
if (-not (Test-Path .\database\database.sqlite)) { New-Item -ItemType File .\database\database.sqlite }
if ($initializeEnv) { php artisan key:generate }
php artisan migrate --seed
php artisan storage:link
php artisan serve
php artisan test
vendor\bin\pint --test
```

### Frontend

```powershell
Set-Location .\frontend
npm ci
npm run dev
npm run lint
npm run build
```

`frontend/package.json` tidak mendefinisikan script test; jangan mengasumsikan ada perintah `npm test`. Instruksi dan kebutuhan dokumentasi Next.js spesifik ada di `frontend/AGENTS.md`.

## Konvensi yang Terlihat di Repo

- Backend mengelompokkan kode di `app/Http/Controllers/Api`, `app/Http/Resources`, `app/Models`, dan `app/Services`; kelas PHP memakai `PascalCase`, metode/properti `camelCase`, dan tabel/field serta payload JSON umumnya `snake_case`.
- Migration menggunakan nama berawalan timestamp; tes backend memakai PHPUnit, berada di `backend/tests/Feature`, dan metode tes bernama `test_...`.
- Frontend memakai App Router dan file halaman `page.tsx`; komponen React bernama `PascalCase`, tipe TypeScript berada di `frontend/types`, dan alias impor `@/` digunakan di kode.
- Ikuti formatter, struktur folder, pola validasi, dan kontrak API yang sudah ada. Jangan mengubah konvensi yang teramati menjadi aturan baru bila sumber terkait tidak mendukungnya.

## Aturan Kerja

- Jangan menampilkan, mengubah, menimpa, atau meng-commit `backend/.env` atau file `.env` lokal lain tanpa izin. Gunakan `.env.example` untuk dokumentasi konfigurasi dan jangan menaruh secret di source control.
- Jangan mengubah migration lama yang mungkin sudah diterapkan. Perubahan skema baru dibuat sebagai migration baru; minta izin sebelum mengubah migration lama.
- Jangan mengedit `backend/composer.lock` secara manual atau memperbaruinya tanpa izin eksplisit. Perubahan dependency/lockfile perlu persetujuan terlebih dahulu.
- Tanyakan kepada pengguna sebelum melanjutkan bila instruksi atau dokumen sumber bertentangan, keputusan produk/akses/retensi belum ditetapkan, perubahan berpotensi merusak data atau kompatibilitas, atau pekerjaan memerlukan akses/secret/approval produksi.
- Jangan menganggap baseline desain sebagai persetujuan produksi. Ikuti instruksi lokal backend/frontend untuk prosedur khusus masing-masing.

## Sumber Kebenaran

- [`PRD.md`](./PRD.md) — kebutuhan dan ruang lingkup produk.
- [`DESIGN.md`](./DESIGN.md) — baseline keputusan, arsitektur, dan kontrak API; persetujuan stakeholder tetap diperlukan untuk keputusan produksi.
- [`TASK-LIST.md`](./TASK-LIST.md) — status dan pembagian task. Perbarui saat task yang diminta selesai.