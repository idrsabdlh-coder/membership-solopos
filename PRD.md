# Product Requirements Document (PRD)
## Sistem Langganan Membership Solopos

| Item | Keterangan |
|---|---|
| **Nama Produk** | Solopos Membership (Langganan Membership Solopos) |
| **Versi Dokumen** | 1.2 (Draft): periode Mingguan/Bulanan/Tahunan, unduh PDF E-Paper, struktur file |
| **Tanggal** | 6 Oktober 2026 |
| **Peran Penyusun** | Product Manager |
| **Status** | Draft untuk review stakeholder |
| **Tech Stack** | Backend: Laravel · Frontend: Next.js |
| **Referensi Benchmark** | Member area Harian Jogja (`member.harianjogja.com`) |

---

## 1. Ringkasan Eksekutif

Solopos Membership adalah layanan langganan digital yang memungkinkan pelanggan mengakses konten Solopos dengan **hak akses terbatas sesuai paket** yang dipilih. Pelanggan dapat membaca berita tanpa iklan (*Free Ads*), membaca dan mengunduh koran digital dalam format PDF (*E-Paper*), menyimpan artikel pilihan (*Clipping*), serta mengelola langganan secara mandiri (beli, perpanjang, upgrade) melalui satu *member area*.

Kata "terbatas" pada tujuan proyek diterjemahkan sebagai **pembatasan akses berbasis paket (entitlement) dan kuota**, misalnya masa aktif (mingguan, bulanan, tahunan), edisi yang dapat dibuka dan diunduh selama masa aktif, batas unduhan, dan jumlah clipping maksimal.

## 2. Latar Belakang dan Permasalahan

**Latar belakang**
- Pendapatan media cetak dan iklan digital terus tertekan, sehingga dibutuhkan sumber pendapatan berulang (*recurring revenue*) dari pembaca.
- Pembaca ingin pengalaman membaca yang nyaman (tanpa iklan mengganggu) dan akses koran digital kapan saja.
- Kompetitor regional sudah memiliki sistem membership. Harian Jogja, misalnya, memiliki *member area* dengan paket 1, 3, dan 6 bulan.

**Permasalahan yang diselesaikan**
1. Belum ada jalur langganan digital mandiri yang terintegrasi (daftar, bayar, aktif otomatis).
2. Tidak ada mekanisme membedakan akses pembaca umum dan pelanggan berbayar.
3. Proses langganan manual membebani tim dan tidak terukur.
4. Tidak ada data perilaku pelanggan untuk retensi dan penentuan harga.

## 3. Tujuan dan Indikator Keberhasilan

### 3.1 Tujuan Produk
| # | Tujuan |
|---|---|
| G1 | Menyediakan layanan langganan membership digital dengan akses terbatas per paket |
| G2 | Memungkinkan pelanggan mendaftar, membayar, dan mengaktifkan langganan secara mandiri tanpa intervensi staf |
| G3 | Memberikan nilai nyata bagi pelanggan: bebas iklan, E-Paper, dan Clipping |
| G4 | Membangun basis pendapatan berulang yang terukur |

### 3.2 Success Metrics (target 6 bulan pertama setelah rilis, dapat disesuaikan)
| Metrik | Target Awal |
|---|---|
| Jumlah pelanggan aktif berbayar | Ditetapkan bersama bisnis (placeholder: 1.000) |
| Conversion rate (registrasi → bayar) | ≥ 8% |
| Payment success rate | ≥ 90% |
| Retention (perpanjang saat masa aktif habis) | ≥ 35% |
| Aktivasi otomatis setelah bayar < 1 menit | ≥ 99% |
| Waktu muat halaman member area (LCP) | < 2,5 detik |
| Tiket bantuan terkait pembayaran/aktivasi | Turun 50% dibanding proses manual |

## 4. Stakeholders dan Persona

### 4.1 Stakeholder Utama
| Stakeholder | Peran | Kebutuhan Utama |
|---|---|---|
| **Pelanggan (Primary)** | Pengguna akhir yang berlangganan | Berlangganan mudah, bayar aman, akses konten sesuai paket, kelola akun sendiri |

> Catatan: sesuai ruang lingkup yang ditetapkan, pelanggan adalah stakeholder utama. Admin/operasional internal dicantumkan hanya sebagai pendukung (lihat bagian 6, fitur backoffice minimum) agar sistem dapat dioperasikan.

### 4.2 Persona Pelanggan
**Persona 1: Bu Rini (42), pembaca setia koran cetak**
Ingin membaca koran versi digital setiap pagi, terbiasa dengan format halaman koran. Kebutuhan: paket E-Paper, antarmuka sederhana, pembayaran mudah (transfer/QRIS).

**Persona 2: Dimas (24), pembaca berita mobile**
Membaca berita lewat ponsel, terganggu iklan. Kebutuhan: paket Free Ads, simpan artikel penting, bayar via e-wallet.

**Persona 3: Pak Hendra (55), pelaku usaha dan peneliti lokal**
Sering mencari arsip berita. Kebutuhan: paket lengkap (Free Ads + E-Paper), Clipping dengan kuota besar, arsip E-Paper.

## 5. Ruang Lingkup

### 5.1 In Scope (MVP)
- Registrasi dan login pelanggan (email + kata sandi, opsional Google Login)
- Katalog paket dan halaman pricing
- Checkout dan pembayaran (payment gateway) dengan aktivasi otomatis
- Member area: Dashboard, Clipping, Berlangganan, Upgrade, Pengaturan
- Fitur Free Ads (bebas iklan) pada portal berita
- E-Paper reader, unduh PDF edisi (ber-watermark), dan akses edisi sesuai masa aktif paket (mingguan, bulanan, tahunan)
- Clipping artikel dengan kuota per paket
- Notifikasi (email dan opsional WhatsApp) untuk transaksi dan masa aktif
- Backoffice minimum: kelola paket, harga, voucher, dan lihat transaksi/pelanggan

