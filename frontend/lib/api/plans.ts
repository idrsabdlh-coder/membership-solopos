import { cacheLife } from "next/cache";
import { apiFetch } from "./client";
import type { Plan } from "@/types/plan";

export async function getPlans(): Promise<Plan[]> {
  "use cache";
  cacheLife("minutes");

  const json = await apiFetch<{ data: Plan[] }>("/plans");
  return json.data;
}