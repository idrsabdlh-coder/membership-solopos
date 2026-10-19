import { apiFetch } from "./client";
import type { Plan } from "@/types/plan";

export async function fetchPlans(): Promise<Plan[]> {
  const json = await apiFetch<{ data: Plan[] }>("/plans");
  return json.data;
}