### 5.2 Out of Scope (MVP)
- Aplikasi mobile native (ditangani sebagai PWA/responsive web)
- Auto-debit/langganan berulang otomatis (fase 2)
- Berlangganan korporat/multi-user (fase 2)
- Pengiriman koran cetak
- Rekomendasi artikel berbasis AI

## 6. Paket Langganan dan Pembatasan Akses

Paket dibentuk dari dua dimensi: **jenis paket** (hak akses) dan **periode** (Mingguan, Bulanan, Tahunan). Pelanggan mendapat edisi koran yang terbit selama masa aktifnya. **Harga dan kuota di bawah adalah usulan awal** dan harus ditetapkan tim bisnis Solopos.

### 6.1 Jenis Paket (Tab)
| Jenis Paket | Hak Akses |
|---|---|
| **Free Ads & E-Paper** | Portal berita tanpa iklan + baca dan unduh PDF E-Paper + Clipping |
| **Free Ads** | Portal berita tanpa iklan + Clipping (tanpa E-Paper) |
| **E-Paper** | Baca dan unduh PDF E-Paper |

### 6.2 Periode, Edisi, dan Kuota (contoh untuk Free Ads & E-Paper)
| Periode | Masa Aktif | Edisi yang Diperoleh (perkiraan) | Harga (usulan) | Kuota Clipping | Batas Unduhan (usulan) |
|---|---|---|---|---|---|
| **Mingguan** | 7 hari | Semua edisi terbit dalam 7 hari, sekitar 6 file PDF | Rp 10.000 | 5 artikel | 3x per edisi, 10x per hari |
| **Bulanan** (Rekomendasi) | 30 hari | Sekitar 26 edisi | Rp 30.000 | 20 artikel | 3x per edisi, 10x per hari |
| **Tahunan** | 365 hari | Sekitar 313 edisi | Rp 300.000 | 200 artikel | 3x per edisi, 10x per hari |

Catatan:
- Edisi akhir pekan terbit sebagai **satu PDF gabungan** (contoh: "Sabtu Wage-Minggu Kliwon, 19-20 September 2026"), sehingga satu minggu berisi sekitar 6 edisi, bukan 7. Asumsi ini perlu dikonfirmasi redaksi.
- Sebagai acuan, harga eceran koran tercetak pada contoh PDF adalah Rp5.000 per edisi (6 edisi seminggu = sekitar Rp30.000), sehingga harga digital harus tampak lebih hemat.
- Harga paket Free Ads saja dan E-Paper saja ditentukan bisnis.

**Contoh:** pelanggan membeli paket Mingguan pada Senin, 21 September 2026. Masa aktif 21-27 September 2026 (berakhir pukul 23:59 WIB). Ia dapat membaca dan mengunduh edisi Senin sampai Jumat, ditambah edisi gabungan Sabtu-Minggu (26-27 September). Edisi sebelum 21 September dan sesudah 27 September terkunci.

### 6.3 Aturan Pembatasan (Entitlement Rules)
1. Akses langganan aktif sejak pembayaran terkonfirmasi (waktu WIB) sampai `end_date`.
2. Sebuah edisi dapat dibuka dan diunduh bila **rentang tanggal edisi** (`publish_date` sampai `publish_end_date`) beririsan dengan masa aktif. Edisi gabungan dihitung satu edisi.
3. Edisi di luar masa aktif terkunci. Pelanggan hanya melihat cover dan ajakan perpanjang atau upgrade.
4. Setelah masa aktif habis, akses baca dan unduh dihentikan. File PDF yang sudah diunduh tetap berada di perangkat pelanggan (tidak dapat ditarik kembali), tetapi membawa watermark identitas pelanggan.
5. Unduhan dibatasi per edisi dan per hari (nilai default di atas, dapat diatur per paket dari backoffice).
6. Paket Free Ads saja tidak dapat mengunduh E-Paper.
7. Kuota clipping dihitung per periode langganan aktif. Jika penuh, pelanggan menghapus clipping lama atau upgrade.
8. Jumlah sesi aktif bersamaan dibatasi (default: 2 sesi) untuk mencegah *account sharing*.
9. Perpanjangan saat masih aktif menyambung dari `end_date` lama tanpa celah edisi. Upgrade (mis. Mingguan ke Bulanan) dihitung prorata atau kredit (lihat Open Questions).

## 7. User Journey Utama

```
Pengunjung → Lihat paket → Daftar/Masuk → Pilih paket → Checkout
   → Bayar (VA/QRIS/e-wallet) → Webhook konfirmasi → Aktivasi otomatis
   → Dashboard (status aktif) → Baca tanpa iklan / E-Paper / Unduh PDF / Clipping
   → Pengingat masa aktif (H-7, H-3, H-1) → Perpanjang / Upgrade
```

## 8. User Stories dan Acceptance Criteria

### Epic A: Akun dan Autentikasi
| ID | User Story | Acceptance Criteria |
|---|---|---|
| US-A1 | Sebagai pengunjung, saya ingin mendaftar akun agar dapat berlangganan | Form: nama lengkap, email, kata sandi. Email unik. Verifikasi email dikirim. Pesan error jelas |
| US-A2 | Sebagai pelanggan, saya ingin masuk dan keluar | Login dengan email + sandi. Sesi aman. Logout menghapus sesi |
| US-A3 | Sebagai pelanggan, saya ingin mereset kata sandi | Tautan reset via email berlaku 60 menit dan sekali pakai |
| US-A4 | Sebagai pelanggan, saya ingin mengubah data diri | Dapat ubah foto profil, nama lengkap, nomor WhatsApp (+62). Email hanya-baca. Dapat ganti kata sandi dengan konfirmasi kata sandi baru |

