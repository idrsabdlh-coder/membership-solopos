# DESIGN.md — Solopos Membership Editorial Design System

Dokumen ini merupakan panduan spesifikasi desain antarmuka, arsitektur visual, sistem token (warna, tipografi, grid, elevasi), serta pedoman komponen untuk ekosistem **Solopos Membership** (Next.js & Laravel API), mengacu pada implementasi layar high-fidelity yang telah dirancang.

---

## 1. Brand Identity & Prinsip Desain

Solopos adalah institusi pers terpercaya di wilayah Solo Raya & Jawa Tengah yang berdiri sejak 1997 dan terverifikasi Dewan Pers. Identitas antarmuka membership digital memadukan wibawa jurnalisme cetak klasik (*editorial gravitas*) dengan kepraktisan aplikasi web modern (*clean utility*).

### Prinsip Utama:
1. **Editorial Credibility & Trust**: Penggunaan palet warna *Deep Navy* dipadu latar belakang *warm paper/off-white* merefleksikan kejujuran, wibawa jurnalistik, dan standar Dewan Pers.
2. **Clear Information Architecture**: Tata letak terstruktur dengan garis pemisah tipis (*hairline borders*), hierarki tipografi tegas, dan minim bayangan berlebihan (*flat/hairline-elevation*).
3. **Transparency by Design**: Setiap aturan pembatasan akses, batas kuota clipping, batas unduh PDF harian, serta simulasi prorata disajikan dengan jelas, jujur, dan mudah dipahami tanpa klausa tersembunyi.
4. **Content-First & Distraction-Free**: Konten koran digital dan artikel investigasi menjadi fokus utama, bebas dari gangguan iklan untuk member premium.

---

## 2. Design Tokens: Warna (Color Palette)

Palet warna dibangun di atas fondasi warna primer *Solopos Navy* (`#0a3a60`) dengan nuansa pendukung untuk status, latar belakang, dan kontras baca.

### 2.1 Primary & Neutrals
- **Primary / Brand Deep Navy**: `#0a3a60` (Warna utama header, tombol aksi primer, judul besar, dan aksen institusional)
- **Primary Dark / Midnight Navy**: `#06243d` (Footer, banner gelap penawaran korporat)
- **Primary Light / Navy Tint**: `#e8f0fe` (Aksen seleksi aktif, highlight latar belakang lencana)
- **Surface Canvas (Background)**: `#f8fafc` / `#faf8ff` (Latar belakang utama aplikasi)
- **Surface Card / Container Lowest**: `#ffffff` (Kartu konten, panel form, viewer container)
- **Surface Container Low / Subtle**: `#f1f5f9` (Kolom input disabled, baris header tabel)
- **Surface Border / Hairline**: `#e2e8f0` (Border pembatas kartu, separator tabel, hairline stroke)

### 2.2 Functional & Status Colors
- **Emerald Green (Success / Verified / Entitlement OK)**:
  - Text/Icon: `#059669` (Tailwind `emerald-600`)
  - Background Tint: `#ecfdf5` (Tailwind `emerald-50`)
  - Border: `#a7f3d0` (Tailwind `emerald-200`)
- **Amber / Gold (VIP Member & Promo Highlight)**:
  - Text: `#b45309` (Tailwind `amber-700`)
  - Background: `#fef3c7` (Tailwind `amber-100`)
  - Accent Badge: `#d97706` (Tailwind `amber-600`)
- **Crimson Red (Danger / Sesi Berakhir / Putus Perangkat)**:
  - Text/Button: `#dc2626` (Tailwind `red-600`)
  - Background Tint: `#fef2f2` (Tailwind `red-50`)
  - Border: `#fecaca` (Tailwind `red-200`)
- **Informative Blue / Slate (Meta & Subtitle)**:
  - Text Primary: `#0f172a` (Tailwind `slate-900`)
  - Text Secondary: `#475569` (Tailwind `slate-600`)
  - Text Muted: `#94a3b8` (Tailwind `slate-400`)

---

## 3. Tipografi (Typography)

Sistem tipografi menggunakan perpaduan antara **Newsreader** (serif editorial) untuk tajuk utama koran dan nuansa prestise jurnalisme, serta **Inter / Plus Jakarta Sans** (sans-serif modern) untuk UI, dashboard, tabel data, dan form.

### 3.1 Typeface Families
- **Display & Editorial Serif**: `Newsreader`, `Lora`, `Merriweather`, serif.
- **UI & Body Sans-Serif**: `Inter`, `system-ui`, `-apple-system`, sans-serif.
- **Monospace (Hash & Kode Transaksi)**: `JetBrains Mono`, `ui-monospace`, monospace.

