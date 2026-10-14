// src/types/plan.ts
export type PlanType = "free_ads_epaper" | "free_ads" | "epaper";
export type BillingPeriod = "weekly" | "monthly" | "yearly";

export interface Plan {
  id: number;
  type: PlanType;
  type_label: string;
  name: string;
  billing_period: BillingPeriod;
  period_label: string;
  duration_days: number;
  price: number;
  clipping_quota: number;
  download_limit_per_edition: number;
  download_limit_per_day: number;
  includes_free_ads: boolean;
  includes_epaper: boolean;
  is_recommended: boolean;
}