### Epic B: Katalog dan Pembelian
| ID | User Story | Acceptance Criteria |
|---|---|---|
| US-B1 | Sebagai calon pelanggan, saya ingin melihat paket dan harga | Halaman pricing dengan tab jenis paket dan kartu durasi. Paket rekomendasi diberi label. Ada tautan "Selengkapnya" untuk detail manfaat |
| US-B2 | Sebagai pelanggan, saya ingin membeli paket | Tombol "Beli Sekarang" membuka ringkasan pesanan dan pilihan metode bayar. Wajib login |
| US-B3 | Sebagai pelanggan, saya ingin membayar dengan metode yang saya sukai | Mendukung VA bank, QRIS, e-wallet (via payment gateway). Batas waktu bayar 24 jam |
| US-B4 | Sebagai pelanggan, saya ingin langganan aktif otomatis setelah bayar | Status berubah ke "Aktif" maksimal 1 menit setelah webhook sukses. Email konfirmasi terkirim |
| US-B5 | Sebagai pelanggan, saya ingin memakai kode voucher | Validasi kode, masa berlaku, batas pemakaian. Potongan tampil di ringkasan |

### Epic C: Dashboard Member
| ID | User Story | Acceptance Criteria |
|---|---|---|
| US-C1 | Sebagai pelanggan, saya ingin melihat status langganan | Dashboard menampilkan nama, badge status (VIP), jenis paket, tanggal berakhir, dan indikator sisa hari (progress ring) |
| US-C2 | Sebagai pelanggan, saya ingin pintasan fitur premium | Kartu "Bebas Iklan" dan "Baca Koran" mengarah ke portal tanpa iklan dan E-Paper |
| US-C3 | Sebagai pelanggan, saya ingin bantuan | Tautan "Perlu Bantuan?" membuka kontak CS/FAQ |

### Epic D: Free Ads
| ID | User Story | Acceptance Criteria |
|---|---|---|
| US-D1 | Sebagai pelanggan aktif, saya ingin membaca tanpa iklan | Saat login dan langganan aktif, slot iklan tidak dirender. Berlaku di web dan mobile web |
| US-D2 | Sebagai pelanggan kedaluwarsa, saya ingin tahu statusnya | Iklan kembali tampil. Muncul banner ajakan perpanjang |

### Epic E: E-Paper dan Unduh PDF
| ID | User Story | Acceptance Criteria |
|---|---|---|
| US-E1 | Sebagai pelanggan E-Paper, saya ingin membaca edisi terbaru | Halaman E-Paper menampilkan edisi terbaru (tanggal dan nomor edisi) dengan viewer halaman penuh |
| US-E2 | Sebagai pelanggan, saya ingin mengunduh edisi dalam format PDF | Tombol "Unduh PDF" tampil pada edisi yang berhak diakses. File berisi seluruh halaman edisi dan bernama `ddmmyyyy-Koran-Solopos_watermarked.pdf`. Jika file belum siap, tampil status "Sedang menyiapkan" dan unduhan berjalan otomatis (target < 10 detik) |
| US-E3 | Sebagai pelanggan mingguan, bulanan, atau tahunan, saya ingin mendapat semua edisi selama masa aktif | Daftar edisi menampilkan semua edisi yang rentang tanggalnya beririsan dengan masa aktif. Contoh: paket Mingguan memberi sekitar 6 edisi (edisi gabungan Sabtu-Minggu dihitung satu) |
| US-E4 | Sebagai pelanggan, saya ingin memilih edisi lain | Daftar "Edisi E-Paper Lainnya" dan pemilih tanggal (kalender). Edisi di luar masa aktif menampilkan cover terkunci dan ajakan perpanjang atau upgrade |
| US-E5 | Sebagai pemilik konten, saya ingin file terlindungi dan dapat dilacak | Setiap PDF diberi watermark di setiap halaman: email pelanggan dan kode hash unik per unduhan. Unduhan memakai signed URL berumur pendek (5 menit). Unduhan dibatasi (default 3x per edisi, 10x per hari). Setiap unduhan dicatat (waktu, IP, perangkat, hash) |
| US-E6 | Sebagai pengelola konten, saya ingin mengunggah edisi harian | Backoffice menerima PDF master, tanggal terbit (dan tanggal akhir untuk edisi gabungan), serta nomor edisi. Sistem memvalidasi PDF, menghitung halaman, membuat cover dan thumbnail, lalu menerbitkan edisi |

### Epic F: Clipping
| ID | User Story | Acceptance Criteria |
|---|---|---|
| US-F1 | Sebagai pelanggan, saya ingin menyimpan artikel | Tombol "Clip" pada artikel. Ditolak dengan pesan jelas bila kuota penuh |
| US-F2 | Sebagai pelanggan, saya ingin melihat dan menghapus clipping | Halaman Clipping menampilkan daftar, pencarian, dan hapus. Sisa kuota tampil (mis. 4/10) |

### Epic G: Berlangganan dan Upgrade
| ID | User Story | Acceptance Criteria |
|---|---|---|
| US-G1 | Sebagai pelanggan, saya ingin melihat riwayat langganan | Daftar: paket, periode, harga, status pembayaran, invoice dapat diunduh |
| US-G2 | Sebagai pelanggan, saya ingin upgrade paket | Halaman Upgrade menampilkan perbandingan. Perhitungan selisih harga transparan sebelum bayar |
| US-G3 | Sebagai pelanggan, saya ingin perpanjang | Perpanjangan menambah masa aktif dari `end_date` bila masih aktif, atau dari hari ini bila sudah habis |

### Epic H: Notifikasi
| ID | User Story | Acceptance Criteria |
|---|---|---|
| US-H1 | Sebagai pelanggan, saya ingin diingatkan sebelum habis | Email (dan WhatsApp bila nomor tersedia) dikirim H-7, H-3, H-1, dan saat kedaluwarsa |
| US-H2 | Sebagai pelanggan, saya ingin bukti transaksi | Email invoice setelah pembayaran sukses atau gagal/kedaluwarsa |

## 9. Kebutuhan Fungsional (Ringkasan)

