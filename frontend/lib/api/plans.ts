import { cacheLife } from "next/cache";
import type { Plan } from "@/types/plan";
import { fetchPlans } from "./plans-client";

export async function getPlans(): Promise<Plan[]> {
  "use cache";
  cacheLife("minutes");

  return fetchPlans();
}