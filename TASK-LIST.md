# Task List Solopos Membership — Pembagian Frontend / Backend

Daftar ini membagi implementasi berdasarkan pemilik kerja teknis, dengan persyaratan tetap mengacu pada [PRD.md](./PRD.md) versi 1.2. Task dapat dikerjakan paralel jika dependency dan kontrak API-nya sudah disepakati.

**Status task induk saat ini:** 2 selesai, 1 sedang dikerjakan, 23 belum dimulai. Status subtask di bawah mengikuti status task induk terkait, bukan klaim bahwa seluruh subtask sudah selesai.

Legenda: `[x]` selesai · `[~]` sedang dikerjakan · `[ ]` belum dimulai.

## Keputusan bersama sebelum integrasi

- [ ] **Memfinalisasi keputusan produk dan integrasi** *(Lintas tim; task induk `product-discovery-decisions`)* — Tinjau baseline keputusan di [DESIGN.md](./DESIGN.md#mvp-decision-baseline), minta persetujuan pemilik bisnis/IT/legal/CMS untuk harga/kuota, gateway, upgrade, auth/domain, CMS, privasi/watermark, hosting dan kebijakan operasional; catat keputusan final serta pengecualian. Baseline DESIGN dipakai untuk development dan sandbox sambil menunggu persetujuan, bukan persetujuan produksi.  
  *PRD: §17 Fase 0, §19–20.*

## Backend — Laravel API, aturan bisnis, dan data

- [x] **Menyiapkan lingkungan backend lokal** *(task induk `prepare-local-environment`)* — Dependensi Laravel, `.env` lokal, SQLite, migration dan panduan setup.  
  *Hasil: [README.md](./README.md).*
- [x] **Memperbaiki seeder database** *(task induk `fix-database-seeder`)* — Seeder paket dapat dijalankan setelah migration.
- [x] **Menyelesaikan API katalog paket** *(bagian backend dari `finalize-plan-catalog`)* — `GET /api/plans?type=&period=` dengan validasi enum, filter, resource terstruktur, paket aktif saja, urutan stabil, cache publik 60 detik, dan feature tests. Harga/kuota seeded tetap baseline sementara sampai keputusan bisnis final; halaman pricing frontend masih dikerjakan.  
  *PRD: FR-03, US-B1.*
- [x] **Membangun API autentikasi dan profil** *(bagian backend dari `implement-user-auth`)* — Register, verifikasi email, login/logout, lupa/reset sandi, profil/avatar/WhatsApp dan ganti sandi; rate limit. Endpoint terlindungi memakai abstraksi guard `auth:sanctum`; konfigurasi sesi, cookie, token, dan domain tetap dapat disesuaikan IT tanpa mengubah modul bisnis. UI frontend masih belum dikerjakan.  
  *PRD: FR-01–02, US-A1–A4, §11.3.*
- [x] **Membangun API checkout, order, dan voucher** *(`implement-checkout-orders-vouchers`)* — `POST /api/vouchers/validate` dan `POST`/`GET /api/orders` tervalidasi dan owner-scoped; jumlah diskon dihitung server, satu voucher per order, kuota voucher direservasi secara transaksional dan dilepas saat order pending kedaluwarsa setelah 24 jam melalui scheduler. Integrasi pembayaran tetap task terpisah.  
  *PRD: FR-04, FR-15, US-B2, US-B5, §15.*
- [x] **Mengintegrasikan gateway dan webhook pembayaran** *(`integrate-payment-gateway`)* — Midtrans Snap sandbox dengan nominal server-side dan hosted redirect untuk VA, QRIS, dan e-wallet; endpoint bayar/status owner-scoped; signature webhook diverifikasi sebelum receipt persisten; job queue memakai retry/backoff dan pemrosesan idempoten, termasuk validasi nominal/IDR. Memancarkan event `OrderPaid` untuk lifecycle langganan; tidak menyimpan data kartu. Produksi tetap menunggu akun merchant, konfigurasi webhook, dan persetujuan terkait.
  *PRD: FR-04–05, US-B3, §18.*
- [x] **Mengimplementasikan lifecycle langganan dan entitlement** *(`implement-subscription-lifecycle`)* — Pembayaran sukses membuat langganan secara idempoten dalam transaksi yang sama; perpanjangan dijadwalkan setelah `end_date`; task setiap menit mengaktifkan masa baru dan menandai expiry. API owner-scoped menyediakan status, riwayat, tanggal WIB, serta entitlement plan/kuota. Alur dan perhitungan kredit upgrade tetap di task `member-history-upgrade` agar tidak menganggap order biasa sebagai upgrade.
  *PRD: FR-06–07, FR-12–14, US-B4, US-G1–G3, §6.3, §15.*
- [x] **Menyediakan entitlement Free Ads untuk CMS** *(bagian backend dari `integrate-cms-free-ads`)* — `GET /api/entitlements` terautentikasi menjadi sumber kebenaran untuk membership/portal: owner-scoped, hanya mengembalikan hak Free Ads saat langganan aktif, memberi false/zero setelah expiry, dan melarang shared cache. Integrasi aktual pada CMS legacy/server-to-server tetap perlu kontrak dan persetujuan tim CMS; tidak ada shared secret di browser.
  *PRD: FR-08, US-D1–D2, §11.2.*
- [x] **Membangun API clipping dan kuota** *(bagian backend dari `implement-clipping`)* — Endpoint owner-scoped untuk simpan/daftar/cari/hapus; create hanya saat paket aktif memiliki kuota clipping, kuota dihitung transaksional per masa langganan, duplikat artikel ditolak, dan data langganan berakhir tersedia read-only selama 90 hari. Mengembalikan metadata pemakaian/sisa kuota dan error code stabil untuk konflik.
  *PRD: FR-11, US-F1–F2, §15.*
- [x] **Membangun ingestion dan pengelolaan edisi E-Paper** *(bagian backend dari `implement-epaper-management`)* — API admin ber-allowlist email terverifikasi untuk ingest master PDF privat dan thumbnail cover, validasi struktur PDF, SHA-256/ukuran/jumlah halaman, metadata tanggal edisi (termasuk edisi gabungan Sabtu-Minggu), edit draft, dan publish eksplisit. Resource tidak membocorkan path penyimpanan; akses staf umum tetap task backoffice terpisah.
  *PRD: FR-25, US-E6, §22.*
- [ ] **Membangun API reader dan unduhan E-Paper** *(bagian backend dari `implement-epaper-access-download`)* — Daftar/filter edisi, akses berdasarkan irisan rentang tanggal, akses halaman bertanda tangan, PDF ber-watermark email + hash unik 32 hex pada tiap halaman, nama file, signed URL 5 menit, queue/cache/cleanup, batas unduhan dan log IP/perangkat/hash.
  *PRD: FR-09–10, FR-21–24, US-E1–E5, §6.3, §22.*
- [ ] **Membangun API invoice, riwayat, dan upgrade** *(bagian backend dari `member-history-upgrade`)* — Riwayat order/pembayaran/langganan, invoice unduhan, perbandingan dan harga upgrade sesuai keputusan prorata/kredit.  
  *PRD: FR-13–14, US-G1–G2.*
- [ ] **Membangun backoffice dan akses staf** *(bagian backend/admin dari `build-backoffice`)* — Role/otorisasi staf; kelola paket, harga, kuota, voucher dan edisi; lihat pelanggan/order/payment; ekspor laporan dan audit tindakan. Teknologi UI admin (mis. Filament) diputuskan saat discovery.  
  *PRD: FR-20, §5.1.*
- [ ] **Membangun notifikasi, queue, dan scheduler** *(`implement-notifications`)* — Email bukti transaksi sukses/gagal/kedaluwarsa, pengingat H-7/H-3/H-1 dan expiry; log status kirim. WhatsApp opsional berdasarkan keputusan dan provider yang disetujui.  
  *PRD: FR-16, US-H1–H2.*
- [ ] **Menerapkan sesi, persetujuan, dan hak privasi pada backend** *(bagian backend dari `session-device-controls` dan `implement-privacy-controls`)* — Default maksimal dua sesi, otorisasi/revokasi sesuai mekanisme IT, catat consent, retensi serta hak hapus data sesuai UU PDP dan keputusan legal.  
  *PRD: FR-17, §6.3, §10, §18.*
- [ ] **Merekam analitik dan menyediakan data dashboard internal** *(bagian backend dari `implement-analytics`)* — Simpan event funnel dan hitung pelanggan aktif, MRR, churn, konversi paket serta metode pembayaran dengan kontrol akses.  
  *PRD: §3.2, §16.*

## Frontend — Next.js UI dan integrasi API

- [x] **Menyiapkan lingkungan frontend lokal** *(bagian frontend dari `prepare-local-environment`)* — Dependensi npm, build dan panduan development lokal.  
  *Hasil: [README.md](./README.md).*
- [x] **Menyelesaikan halaman pricing** *(bagian frontend dari `finalize-plan-catalog`)* — Tab jenis paket, kartu periode mingguan/bulanan/tahunan, rekomendasi, fitur, harga, loading/error/empty state dan konsumsi API katalog. Tombol checkout tetap dinonaktifkan hingga task checkout selesai.
  *PRD: FR-03, US-B1, §14.*
- [x] **Membangun halaman autentikasi dan profil** *(bagian frontend dari `implement-user-auth`)* — Daftar, masuk, lupa/reset sandi, verifikasi email, pengaturan profil, avatar, ganti sandi, logout, serta state validasi/error; terintegrasi dengan sesi Sanctum SPA backend. Konfigurasi domain/cookie produksi tetap mengikuti persetujuan IT.
  *PRD: FR-01–02, US-A1–A4.*
- [ ] **Membangun checkout dan status pembayaran** *(bagian frontend dari `implement-checkout-orders-vouchers` dan `integrate-payment-gateway`)* — Ringkasan paket, input voucher, biaya/diskon, pilihan bayar, instruksi dan status menunggu/sukses/gagal/kedaluwarsa; tangani retry dan cek status secara jelas.  
  *PRD: US-B2–B5, §14.*
- [ ] **Membangun layout member dan dashboard** *(bagian frontend dari `build-member-dashboard`)* — Navigasi member, badge/status, progress sisa hari, paket/tanggal akhir, sisa kuota, pintasan premium, bantuan dan pengaturan akun.  
  *PRD: FR-12, US-C1–C3, §14.*
- [ ] **Membangun halaman Free Ads** *(bagian frontend dari `integrate-cms-free-ads`)* — Terapkan flag entitlement pada slot iklan portal atau fallback yang disepakati; tampilkan ajakan langganan dan pulihkan iklan bagi pelanggan kedaluwarsa. Integrasi aktual dikoordinasikan dengan tim CMS.  
  *PRD: FR-08, US-D1–D2, §18.*
- [ ] **Membangun UI E-Paper** *(bagian frontend dari `implement-epaper-access-download`)* — Daftar edisi dan pemilih tanggal, cover terbuka/terkunci, viewer halaman, unduhan dengan status menyiapkan/siap/limit tercapai, dan riwayat unduh.  
  *PRD: FR-09–10, FR-21–24, US-E1–E5, §14.*
- [ ] **Membangun UI clipping** *(bagian frontend dari `implement-clipping`)* — Tombol Clip, daftar dan pencarian, hapus, indikator kuota, pesan saat penuh, dan tampilan read-only setelah langganan berakhir.  
  *PRD: FR-11, US-F1–F2.*
- [ ] **Membangun UI riwayat langganan, invoice, dan upgrade** *(bagian frontend dari `member-history-upgrade`)* — Daftar transaksi/langganan, unduh invoice, perbandingan paket dan rincian selisih upgrade.  
  *PRD: FR-13–14, US-G1–G2.*
- [ ] **Membangun UI backoffice bila memakai Next.js** *(bagian frontend/admin dari `build-backoffice`)* — Kerjakan hanya jika discovery memilih admin terpisah; jika memakai panel Laravel seperti Filament, task ini tidak diperlukan dan UI ditangani task backend/admin.  
  *PRD: FR-20, §11.4.*
- [ ] **Menerapkan consent dan pengalaman privasi** *(bagian frontend dari `implement-privacy-controls`)* — Tampilkan persetujuan dan tautan kebijakan/syarat watermark, sediakan alur permintaan hak data sesuai desain legal; jangan tampilkan klaim persetujuan yang tidak dicatat backend.  
  *PRD: §10, §20.*
- [ ] **Mengirim event analitik dari UI** *(bagian frontend dari `implement-analytics`)* — Instrumentasikan event pricing, pemilihan paket, checkout, login, E-Paper, clipping, kuota, reminder dan upgrade sesuai skema bersama; hindari data pribadi yang tidak diperlukan.  
  *PRD: §16.*

## Lintas tim — Kontrak, QA, infrastruktur, dan rilis

- [ ] **Meninjau dan menjaga kontrak API dan state UI** — Gunakan kontrak, format error, autentikasi dan state loading/error/empty yang sudah didefinisikan di [DESIGN.md](./DESIGN.md#api-contract-and-frontend-state-conventions) sebagai baseline; Frontend dan Backend mengonfirmasi bagian per fitur, menambah contoh request/response yang belum ada, serta memperbarui dokumen saat implementasi berbeda. Berlaku untuk API di PRD §13.
- [x] **Membatasi sesi dan perangkat secara end-to-end** *(task induk `session-device-controls`)* — Database session store menegakkan batas dua sesi, mencabut sesi paling lama saat login ketiga, dan menyediakan daftar/revokasi sesi milik pengguna; halaman pengaturan menampilkan perangkat, sesi saat ini, waktu aktivitas, dan aksi pencabutan serta memberi tahu saat sesi lama dicabut.
  *PRD: FR-17.*
- [ ] **Memvalidasi MySQL via Docker** *(task induk `validate-mysql-docker-integration`)* — Setelah fitur backend stabil dan sebelum UAT, jalankan MySQL 8 melalui [docker-compose.yml](./backend/docker-compose.yml); uji migration/seed DB kosong dan regresi di SQLite serta MySQL. Docker belum tersedia di mesin saat terakhir diperiksa.
- [ ] **Memenuhi target kualitas nonfungsional** *(task induk `nonfunctional-quality`)* — Ukur API p95 <500 ms, LCP <2,5 dtk, halaman pertama viewer <2 dtk, PDF 12 halaman <10 dtk; responsif, dua versi browser terakhir, WCAG 2.1 AA, SEO/metadata/sitemap, Bahasa Indonesia, WIB dan IDR. FE dan BE mencatat hasil per lapisan.  
  *PRD: §3.2, §10.*
- [ ] **Menulis dan menjalankan test lintas lapisan** *(task induk `add-mvp-automated-tests`)* — Backend menulis unit/feature/integration tests; frontend menulis component/flow tests; bersama-sama verifikasi webhook, checkout, entitlement, konten, clipping, privacy dan regresi MySQL/SQLite.  
  *PRD: §11.4, §17 Fase 4.*
- [ ] **Menyiapkan operasi dan deployment** *(task induk `production-operations`)* — CI/CD, staging/production, MySQL, Redis queue/cache/rate limit, private object storage/CDN, worker/scheduler, HTTPS/secrets, backup dan restore.  
  *PRD: §11.1–11.2, §17–18.*
- [ ] **Menjalankan security, performance, dan UAT** *(task induk `security-performance-uat`)* — Review keamanan dan otorisasi, uji beban/recovery webhook, log/alert, UAT stakeholder serta beta tertutup.  
  *PRD: §10, §17 Fase 4.*
- [ ] **Menyelesaikan dokumentasi dan verifikasi rilis** *(task induk `document-and-verify-release`)* — Smoke test staging, konfigurasi, backup/restore, observabilitas, kriteria rilis dan sign-off stakeholder.  
  *PRD: §17.*

## Di luar MVP

Aplikasi native, auto-renewal, paket korporat/multi-user, Google Login, pengiriman koran cetak, dan rekomendasi artikel AI bukan syarat MVP. Catat sebagai backlog pasca-rilis.  
*PRD: §5.2, §17.*
