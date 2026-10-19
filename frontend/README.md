# Frontend Solopos Membership

Frontend Next.js untuk katalog paket dan akun Solopos Membership.

## Menjalankan lokal

Pastikan backend Laravel berjalan di `http://127.0.0.1:8000` dengan konfigurasi lokal
Sanctum/CORS dari [panduan root](../README.md). Dari folder `frontend`:

```powershell
npm ci
npm run dev
```

Buka `http://localhost:3000`. URL API default adalah
`http://127.0.0.1:8000/api`; untuk menggantinya, set `NEXT_PUBLIC_API_URL` sebelum
menjalankan Next.js.

## Halaman yang tersedia

- `/langganan` — katalog paket dari API beserta loading, error, retry, dan empty state.
- `/daftar`, `/masuk`, `/lupa-sandi`, `/reset-password` — alur akun dan pemulihan sandi.
- `/pengaturan` — profil, foto, verifikasi email, ganti sandi, dan logout; memerlukan sesi.

Checkout belum tersedia; tombol pembelian pada kartu paket dinonaktifkan sampai alur checkout
dan pembayaran selesai dibuat.

## Validasi

```powershell
npm run lint
npm run build
```
