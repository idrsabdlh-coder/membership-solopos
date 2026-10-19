import type { Metadata } from "next";
import Link from "next/link";
import { connection } from "next/server";
import { Suspense } from "react";
import PricingTabs from "@/components/plans/PricingTabs";
import { getPlans } from "@/lib/api/plans";
import type { Plan } from "@/types/plan";

export const metadata: Metadata = {
  title: "Langganan | Solopos Membership",
  description: "Pilih paket langganan Solopos: bebas iklan dan E-Paper.",
};

async function PlansSection() {
  await connection();
  let plans: Plan[] = [];

  try {
    plans = await getPlans();
  } catch {
    return (
      <PricingTabs
        plans={[]}
        initialError="Periksa koneksi lalu coba muat ulang daftar paket."
      />
    );
  }

  return <PricingTabs plans={plans} />;
}

export default function LanggananPage() {
  return (
    <main className="mx-auto max-w-7xl px-4 py-12 md:px-8">
      <nav aria-label="Navigasi akun" className="mb-8 flex justify-end gap-4">
        <Link href="/masuk" className="text-sm font-semibold text-navy hover:underline">
          Masuk
        </Link>
        <Link href="/daftar" className="text-sm font-semibold text-navy hover:underline">
          Buat akun
        </Link>
        <Link href="/pengaturan" className="text-sm font-semibold text-navy hover:underline">
          Akun
        </Link>
      </nav>
      <header className="mx-auto mb-10 max-w-2xl text-center">
        <h1 className="font-serif text-4xl font-semibold text-navy">Pilih Paket Langganan</h1>
        <p className="mt-3 text-muted">
          Baca berita tanpa iklan dan koran digital Solopos kapan saja. Semua batas akses
          dijelaskan terbuka pada setiap paket.
        </p>
      </header>

      <Suspense fallback={<p className="text-center text-muted">Memuat paket...</p>}>
        <PlansSection />
      </Suspense>
    </main>
  );
}   