| ID | Kebutuhan | Prioritas |
|---|---|---|
| FR-01 | Registrasi, verifikasi email, login, logout, reset sandi | Must |
| FR-02 | Manajemen profil (foto, nama, ganti sandi) | Must |
| FR-03 | Katalog paket (jenis × periode: mingguan/bulanan/tahunan) dikelola dari backoffice | Must |
| FR-04 | Checkout dan integrasi payment gateway | Must |
| FR-05 | Webhook pembayaran dengan verifikasi signature dan idempotensi | Must |
| FR-06 | Aktivasi langganan otomatis dan perhitungan masa aktif | Must |
| FR-07 | Middleware entitlement (cek akses per fitur dan kuota) | Must |
| FR-08 | Penyembunyian iklan untuk pelanggan Free Ads | Must |
| FR-09 | E-Paper viewer, daftar edisi, pemilih tanggal | Must |
| FR-10 | Pembatasan akses edisi berdasarkan rentang masa aktif paket | Must |
| FR-11 | Clipping dengan kuota | Must |
| FR-12 | Dashboard status langganan | Must |
| FR-13 | Riwayat transaksi dan invoice | Should |
| FR-14 | Upgrade paket | Should |
| FR-15 | Voucher/kode promo | Should |
| FR-16 | Pengingat masa aktif (email) | Should |
| FR-17 | Pembatasan sesi/perangkat | Should |
| FR-18 | Login Google | Could |
| FR-19 | Auto-renewal | Won't (fase 2) |
| FR-20 | Backoffice: paket, harga, voucher, transaksi, pelanggan, ekspor laporan | Must (minimum) |
| FR-21 | Unduh PDF edisi (seluruh halaman) untuk edisi yang berhak diakses | Must |
| FR-22 | Watermark dinamis di setiap halaman PDF: email pelanggan dan hash unik per unduhan | Must |
| FR-23 | Batas unduhan per edisi dan per hari, dapat diatur per paket | Should |
| FR-24 | Log unduhan (waktu, IP, perangkat, hash) untuk pelacakan | Should |
| FR-25 | Backoffice: unggah dan terbitkan PDF edisi, termasuk edisi gabungan akhir pekan | Must |

## 10. Kebutuhan Non-Fungsional

| Kategori | Kebutuhan |
|---|---|
| **Performa** | LCP < 2,5 dtk di 4G; API p95 < 500 ms; viewer E-Paper memuat halaman pertama < 2 dtk; pembuatan PDF ber-watermark 12 halaman < 10 dtk (hasil di-cache untuk unduhan ulang) |
| **Ketersediaan** | Uptime ≥ 99,5%; webhook pembayaran harus tahan gangguan (retry dan antrean) |
| **Keamanan** | HTTPS wajib, hash sandi bcrypt/argon2, proteksi CSRF injection, rate limiting login, validasi signature webhook, tidak menyimpan data kartu |
| **Privasi** | Kepatuhan UU Pelindungan Data Pribadi (UU PDP): persetujuan, kebijakan privasi, hak hapus data |
| **Skalabilitas** | Mampu menangani lonjakan trafik saat berita besar (cache, CDN, antrean) |
| **Kompatibilitas** | Responsif (mobile-first), browser modern (Chrome, Safari, Firefox, Edge 2 versi terakhir) |
| **Aksesibilitas** | Mengacu WCAG 2.1 AA untuk kontras dan navigasi keyboard |
| **Observabilitas** | Log terpusat, monitoring error, alert kegagalan pembayaran/webhook |
| **SEO** | Halaman publik (pricing) SSR/ISR, metadata dan sitemap |
| **Lokalisasi** | Bahasa Indonesia, zona waktu WIB, mata uang IDR |

## 11. Arsitektur dan Tech Stack

### 11.1 Gambaran Arsitektur
```
[Browser / Mobile Web]
        │  HTTPS
        ▼
[Next.js Frontend]  ── SSR, UI member area
        │  REST API (JSON)
        ▼
[Laravel Backend API]
   ├─ Auth (mekanisme ditentukan manajer IT Solopos, lihat 11.3)
   ├─ Subscription & Entitlement service
   ├─ Payment service ◄──── Webhook ──── [Payment Gateway]
   ├─ E-Paper service (signed URL, unduh PDF + watermark dinamis via queue)
   ├─ Clipping service
   ├─ Notification (Queue → Email)
   └─ Backoffice (panel admin)
        │
        ├─ MySQL (data utama)
        ├─ Redis (cache, queue, rate limit)
        └─ Object Storage + CDN (file E-Paper)
```

### 11.2 Rekomendasi Teknis
| Lapisan | Pilihan |
|---|---|
| Backend | Laravel (versi LTS terbaru), Queue (Redis), Scheduler untuk cek kedaluwarsa dan pengingat |
| Frontend | Next.js (App Router), TypeScript, Tailwind CSS, SWR/React Query |
| Database | MySQL 8 atau PostgreSQL |
| Pembayaran | Midtrans atau Xendit (VA, QRIS, e-wallet) |
| Notifikasi | SMTP/transactional email; WhatsApp via provider resmi |
| Penyimpanan | S3-compatible storage privat (PDF master edisi dan PDF ber-watermark sementara) + CDN untuk halaman E-Paper |
| Pemrosesan PDF | Library PDF di Laravel (mis. FPDI/TCPDF) atau tool CLI (qpdf/Ghostscript) yang dijalankan lewat queue untuk watermark dinamis. Periksa kompatibilitas dan lisensi untuk PDF terkompresi |
| DevOps | Docker, CI/CD, staging dan production, backup harian |
| Integrasi CMS | Hook ke CMS Solopos untuk status artikel dan slot iklan (perlu dikonfirmasi) |

