// src/components/plans/PricingTabs.tsx
"use client";

import { useMemo, useState } from "react";
import type { Plan, PlanType } from "@/types/plan";
import PlanCard from "./PlanCard";

const TABS: { type: PlanType; label: string }[] = [
  { type: "free_ads_epaper", label: "Free Ads & E-Paper" },
  { type: "free_ads", label: "Free Ads" },
  { type: "epaper", label: "E-Paper" },
];

export default function PricingTabs({ plans }: { plans: Plan[] }) {
  const [active, setActive] = useState<PlanType>("free_ads_epaper");

  const visible = useMemo(
    () =>
      plans
        .filter((p) => p.type === active)
        .sort((a, b) => a.duration_days - b.duration_days),
    [plans, active],
  );

  return (
    <div>
      <div
        role="tablist"
        className="mx-auto flex w-fit max-w-full overflow-x-auto rounded border border-hairline bg-card p-1"
      >
        {TABS.map((tab) => (
          <button
            key={tab.type}
            role="tab"
            aria-selected={active === tab.type}
            onClick={() => setActive(tab.type)}
            className={`whitespace-nowrap rounded px-4 py-2 text-sm font-semibold transition-colors ${
              active === tab.type ? "bg-navy text-white" : "text-navy hover:bg-canvas"
            }`}
          >
            {tab.label}
          </button>
        ))}
      </div>

      <div className="mt-8 grid grid-cols-1 gap-6 md:grid-cols-3">
        {visible.map((plan) => (
          <PlanCard key={plan.id} plan={plan} />
        ))}
      </div>
    </div>
  );
}