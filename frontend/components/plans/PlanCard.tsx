// src/components/plans/PlanCard.tsx
import type { BillingPeriod, Plan } from "@/types/plan";
import { formatRupiah } from "@/lib/utils/format";

const PERIOD_SUFFIX: Record<BillingPeriod, string> = {
  weekly: "minggu",
  monthly: "bulan",
  yearly: "tahun",
};

function Feature({ ok, children }: { ok: boolean; children: React.ReactNode }) {
  return (
    <li className={`flex gap-2 text-sm ${ok ? "text-ink" : "text-muted line-through"}`}>
      <span aria-hidden className={ok ? "text-success" : "text-muted"}>
        {ok ? "✓" : "✕"}
      </span>
      <span>{children}</span>
    </li>
  );
}

export default function PlanCard({ plan }: { plan: Plan }) {
  const canDownload = plan.download_limit_per_edition > 0;

  return (
    <article
      className={`relative flex flex-col overflow-hidden rounded-lg border bg-card ${
        plan.is_recommended ? "border-vip-gold" : "border-hairline"
      }`}
    >
      {plan.is_recommended && (
        <div className="bg-vip-gold px-4 py-1.5 text-center text-xs font-semibold uppercase tracking-wider text-white">
          Paling Diminati &amp; Rekomendasi
        </div>
      )}

      <header className={`px-6 pt-6 pb-4 ${plan.is_recommended ? "bg-[#FFFBEB]" : ""}`}>
        <h3 className="font-serif text-xl font-semibold text-navy">{plan.period_label}</h3>
        <p className="mt-3 flex items-baseline gap-1">
          <span className="font-serif text-4xl font-semibold text-ink">
            {formatRupiah(plan.price)}
          </span>
          <span className="text-sm text-muted">/ {PERIOD_SUFFIX[plan.billing_period]}</span>
        </p>
        <p className="mt-1 text-sm text-muted">Masa aktif {plan.duration_days} hari</p>
      </header>

      <ul className="flex-1 space-y-3 px-6 py-5">
        <Feature ok={plan.includes_free_ads}>Bebas iklan di portal berita</Feature>
        <Feature ok={plan.includes_epaper}>Baca E-Paper semua edisi selama masa aktif</Feature>
        <Feature ok={canDownload}>
          {canDownload
            ? `Unduh PDF ${plan.download_limit_per_edition}x per edisi, maks. ${plan.download_limit_per_day}x per hari`
            : "Unduh PDF E-Paper"}
        </Feature>
        <Feature ok={plan.clipping_quota > 0}>
          {plan.clipping_quota > 0
            ? `Clipping hingga ${plan.clipping_quota} artikel`
            : "Clipping artikel"}
        </Feature>
      </ul>

      <footer className="px-6 pb-6">
        <button
          type="button"
          disabled
          title="Proses checkout sedang disiapkan."
          className="block w-full cursor-not-allowed rounded bg-slate-200 px-4 py-3 text-center text-sm font-semibold text-slate-600"
        >
          Checkout segera tersedia
        </button>
      </footer>
    </article>
  );
}