### 11.3 Catatan Autentikasi (Ditunda)
Mekanisme autentikasi dan manajemen sesi (penyimpanan token/cookie, pengaturan domain frontend-backend, dan pengamanan terkait) **tidak ditetapkan dalam PRD ini** dan akan ditambahkan oleh Manajer IT Solopos. Tim pengembang cukup memisahkan logika autentikasi dalam modul tersendiri agar mudah diganti atau disesuaikan nanti, tanpa mengubah modul langganan, pembayaran, E-Paper, dan Clipping.

### 11.4 Struktur File Backend (Laravel)

Disarankan **dua repository terpisah** (`solopos-membership-api` dan `solopos-membership-web`) karena di-deploy sendiri-sendiri. Struktur dikelompokkan per domain fitur. Modul autentikasi sengaja dipisah agar mudah diganti saat Manajer IT menetapkan mekanismenya.

```
solopos-membership-api/
├── app/
│   ├── Http/
│   │   ├── Controllers/Api/
│   │   │   ├── Auth/                       # dipisah, diisi/diganti sesuai keputusan IT
│   │   │   ├── ProfileController.php
│   │   │   ├── PlanController.php
│   │   │   ├── OrderController.php
│   │   │   ├── PaymentWebhookController.php
│   │   │   ├── SubscriptionController.php
│   │   │   ├── EpaperController.php         # daftar edisi, halaman viewer
│   │   │   ├── EpaperDownloadController.php # unduh PDF ber-watermark, riwayat unduhan
│   │   │   ├── ClippingController.php
│   │   │   └── EntitlementController.php
│   │   ├── Middleware/
│   │   │   ├── EnsureActiveSubscription.php
│   │   │   ├── EnsureEditionAccess.php      # cek edisi beririsan dengan masa aktif
│   │   │   ├── CheckDownloadLimit.php       # batas per edisi dan per hari
│   │   │   └── CheckQuota.php               # kuota clipping
│   │   ├── Requests/                        # Form Request (validasi)
│   │   └── Resources/                       # API Resource (format JSON)
│   ├── Models/
│   │   ├── User.php
│   │   ├── Plan.php
│   │   ├── Subscription.php
│   │   ├── Order.php
│   │   ├── Payment.php
│   │   ├── Voucher.php
│   │   ├── Clipping.php
│   │   ├── EpaperEdition.php
│   │   ├── EpaperPage.php
│   │   └── EpaperDownload.php
│   ├── Services/                            # logika bisnis utama
│   │   ├── Subscription/
│   │   │   ├── ActivateSubscriptionService.php
│   │   │   ├── RenewSubscriptionService.php
│   │   │   └── UpgradeSubscriptionService.php
│   │   ├── Payment/
│   │   │   ├── PaymentGatewayInterface.php
│   │   │   └── MidtransGateway.php          # atau XenditGateway
│   │   ├── Entitlement/
│   │   │   ├── EntitlementService.php
│   │   │   └── EditionAccessService.php     # aturan irisan tanggal edisi vs masa aktif
│   │   ├── Epaper/
│   │   │   ├── PublishEditionService.php    # validasi PDF master, cover, thumbnail
│   │   │   ├── WatermarkPdfService.php      # email + hash unik di setiap halaman
│   │   │   ├── DownloadFileNameService.php  # ddmmyyyy-Koran-Solopos_watermarked.pdf
│   │   │   └── SignedUrlService.php
│   │   └── Clipping/ClippingQuotaService.php
│   ├── Jobs/
│   │   ├── ProcessPaymentWebhook.php
│   │   ├── GenerateWatermarkedPdf.php       # dijalankan lewat queue
│   │   ├── CleanupTempPdfs.php
│   │   └── SendNotification.php
│   ├── Notifications/                       # email dan WhatsApp
│   ├── Console/Commands/
│   │   ├── ExpireSubscriptions.php
│   │   ├── ExpirePendingOrders.php
│   │   └── SendRenewalReminders.php
│   ├── Filament/ (atau Admin/)              # backoffice minimum
│   │   └── Resources/                       # Plan, Voucher, Order, User, Edition (unggah PDF)
│   ├── Enums/                               # SubscriptionStatus, PlanType, BillingPeriod
│   └── Policies/
├── config/
│   ├── cors.php
│   └── epaper.php                           # batas unduhan default, masa berlaku signed URL
├── database/
│   ├── migrations/
│   ├── seeders/                             # PlanSeeder (paket mingguan, bulanan, tahunan)
│   └── factories/
├── routes/
│   ├── api.php
│   └── console.php                          # jadwal scheduler
├── storage/app/private/
│   ├── epaper/master/                       # PDF master (bersih), tidak publik
│   └── epaper/tmp/                          # PDF ber-watermark sementara
├── tests/
│   ├── Feature/                             # webhook, aktivasi, akses edisi, unduhan, kuota
│   └── Unit/                                # perhitungan masa aktif, nama file, hash
├── .env.example
└── docker-compose.yml
```

Dua hal yang sengaja dipisah:
- `Services/` menyimpan aturan bisnis (aktivasi, akses edisi, watermark) agar controller tetap tipis dan mudah dites.
- `PaymentGatewayInterface` membuat Midtrans bisa diganti Xendit tanpa mengubah alur langganan.

### 11.5 Struktur File Frontend (Next.js, App Router)

