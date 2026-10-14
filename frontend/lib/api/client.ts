// src/lib/api/client.ts
const BASE_URL = process.env.NEXT_PUBLIC_API_URL ?? "http://127.0.0.1:8000/api";

export async function apiFetch<T>(path: string, init?: RequestInit): Promise<T> {
  // Titik sambung auth: header Authorization akan ditambahkan di sini
  // setelah keputusan Manajer IT (lihat lib/auth/).
  const res = await fetch(`${BASE_URL}${path}`, {
    ...init,
    headers: { Accept: "application/json", ...init?.headers },
  });

  if (!res.ok) {
    throw new Error(`API ${res.status}: ${path}`);
  }
  return res.json() as Promise<T>;
}