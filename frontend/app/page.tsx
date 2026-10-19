import type { Metadata } from "next";
import Link from "next/link";

export const metadata: Metadata = {
  title: "Solopos Membership",
  description: "Nikmati berita Solopos tanpa iklan dan akses E-Paper.",
};

export default function Home() {
  return (
    <main className="flex min-h-screen flex-col">
      <header className="mx-auto flex w-full max-w-7xl items-center justify-between px-4 py-6 md:px-8">
        <Link href="/" className="font-serif text-xl font-semibold tracking-wide text-navy">
          SOLOPOS
          <span className="ml-2 font-sans text-xs font-medium tracking-normal text-muted">
            MEMBERSHIP
          </span>
        </Link>
        <nav aria-label="Navigasi akun" className="flex items-center gap-4">
          <Link href="/masuk" className="text-sm font-semibold text-navy hover:underline">
            Masuk
          </Link>
          <Link
            href="/pengaturan"
            className="hidden text-sm font-semibold text-navy hover:underline sm:inline"
          >
            Akun
          </Link>
        </nav>
      </header>

      <section className="mx-auto flex w-full max-w-5xl flex-1 flex-col items-center justify-center px-4 py-16 text-center md:px-8">
        <p className="text-xs font-semibold uppercase tracking-[0.18em] text-vip-gold">
          SOLOPOS MEMBERSHIP
        </p>
        <h1 className="mt-4 max-w-3xl font-serif text-4xl font-semibold leading-tight text-navy md:text-6xl">
          Berita tepercaya, pengalaman membaca yang lebih nyaman.
        </h1>
        <p className="mt-6 max-w-2xl text-base leading-7 text-muted md:text-lg">
          Pilih akses bebas iklan, E-Paper, atau keduanya dalam satu keanggotaan.
        </p>
        <Link
          href="/langganan"
          className="mt-8 rounded bg-navy px-6 py-3 text-sm font-semibold text-white transition-colors hover:bg-navy-hover focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-navy"
        >
          Lihat pilihan paket
        </Link>
      </section>
    </main>
  );
}