```
solopos-membership-web/
├── src/
│   ├── app/
│   │   ├── (public)/
│   │   │   ├── langganan/page.tsx           # pricing: tab jenis + kartu Mingguan/Bulanan/Tahunan
│   │   │   ├── masuk/page.tsx
│   │   │   ├── daftar/page.tsx
│   │   │   └── lupa-sandi/page.tsx
│   │   ├── (member)/
│   │   │   ├── layout.tsx                   # sidebar member area
│   │   │   ├── dashboard/page.tsx
│   │   │   ├── clipping/page.tsx
│   │   │   ├── berlangganan/page.tsx
│   │   │   ├── upgrade/page.tsx
│   │   │   ├── unduhan/page.tsx             # riwayat unduhan dan sisa batas hari ini
│   │   │   └── pengaturan/page.tsx
│   │   ├── e-paper/
│   │   │   ├── page.tsx                     # daftar edisi (terkunci/terbuka sesuai masa aktif)
│   │   │   └── [editionId]/page.tsx         # viewer + tombol Unduh PDF
│   │   ├── checkout/
│   │   │   ├── [planId]/page.tsx
│   │   │   └── status/[orderId]/page.tsx
│   │   ├── layout.tsx
│   │   └── globals.css
│   ├── components/
│   │   ├── ui/                              # Button, Input, Modal, Badge
│   │   ├── layout/                          # Header, Footer, MemberSidebar
│   │   ├── plans/                           # PlanTabs, PlanCard, PeriodSwitcher
│   │   ├── dashboard/                       # SubscriptionStatus, ProgressRing
│   │   ├── epaper/                          # EpaperViewer, EditionList, EditionCard,
│   │   │                                    # DatePicker, DownloadButton, LockedEdition
│   │   └── clipping/                        # ClippingList, QuotaIndicator
│   ├── lib/
│   │   ├── api/                             # client.ts, plans.ts, orders.ts, epaper.ts, dll
│   │   ├── auth/                            # dipisah, diisi sesuai keputusan Manajer IT
│   │   ├── utils/                           # format rupiah, tanggal WIB, nama file unduhan
│   │   └── constants.ts
│   ├── hooks/                               # useSubscription, useEntitlements,
│   │                                        # useEditions, useDownload, useClippings
│   ├── types/                               # Plan, Subscription, Edition, Download, dll
│   └── middleware.ts                        # proteksi rute member area
├── public/
├── .env.example                             # NEXT_PUBLIC_API_URL
├── next.config.js
├── tailwind.config.ts
└── package.json
```

Catatan:
- `(public)` dan `(member)` adalah *route group*: nama dalam kurung tidak muncul di URL, tetapi memungkinkan layout berbeda.
- Halaman `e-paper` berada di luar route group karena dapat dilihat semua pengunjung. Edisi di luar masa aktif tampil sebagai cover terkunci dengan ajakan berlangganan.
- Komponen `DownloadButton` menangani tiga kondisi: sedang menyiapkan file, siap diunduh (signed URL), dan batas unduhan tercapai.
- `lib/auth/` dan komentar autentikasi di `client.ts` adalah titik penyambungan untuk keputusan Manajer IT.

## 12. Model Data (Garis Besar)

| Tabel | Kolom Utama |
|---|---|
| `users` | id, name, email (unique), email_verified_at, password, whatsapp, avatar_path |
| `plans` | id, type (`free_ads_epaper`/`free_ads`/`epaper`), name, billing_period (`weekly`/`monthly`/`yearly`), duration_days, price, clipping_quota, download_limit_per_edition, download_limit_per_day, is_recommended, is_active |
| `subscriptions` | id, user_id, plan_id, status (`pending`/`active`/`expired`/`cancelled`), start_date, end_date, source_order_id |
| `orders` | id, user_id, plan_id, voucher_id, subtotal, discount, total, status, expires_at |
| `payments` | id, order_id, gateway, gateway_ref, method, amount, status, paid_at, raw_payload |
| `vouchers` | id, code, type, value, quota, used, valid_from, valid_until |
| `clippings` | id, user_id, subscription_id, article_ref, title, url, created_at |
| `epaper_editions` | id, edition_number, edition_year, publish_date, publish_end_date (edisi gabungan), master_pdf_path, checksum, file_size, cover_path, page_count, status |
| `epaper_pages` | id, edition_id, page_no, file_path |
| `epaper_downloads` | id, user_id, subscription_id, edition_id, watermark_hash (32 hex), file_name, ip_address, user_agent, downloaded_at |
| `user_sessions` | id, user_id, device_info, last_active_at |
| `notifications_log` | id, user_id, channel, type, status, sent_at |
| `audit_logs` | id, actor_id, action, entity, meta, created_at |

## 13. Rancangan API (Ringkas)

| Method | Endpoint | Fungsi |
|---|---|---|
| POST | `/api/auth/register` | Registrasi |
| POST | `/api/auth/login` · `/logout` | Masuk dan keluar |
| POST | `/api/auth/forgot-password` · `/reset-password` | Reset sandi |
| GET/PUT | `/api/me` | Ambil dan ubah profil |
| PUT | `/api/me/password` | Ganti kata sandi |
| GET | `/api/plans?type=&period=` | Daftar paket (filter jenis dan periode) |
| POST | `/api/orders` | Buat pesanan |
| POST | `/api/orders/{id}/pay` | Mulai pembayaran |
| POST | `/api/payments/webhook` | Callback gateway (publik, terverifikasi signature) |
| GET | `/api/subscription` | Status langganan aktif dan sisa hari |
| GET | `/api/subscriptions/history` | Riwayat |
| POST | `/api/subscription/upgrade` | Upgrade paket |
| GET | `/api/epaper/editions` | Daftar edisi (filter tanggal) |
| GET | `/api/epaper/editions/{id}/pages/{n}` | URL halaman bertanda tangan |
| GET | `/api/epaper/editions/{id}/download` | Unduh PDF ber-watermark (signed URL atau status pemrosesan) |
| GET | `/api/epaper/downloads` | Riwayat unduhan dan sisa batas unduhan hari ini |
| GET/POST/DELETE | `/api/clippings` | Kelola clipping |
| GET | `/api/entitlements` | Hak akses dan kuota pengguna (untuk frontend dan situs berita) |

## 14. Rancangan Halaman (Sitemap Frontend)

| Area | Halaman |
|---|---|
| Publik | Beranda berita (dengan pencarian dan ikon kalender arsip), Pricing (`/langganan`), E-Paper (daftar edisi dan pratinjau terkunci), Daftar, Masuk, Lupa Sandi |
| Member Area | **Dashboard** · **Clipping** · **Berlangganan** · **Upgrade** · **Pengaturan** (Data Diri dan Keamanan Akun) |
| Pembayaran | Checkout, Instruksi Bayar, Status Pembayaran |
| E-Paper | Viewer edisi, tombol Unduh PDF, pemilih tanggal, "Edisi E-Paper Lainnya", riwayat unduhan |

