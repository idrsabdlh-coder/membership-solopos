const API_BASE_URL = (
  process.env.NEXT_PUBLIC_API_URL ?? "http://127.0.0.1:8000/api"
).replace(/\/+$/, "");

type ApiErrorPayload = {
  error?: {
    code?: string;
    message?: string;
    fields?: Record<string, string[]>;
  };
  message?: string;
  errors?: Record<string, string[]>;
};

export class ApiError extends Error {
  constructor(
    message: string,
    readonly status: number,
    readonly code?: string,
    readonly fields: Record<string, string[]> = {},
  ) {
    super(message);
    this.name = "ApiError";
  }
}

function apiOrigin(): string {
  return new URL(API_BASE_URL).origin;
}

function csrfToken(): string | undefined {
  const token = document.cookie
    .split("; ")
    .find((cookie) => cookie.startsWith("XSRF-TOKEN="))
    ?.slice("XSRF-TOKEN=".length);

  return token ? decodeURIComponent(token) : undefined;
}

async function bootstrapCsrf(): Promise<void> {
  const response = await fetch(`${apiOrigin()}/sanctum/csrf-cookie`, {
    credentials: "include",
    headers: { Accept: "application/json" },
  });

  if (!response.ok) {
    throw new ApiError("Tidak dapat memulai sesi aman. Silakan coba lagi.", response.status);
  }
}

function isUnsafeMethod(method: string): boolean {
  return !["GET", "HEAD", "OPTIONS"].includes(method.toUpperCase());
}

async function readError(response: Response): Promise<ApiError> {
  let payload: ApiErrorPayload = {};

  try {
    payload = (await response.json()) as ApiErrorPayload;
  } catch {
    // The server may return an empty or non-JSON error response.
  }

  const fields = payload.error?.fields ?? payload.errors ?? {};
  const message =
    payload.error?.message ??
    payload.message ??
    (response.status >= 500
      ? "Terjadi gangguan pada layanan. Silakan coba lagi."
      : "Permintaan tidak dapat diproses.");

  return new ApiError(message, response.status, payload.error?.code, fields);
}

export async function apiFetch<T>(path: string, init: RequestInit = {}): Promise<T> {
  const method = (init.method ?? "GET").toUpperCase();
  const headers = new Headers(init.headers);
  headers.set("Accept", "application/json");

  if (typeof FormData === "undefined" || !(init.body instanceof FormData)) {
    if (init.body !== undefined && !headers.has("Content-Type")) {
      headers.set("Content-Type", "application/json");
    }
  }

  if (typeof window !== "undefined" && isUnsafeMethod(method)) {
    await bootstrapCsrf();
    const token = csrfToken();
    if (token) {
      headers.set("X-XSRF-TOKEN", token);
    }
  }

  let response: Response;
  try {
    response = await fetch(`${API_BASE_URL}${path}`, {
      ...init,
      method,
      headers,
      credentials: "include",
    });
  } catch {
    throw new ApiError("Tidak dapat terhubung ke layanan. Periksa koneksi dan coba lagi.", 0);
  }

  if (!response.ok) {
    throw await readError(response);
  }

  if (response.status === 204) {
    return undefined as T;
  }

  return (await response.json()) as T;
}

export function apiJson<T>(
  path: string,
  method: "POST" | "PATCH" | "PUT" | "DELETE",
  body?: unknown,
): Promise<T> {
  return apiFetch<T>(path, {
    method,
    ...(body === undefined ? {} : { body: JSON.stringify(body) }),
  });
}
