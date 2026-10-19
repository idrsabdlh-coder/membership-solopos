// src/components/plans/PricingTabs.tsx
"use client";

import { useMemo, useState } from "react";
import { ApiError } from "@/lib/api/client";
import { fetchPlans } from "@/lib/api/plans-client";
import type { Plan, PlanType } from "@/types/plan";
import PlanCard from "./PlanCard";

const TABS: { type: PlanType; label: string }[] = [
  { type: "free_ads_epaper", label: "Free Ads & E-Paper" },
  { type: "free_ads", label: "Free Ads" },
  { type: "epaper", label: "E-Paper" },
];

export default function PricingTabs({
  plans,
  initialError,
}: {
  plans: Plan[];
  initialError?: string;
}) {
  const [active, setActive] = useState<PlanType>("free_ads_epaper");
  const [currentPlans, setCurrentPlans] = useState(plans);
  const [error, setError] = useState(initialError);
  const [loading, setLoading] = useState(false);

  const visible = useMemo(
    () =>
      currentPlans
        .filter((p) => p.type === active)
        .sort((a, b) => a.duration_days - b.duration_days),
    [currentPlans, active],
  );

  async function retryLoadingPlans(): Promise<void> {
    setLoading(true);
    setError(undefined);

    try {
      setCurrentPlans(await fetchPlans());
    } catch (cause) {
      setError(
        cause instanceof ApiError
          ? cause.message
          : "Daftar paket belum dapat dimuat. Silakan coba lagi.",
      );
    } finally {
      setLoading(false);
    }
  }

  return (
    <div>
      <div
        role="tablist"
        aria-label="Jenis paket langganan"
        className="mx-auto flex w-fit max-w-full overflow-x-auto rounded border border-hairline bg-card p-1"
      >
        {TABS.map((tab) => (
          <button
            key={tab.type}
            role="tab"
            id={`plan-tab-${tab.type}`}
            aria-controls="plan-tab-panel"
            aria-selected={active === tab.type}
            tabIndex={active === tab.type ? 0 : -1}
            onClick={() => setActive(tab.type)}
            className={`whitespace-nowrap rounded px-4 py-2 text-sm font-semibold transition-colors ${
              active === tab.type ? "bg-navy text-white" : "text-navy hover:bg-canvas"
            }`}
          >
            {tab.label}
          </button>
        ))}
      </div>

      <div
        id="plan-tab-panel"
        role="tabpanel"
        aria-labelledby={`plan-tab-${active}`}
        className="mt-8"
      >
        {error ? (
          <div
            className="rounded border border-red-200 bg-red-50 p-6 text-center"
            role="alert"
          >
            <p className="font-semibold text-red-800">Paket belum dapat dimuat</p>
            <p className="mt-2 text-sm text-red-700">{error}</p>
            <button
              type="button"
              onClick={retryLoadingPlans}
              disabled={loading}
              className="mt-4 rounded bg-navy px-4 py-2 text-sm font-semibold text-white hover:bg-navy-hover disabled:cursor-wait disabled:opacity-60"
            >
              {loading ? "Memuat ulang..." : "Coba lagi"}
            </button>
          </div>
        ) : loading ? (
          <p className="py-8 text-center text-muted" role="status" aria-live="polite">
            Memuat paket...
          </p>
        ) : visible.length === 0 ? (
          <div className="rounded border border-hairline bg-card p-8 text-center">
            <p className="font-semibold text-navy">Paket belum tersedia</p>
            <p className="mt-2 text-sm text-muted">
              Belum ada paket aktif untuk jenis ini. Silakan pilih jenis paket lain atau coba lagi.
            </p>
            <button
              type="button"
              onClick={retryLoadingPlans}
              disabled={loading}
              className="mt-4 rounded border border-navy px-4 py-2 text-sm font-semibold text-navy hover:bg-canvas disabled:opacity-60"
            >
              {loading ? "Memuat ulang..." : "Muat ulang"}
            </button>
          </div>
        ) : (
          <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
            {visible.map((plan) => (
              <PlanCard key={plan.id} plan={plan} />
            ))}
          </div>
        )}
      </div>
    </div>
  );
}