**Elemen UI kunci (mengacu benchmark):** header dengan pencarian, ikon kalender, menu Free Ads/E-Paper, avatar pengguna; banner promosi; kartu paket dengan label "Rekomendasi"; progress ring sisa hari; kartu pintasan "Bebas Iklan" dan "Baca Koran".

## 15. Aturan Bisnis Penting

1. Pesanan berstatus `pending` otomatis `expired` setelah 24 jam tanpa pembayaran.
2. Webhook diproses **idempoten** (pembayaran yang sama tidak mengaktifkan dua kali).
3. Perpanjangan saat masih aktif: `end_date` baru = `end_date` lama + durasi paket.
4. Setelah kedaluwarsa: akses premium dicabut, data clipping **tetap tersimpan** (read-only) selama 90 hari sebagai pemicu perpanjangan.
5. Voucher tidak dapat digabung dengan voucher lain (kecuali ditetapkan sebaliknya).
6. Refund hanya melalui kebijakan CS (manual) pada MVP.
7. Sebuah edisi berhak diakses bila rentang tanggalnya (`publish_date` sampai `publish_end_date`) beririsan dengan masa aktif. Edisi gabungan (mis. Sabtu-Minggu) dihitung satu edisi.
8. Setiap unduhan menghasilkan PDF baru dengan watermark dan hash unik, lalu dicatat di log unduhan.
9. Batas unduhan (per edisi dan per hari) dapat diubah per paket dari backoffice.
10. Setelah langganan berakhir, akses baca dan unduh dihentikan. File yang sudah terunduh tidak dapat ditarik kembali.

## 16. Analitik dan Pelacakan

| Event | Tujuan |
|---|---|
| `view_pricing`, `select_plan`, `begin_checkout`, `payment_success/failed` | Funnel konversi |
| `login`, `epaper_open`, `clip_added`, `quota_reached` | Penggunaan fitur |
| `reminder_sent`, `renewal_success` | Retensi |
| `upgrade_click` dari kuota penuh/arsip terkunci | Efektivitas upsell |

Dashboard internal: pelanggan aktif, MRR, churn, konversi per paket, metode bayar terpopuler.

## 17. Rencana Rilis (Roadmap)

| Fase | Cakupan | Estimasi |
|---|---|---|
| **Fase 0: Discovery** | Finalisasi paket dan harga, pilih payment gateway, integrasi CMS dan iklan, desain UI | 2 minggu |
| **Fase 1: MVP Core** | Auth, katalog paket, checkout, webhook, aktivasi, dashboard, pengaturan | 5 minggu |
| **Fase 2: Fitur Konten** | Free Ads, E-Paper viewer dan arsip, Clipping, kuota | 4 minggu |
| **Fase 3: Retensi** | Notifikasi pengingat, upgrade, voucher, riwayat dan invoice, backoffice | 3 minggu |
| **Fase 4: Hardening dan UAT** | Uji beban, uji keamanan, UAT, beta tertutup | 2 minggu |
| **Peluncuran** | Soft launch bertahap, lalu rilis penuh | |
| **Pasca-rilis** | Auto-renewal, login Google, paket korporat | Backlog |

## 18. Risiko dan Mitigasi

| Risiko | Dampak | Mitigasi |
|---|---|---|
| Berbagi akun antar pengguna | Pendapatan bocor | Batas sesi, watermark E-Paper, deteksi login anomali |
| Webhook gagal/terlambat | Pelanggan sudah bayar tapi belum aktif | Retry, antrean, rekonsiliasi harian, tombol "Cek status pembayaran" |
| PDF E-Paper disebarkan ke pihak lain | Kerugian konten dan pendapatan | Watermark email + hash unik per unduhan di setiap halaman, signed URL pendek, batas unduhan, log unduhan, deteksi unduhan tidak wajar |
| Proses watermark lambat saat banyak orang mengunduh bersamaan (pagi hari saat edisi terbit) | Unduhan lambat atau gagal | Queue worker terpisah, cache hasil per pengguna-edisi, batasi konkurensi, autoscale worker |
| Harga tidak sesuai pasar | Konversi rendah | A/B test harga, voucher peluncuran |
| Integrasi iklan dengan CMS lama sulit | Fitur Free Ads terlambat | Validasi teknis sejak Fase 0, cadangan: sembunyikan iklan lewat flag di frontend |
| Lonjakan trafik | Situs lambat | CDN, cache, autoscaling, uji beban |
| Kepatuhan data pribadi | Risiko hukum | Kebijakan privasi, persetujuan eksplisit, enkripsi, kontrol akses |

## 19. Asumsi dan Ketergantungan

- Solopos menyediakan akses ke CMS, sistem iklan, dan file PDF master tiap edisi (contoh: 12 halaman, edisi gabungan Sabtu-Minggu) yang diunggah sebelum jam terbit.
- Payment gateway disetujui dan akun merchant aktif.
- Tim bisnis menetapkan harga, kuota, dan kebijakan refund.
- Domain dan infrastruktur hosting tersedia.
- Rancangan keamanan autentikasi dan sesi (cookie/token, domain) disediakan oleh Manajer IT Solopos pada tahap berikutnya.

## 20. Open Questions (Perlu Keputusan)

