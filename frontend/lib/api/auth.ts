import { apiFetch, apiJson } from "./client";

export interface User {
  id: number;
  name: string;
  email: string;
  email_verified_at: string | null;
  whatsapp: string | null;
  avatar_url: string | null;
}

interface Resource<T> {
  data: T;
  meta?: Record<string, unknown>;
}

export interface RegistrationInput {
  name: string;
  email: string;
  password: string;
  password_confirmation: string;
  whatsapp?: string;
}

export interface DeviceSession {
  id: string;
  device: string;
  ip_address: string | null;
  last_activity: string;
  is_current: boolean;
}

interface DeviceSessionsResource {
  data: DeviceSession[];
  meta: { max_active_sessions: number };
}

export async function register(input: RegistrationInput): Promise<Resource<User>> {
  return apiJson("/auth/register", "POST", input);
}

export async function login(
  email: string,
  password: string,
): Promise<{ user: User; previousSessionRevoked: boolean }> {
  const result = await apiJson<Resource<User> & {
    meta?: { previous_session_revoked?: boolean };
  }>("/auth/login", "POST", { email, password });
  return {
    user: result.data,
    previousSessionRevoked: result.meta?.previous_session_revoked ?? false,
  };
}

export function logout(): Promise<void> {
  return apiJson("/auth/logout", "POST");
}

export async function resendVerification(email: string): Promise<{ message: string }> {
  return apiJson("/auth/email/verification-notification", "POST", { email });
}

export async function requestPasswordReset(email: string): Promise<{ message: string }> {
  return apiJson("/auth/forgot-password", "POST", { email });
}

export async function resetPassword(input: {
  token: string;
  email: string;
  password: string;
  password_confirmation: string;
}): Promise<{ message: string }> {
  return apiJson("/auth/reset-password", "POST", input);
}

export async function getProfile(): Promise<User> {
  const result = await apiFetch<Resource<User>>("/auth/profile");
  return result.data;
}

export async function getDeviceSessions(): Promise<DeviceSessionsResource> {
  return apiFetch("/auth/sessions");
}

export function revokeDeviceSession(id: string): Promise<void> {
  return apiFetch(`/auth/sessions/${encodeURIComponent(id)}`, { method: "DELETE" });
}

export async function updateProfile(input: {
  name: string;
  whatsapp: string;
  avatar?: File;
}): Promise<User> {
  let result: Resource<User>;

  if (input.avatar) {
    const form = new FormData();
    form.set("_method", "PATCH");
    form.set("name", input.name);
    form.set("whatsapp", input.whatsapp);
    form.set("avatar", input.avatar);
    result = await apiFetch<Resource<User>>("/auth/profile", {
      method: "POST",
      body: form,
    });
  } else {
    result = await apiJson<Resource<User>>("/auth/profile", "PATCH", {
      name: input.name,
      whatsapp: input.whatsapp || null,
    });
  }

  return result.data;
}

export function changePassword(input: {
  current_password: string;
  password: string;
  password_confirmation: string;
}): Promise<{ message: string }> {
  return apiJson("/auth/password", "PATCH", input);
}