### 3.2 Type Scale & Utilities
| Role | Ukuran (Font Size) | Line Height | Weight | Kegunaan |
|---|---|---|---|---|
| **Display Editorial** | 2rem – 2.5rem (32–40px) | 1.2 | Bold (700) | Judul besar landing page, headline edisi koran hari ini |
| **Headline Large** | 1.5rem – 1.875rem (24–30px) | 1.25 | Bold (700) | Judul halaman member (Dashboard, Upgrade, Riwayat) |
| **Headline Medium** | 1.25rem (20px) | 1.3 | SemiBold (600) | Sub-heading kartu, nama paket langganan |
| **Headline Small** | 1.125rem (18px) | 1.4 | SemiBold (600) | Judul seksi, nama transaksi |
| **Body Large** | 1rem (16px) | 1.5 | Regular / Medium | Teks deskripsi utama, narasi artikel |
| **Body Medium / Base** | 0.875rem (14px) | 1.5 | Regular (400) | Default teks UI, rincian billing, form input |
| **Label / Meta Small** | 0.75rem (12px) | 1.4 | Medium (500) | Status pill, metadata tanggal WIB, keterangan watermark |
| **Micro Monospace** | 0.7rem (11px) | 1.3 | Regular (400) | Hash 32-karakter unik watermark, ID transaksi |

---

## 4. Spacing, Grid, dan Layout

- **Base Spacing Unit**: 4px (`0.25rem`).
- **Container Max-Width**: `max-w-7xl` (1280px) untuk portal utama & header, berpusat dengan padding horisontal `px-4 md:px-8`.
- **Member Area Layout**: Split 2 kolom:
  - **Sidebar Navigasi**: `w-64` (256px) *fixed/sticky desktop*.
  - **Main Content Area**: `flex-1 min-w-0` dengan padding `p-6` hingga `p-8`.
- **Grid Patterns**:
  - 3 Kolom untuk Kartu Paket (`grid-cols-1 md:grid-cols-3 gap-6`).
  - 2 Kolom untuk Dashboard Widgets & E-Paper Archive Grid (`grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6`).
- **Border Radius**:
  - `rounded-md` (6px) untuk tombol kecil, badge, input fields.
  - `rounded-lg` (8px) – `rounded-xl` (12px) untuk kartu, kontainer widget, dan modal dialog.
  - `rounded-full` untuk avatar, status dot, dan pill badges.

---

## 5. Komponen Utama (Core UI Components)

### 5.1 Top Navigation Bar (Editorial Header)
- Logo Solopos huruf kapital berwibawa di sebelah kiri dengan tagline *"Jaringan Informasi Sahaja Solo Raya"*.
- Menu utama: *Berita*, *E-Paper*, *Bebas Iklan*, *Bantuan*.
- Menu kanan: Profil Pengguna dengan Avatar, status *"VIP Member"*, pill verifikasi email hijau, dan dropdown akun.

### 5.2 Member Sidebar
- Tombol navigasi berikon:
  - Dashboard
  - E-Paper & Koran Hari Ini (dengan badge status edisi)
  - Bebas Iklan (Portal)
  - Arsip Clipping Saya (dengan counter kuota, misal `7/20`)
  - Riwayat & Invoice
  - Upgrade Paket (dengan label promo diskon)
  - Pengaturan Akun & Keamanan
- Box bawah: status limit sesi perangkat (`1/5 Sesi Aktif`) dan shortcut email Sirkulasi.

### 5.3 Progress Ring & Indikator Kuota
- **Progress Ring Sisa Hari**: Visualisasi lingkaran berputar SVG menampilkan sisa masa aktif (misal `18 Hari Lagi dari 30 Hari`).
- **Quota Bars**: Bar horizontal dengan persentase pemakaian kuota clipping dan kuota unduh PDF harian (misal `8/10 PDF Tersedia Hari Ini`).

### 5.4 E-Paper Viewer & Dynamic Watermark System
- Kontainer viewer koran digital dengan toolbar navigasi halaman, tombol zoom, dan tombol unduh PDF resmi.
- **Watermark Anti-Pembajakan**: Dicantumkan di setiap halaman secara dinamis:
  - Teks: `DILISENSIKAN KEPADA: [EMAIL_PELANGGAN] | HASH: [32_HEX_STRING] | STANDAR DEWAN PERS & UU PDP`.
  - Opasitas rendah (`opacity-25` hingga `opacity-35`) agar tidak menghalangi keterbacaan berita.

### 5.5 Kartu Paket & Pricing Matrix
- Tab selektor paket (*Free Ads & E-Paper*, *Free Ads*, *E-Paper*).
- Kartu paket dengan badge penanda *"Paling Diminati & Rekomendasi"* pada opsi Bulanan.
- Komparasi fitur: tabel matriks dengan tanda checklist hijau dan cross abu-abu yang transparan.

### 5.6 Tabel Transaksi & Form Faktur Pajak
- Header tabel netral (`bg-slate-50`), border hairline.
- Aksi unduh berkas invoice PDF resmi.
- Opsi penerbitan e-Faktur Pajak pertambahan nilai (PPN 11%) bagi instansi dan korporat.

---

## 6. Tone of Voice & Copywriting

- **Bahasa**: Bahasa Indonesia formal, bersahabat, terpercaya (*Khas Surakarta / Solo Raya yang sopan dan profesional*).
- Contoh Sapaan: *"Sugeng Rawuh, Bambang Wijanarko"*.
- Pesan Keamanan & Regulasi: Mengacu secara konsisten pada kepatuhan UU Perlindungan Data Pribadi (UU PDP No. 27/2022) dan standar jurnalisme Dewan Pers.

---
*Dokumen ini dirancang sebagai acuan desain resmi (Design System Guideline) untuk konsistensi seluruh modul Solopos Membership.*