1. Berapa harga final paket Mingguan, Bulanan, dan Tahunan? Apakah perlu opsi periode lain (mis. 3 atau 6 bulan)?
2. Makna "layanan terbatas": apakah hanya kuota clipping dan arsip, atau ada batas jumlah artikel yang dapat dibaca per bulan?
3. Mekanisme upgrade: prorata atau kredit sisa hari?
4. Apakah perlu uji coba gratis (*trial*) beberapa hari?
5. Berapa batas unduhan yang wajar (per edisi dan per hari) agar tidak disalahgunakan?
6. Payment gateway mana yang dipilih, dan apakah perlu transfer manual dengan konfirmasi staf?
7. Apakah akun wajib verifikasi nomor WhatsApp (di benchmark, kolom ini opsional)?
8. Siapa pemilik operasional layanan pelanggan (CS) dan SLA-nya?
9. Mekanisme autentikasi dan sesi (penyimpanan token/cookie, struktur domain) akan ditetapkan oleh Manajer IT Solopos. Kapan keputusan ini dibutuhkan agar tidak menghambat Fase 1?
10. Setelah masa aktif habis, apakah pelanggan boleh mengunduh ulang edisi dari periodenya? Apakah paket memberi akses arsip mundur (edisi sebelum tanggal aktif)?
11. Apakah edisi gabungan Sabtu-Minggu dihitung satu edisi (asumsi PRD)? Apakah ada suplemen atau edisi khusus lain yang perlu masuk?
12. Pencantuman email pelanggan di watermark perlu dicantumkan pada Syarat dan Ketentuan serta Kebijakan Privasi (UU PDP). Siapa yang menyiapkan teksnya?

## 21. Lampiran: Temuan Benchmark (Harian Jogja)

| Aspek | Temuan |
|---|---|
| Navigasi member | Dashboard, Clipping, Berlangganan, Upgrade, Pengaturan |
| Dashboard | Sapaan dengan badge VIP, progress ring sisa hari, tanggal aktif sampai, pintasan "Bebas Iklan" dan "Baca Koran" |
| Paket | 3 tab: Free Ads & E-Paper, Free Ads, E-Paper |
| Durasi dan harga (tab Free Ads & E-Paper) | 1 bulan Rp 15.000 (Rekomendasi), 3 bulan Rp 35.000, 6 bulan Rp 62.500 |
| Kuota | Clipping maks. 10/30/60 artikel; bebas baca 30 arsip E-Paper |
| E-Paper | Halaman depan edisi hari ini, daftar "Edisi E-Paper Lainnya", pemilih tanggal |
| Pengaturan | Foto profil, nama lengkap, nomor WhatsApp (+62), email (hanya-baca), kata sandi baru dan konfirmasi |
| Pola konversi | Banner ajakan daftar untuk pengunjung (tombol Daftar dan Masuk di header) |

**Perbaikan yang diusulkan dibanding benchmark:**
- Kolom WhatsApp diberi validasi format dan opsi verifikasi OTP.
- Dashboard menampilkan sisa kuota clipping dan pengingat kedaluwarsa secara eksplisit.
- Menambahkan riwayat transaksi/invoice (tidak terlihat pada benchmark).
- Mencantumkan penjelasan manfaat paket secara langsung, tidak hanya terpotong ("Selengkapnya").
- Terminologi seragam ("Clipping" dan "Cliping" pada benchmark tidak konsisten).

## 22. Lampiran: Spesifikasi Unduhan PDF Edisi (berdasarkan contoh file)

Acuan: contoh file `19092026-Koran-Solopos_watermarked.pdf`.

| Aspek | Temuan pada contoh | Kebutuhan pada sistem |
|---|---|---|
| Nama file | `ddmmyyyy-Koran-Solopos_watermarked.pdf` (19092026 = 19 September 2026) | Pola nama file sama, dibentuk otomatis dari tanggal terbit edisi |
| Isi | 12 halaman, edisi gabungan "Sabtu Wage-Minggu Kliwon, 19-20 September 2026" | Mendukung edisi gabungan melalui `publish_date` dan `publish_end_date` |
| Watermark | Pada setiap halaman terdapat alamat email pelanggan (huruf kapital) dan kode hash 32 karakter heksadesimal, sama di semua halaman satu file | Email + hash dibuat dinamis tiap unduhan dan disimpan di `epaper_downloads` |
| Posisi watermark | Di bawah area header halaman. Pada halaman beriklan penuh (mis. halaman 7) teks watermark menimpa konten | Posisikan di margin atau footer dengan opasitas rendah agar tidak menutupi teks dan iklan |

**Alur teknis yang diusulkan**
1. Redaksi atau percetakan mengunggah PDF master (bersih) ke penyimpanan privat melalui backoffice.
2. Sistem memvalidasi file, menghitung halaman, membuat cover dan thumbnail, lalu menerbitkan edisi.
3. Saat pelanggan menekan "Unduh PDF", backend memeriksa hak akses (masa aktif, paket, batas unduhan).
4. Job queue membuat salinan ber-watermark: email pelanggan dan hash unik (mis. HMAC dari `user_id`, `edition_id`, dan waktu, dipotong 32 karakter heksadesimal).
5. Backend mengembalikan signed URL berumur 5 menit. File sementara dihapus berkala.
6. Unduhan dicatat sehingga file yang bocor dapat dilacak lewat hash ke akun dan waktu unduhan.

**Catatan tambahan**
- Tanamkan watermark pada isi halaman (bukan sekadar anotasi) agar sulit dihapus.
- Isi juga metadata PDF (mis. subjek berisi hash) sebagai penanda tambahan.
- Beri tahu pelanggan pada halaman unduhan bahwa email mereka dicantumkan pada file.

## 23. Riwayat Revisi
| Versi | Perubahan |
|---|---|
| 1.0 | Draft awal berdasarkan benchmark member area Harian Jogja |
| 1.1 | Paket diubah menjadi periode Mingguan, Bulanan, Tahunan. Menambah unduh PDF edisi dengan watermark dinamis, batas unduhan, tabel `epaper_downloads`, endpoint unduhan, dan Lampiran 22 |
| 1.2 | Menambah struktur file backend (11.4) dan frontend (11.5) |

---
*Dokumen ini adalah draf awal dan akan diperbarui setelah review dengan stakeholder dan keputusan atas Open Questions.*