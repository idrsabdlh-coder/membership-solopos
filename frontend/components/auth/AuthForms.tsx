"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState, type FormEvent } from "react";
import { ApiError } from "@/lib/api/client";
import {
  login as loginUser,
  register as registerUser,
  requestPasswordReset,
  resendVerification,
  resetPassword,
} from "@/lib/api/auth";
import FormField from "./FormField";

function fieldError(error: ApiError | null, field: string): string | undefined {
  return error?.fields[field]?.[0];
}

function FormMessage({
  message,
  kind = "error",
}: {
  message: string;
  kind?: "error" | "success";
}) {
  const isError = kind === "error";

  return (
    <div
      className={`rounded border px-4 py-3 text-sm leading-6 ${
        isError
          ? "border-red-200 bg-red-50 text-red-800"
          : "border-emerald-200 bg-emerald-50 text-emerald-800"
      }`}
      role={isError ? "alert" : "status"}
      aria-live={isError ? "assertive" : "polite"}
    >
      {message}
    </div>
  );
}

function SubmitButton({ busy, label }: { busy: boolean; label: string }) {
  return (
    <button
      type="submit"
      disabled={busy}
      className="w-full rounded bg-navy px-4 py-3 text-sm font-semibold text-white transition hover:bg-navy-hover focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-navy disabled:cursor-wait disabled:opacity-60"
    >
      {busy ? "Memproses..." : label}
    </button>
  );
}

export function RegisterForm({ continuePath }: { continuePath: string }) {
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<ApiError | null>(null);
  const [message, setMessage] = useState<string>();
  const [email, setEmail] = useState("");
  const [resendBusy, setResendBusy] = useState(false);
  const [resendMessage, setResendMessage] = useState<string>();

  async function handleSubmit(event: FormEvent<HTMLFormElement>): Promise<void> {
    event.preventDefault();
    setBusy(true);
    setError(null);
    setMessage(undefined);
    setResendMessage(undefined);

    const form = new FormData(event.currentTarget);
    const submittedEmail = String(form.get("email") ?? "").trim().toLowerCase();
    setEmail(submittedEmail);

    try {
      await registerUser({
        name: String(form.get("name") ?? "").trim(),
        email: submittedEmail,
        password: String(form.get("password") ?? ""),
        password_confirmation: String(form.get("password_confirmation") ?? ""),
        ...(String(form.get("whatsapp") ?? "").trim()
          ? { whatsapp: String(form.get("whatsapp")).trim() }
          : {}),
      });
      setMessage(
        "Akun berhasil dibuat. Verifikasi email sebelum masuk; tautan verifikasi telah dikirim jika email dapat menerima pesan.",
      );
    } catch (cause) {
      setError(
        cause instanceof ApiError
          ? cause
          : new ApiError("Pendaftaran belum berhasil. Silakan coba lagi.", 0),
      );
    } finally {
      setBusy(false);
    }
  }

  async function resendEmail(): Promise<void> {
    setResendBusy(true);
    setResendMessage(undefined);
    setError(null);

    try {
      const result = await resendVerification(email);
      setResendMessage(result.message);
    } catch (cause) {
      setError(
        cause instanceof ApiError
          ? cause
          : new ApiError("Instruksi belum dapat dikirim. Silakan coba lagi.", 0),
      );
    } finally {
      setResendBusy(false);
    }
  }

  if (message) {
    const signInHref = `/masuk?next=${encodeURIComponent(continuePath)}`;

    return (
      <div className="space-y-4">
        <FormMessage message={message} kind="success" />
        <p className="text-sm text-muted">
          Jika email verifikasi belum diterima, periksa folder spam atau kirim ulang instruksi.
        </p>
        {resendMessage && <FormMessage message={resendMessage} kind="success" />}
        {error && <FormMessage message={error.message} />}
        <button
          type="button"
          onClick={resendEmail}
          disabled={resendBusy || !email}
          className="w-full rounded border border-navy px-4 py-3 text-sm font-semibold text-navy hover:bg-canvas disabled:cursor-wait disabled:opacity-60"
        >
          {resendBusy ? "Mengirim..." : "Kirim ulang verifikasi"}
        </button>
        <Link
          href={signInHref}
          className="block text-center text-sm font-semibold text-navy underline underline-offset-4"
        >
          Lanjut ke masuk
        </Link>
      </div>
    );
  }

  return (
    <form onSubmit={handleSubmit} noValidate className="space-y-5">
      {error && <FormMessage message={error.message} />}
      <FormField
        id="name"
        name="name"
        label="Nama lengkap"
        autoComplete="name"
        maxLength={255}
        required
        error={fieldError(error, "name")}
      />
      <FormField
        id="email"
        name="email"
        label="Email"
        type="email"
        autoComplete="email"
        maxLength={255}
        required
        error={fieldError(error, "email")}
      />
      <FormField
        id="whatsapp"
        name="whatsapp"
        label="Nomor WhatsApp (opsional)"
        type="tel"
        autoComplete="tel"
        placeholder="+628123456789"
        hint="Gunakan format +62 jika ingin mencantumkan nomor."
        error={fieldError(error, "whatsapp")}
      />
      <FormField
        id="password"
        name="password"
        label="Kata sandi"
        type="password"
        autoComplete="new-password"
        minLength={8}
        required
        hint="Minimal 8 karakter."
        error={fieldError(error, "password")}
      />
      <FormField
        id="password_confirmation"
        name="password_confirmation"
        label="Ulangi kata sandi"
        type="password"
        autoComplete="new-password"
        minLength={8}
        required
        error={fieldError(error, "password_confirmation")}
      />
      <SubmitButton busy={busy} label="Buat akun" />
      <p className="text-center text-sm text-muted">
        Sudah memiliki akun?{" "}
        <Link
          href={`/masuk?next=${encodeURIComponent(continuePath)}`}
          className="font-semibold text-navy underline underline-offset-4"
        >
          Masuk
        </Link>
      </p>
    </form>
  );
}

export function LoginForm({ continuePath }: { continuePath: string }) {
  const router = useRouter();
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<ApiError | null>(null);
  const [submittedEmail, setSubmittedEmail] = useState("");
  const [verificationBusy, setVerificationBusy] = useState(false);
  const [verificationMessage, setVerificationMessage] = useState<string>();
  const [verificationError, setVerificationError] = useState<ApiError | null>(null);

  async function handleSubmit(event: FormEvent<HTMLFormElement>): Promise<void> {
    event.preventDefault();
    setBusy(true);
    setError(null);
    setVerificationError(null);
    setVerificationMessage(undefined);

    const form = new FormData(event.currentTarget);
    const email = String(form.get("email") ?? "").trim().toLowerCase();
    const password = String(form.get("password") ?? "");
    setSubmittedEmail(email);

    try {
      const result = await loginUser(email, password);
      const destination = new URL(continuePath, window.location.origin);
      if (result.previousSessionRevoked) {
        destination.searchParams.set("session_replaced", "1");
      }
      router.replace(`${destination.pathname}${destination.search}${destination.hash}`);
    } catch (cause) {
      setError(
        cause instanceof ApiError
          ? cause
          : new ApiError("Tidak dapat masuk. Silakan coba lagi.", 0),
      );
    } finally {
      setBusy(false);
    }
  }

  async function resendEmailVerification(): Promise<void> {
    setVerificationBusy(true);
    setVerificationError(null);
    setVerificationMessage(undefined);

    try {
      const result = await resendVerification(submittedEmail);
      setVerificationMessage(result.message);
    } catch (cause) {
      setVerificationError(
        cause instanceof ApiError
          ? cause
          : new ApiError("Instruksi verifikasi belum dapat dikirim.", 0),
      );
    } finally {
      setVerificationBusy(false);
    }
  }

  return (
    <form onSubmit={handleSubmit} noValidate className="space-y-5">
      {error && <FormMessage message={error.message} />}
      {error?.status === 403 && submittedEmail && (
        <div className="space-y-3 rounded border border-amber-200 bg-amber-50 p-4">
          <p className="text-sm text-amber-900">
            Verifikasi alamat email sebelum masuk. Anda dapat meminta instruksi verifikasi baru.
          </p>
          {verificationError && <FormMessage message={verificationError.message} />}
          {verificationMessage && (
            <FormMessage kind="success" message={verificationMessage} />
          )}
          <button
            type="button"
            onClick={resendEmailVerification}
            disabled={verificationBusy}
            className="text-sm font-semibold text-navy underline disabled:opacity-60"
          >
            {verificationBusy ? "Mengirim..." : "Kirim ulang verifikasi"}
          </button>
        </div>
      )}
      <FormField
        id="email"
        name="email"
        label="Email"
        type="email"
        autoComplete="email"
        required
        error={fieldError(error, "email")}
      />
      <div className="space-y-1.5">
        <div className="flex items-center justify-between gap-4">
          <label htmlFor="password" className="text-sm font-semibold text-ink">
            Kata sandi
          </label>
          <Link href="/lupa-sandi" className="text-xs font-semibold text-navy underline">
            Lupa kata sandi?
          </Link>
        </div>
        <input
          id="password"
          name="password"
          type="password"
          autoComplete="current-password"
          required
          aria-invalid={Boolean(fieldError(error, "password"))}
          className="w-full rounded border border-slate-300 bg-white px-3 py-2.5 text-sm text-ink outline-none focus:border-navy focus-visible:ring-2 focus-visible:ring-navy/20"
        />
        {fieldError(error, "password") && (
          <p className="text-xs text-red-700">{fieldError(error, "password")}</p>
        )}
      </div>
      <SubmitButton busy={busy} label="Masuk" />
      <p className="text-center text-sm text-muted">
        Belum memiliki akun?{" "}
        <Link
          href={`/daftar?next=${encodeURIComponent(continuePath)}`}
          className="font-semibold text-navy underline underline-offset-4"
        >
          Daftar
        </Link>
      </p>
    </form>
  );
}

export function ForgotPasswordForm() {
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<ApiError | null>(null);
  const [success, setSuccess] = useState(false);

  async function handleSubmit(event: FormEvent<HTMLFormElement>): Promise<void> {
    event.preventDefault();
    setBusy(true);
    setError(null);

    const form = new FormData(event.currentTarget);
    const email = String(form.get("email") ?? "").trim().toLowerCase();

    try {
      await requestPasswordReset(email);
      setSuccess(true);
    } catch (cause) {
      setError(
        cause instanceof ApiError
          ? cause
          : new ApiError("Permintaan reset belum dapat dikirim. Silakan coba lagi.", 0),
      );
    } finally {
      setBusy(false);
    }
  }

  return (
    <form onSubmit={handleSubmit} noValidate className="space-y-5">
      {error && <FormMessage message={error.message} />}
      {success && (
        <FormMessage
          kind="success"
          message="Jika email terdaftar, instruksi reset kata sandi akan dikirim. Periksa kotak masuk dan folder spam."
        />
      )}
      <FormField
        id="email"
        name="email"
        label="Email akun"
        type="email"
        autoComplete="email"
        required
        error={fieldError(error, "email")}
      />
      <SubmitButton busy={busy} label="Kirim instruksi reset" />
      <p className="text-center text-sm text-muted">
        Ingat kata sandi?{" "}
        <Link href="/masuk" className="font-semibold text-navy underline underline-offset-4">
          Kembali ke masuk
        </Link>
      </p>
    </form>
  );
}

export function ResetPasswordForm({ token, email }: { token: string; email: string }) {
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<ApiError | null>(null);
  const [success, setSuccess] = useState(false);
  async function handleSubmit(event: FormEvent<HTMLFormElement>): Promise<void> {
    event.preventDefault();
    setBusy(true);
    setError(null);
    const form = new FormData(event.currentTarget);

    try {
      await resetPassword({
        token,
        email: String(form.get("email") ?? "").trim().toLowerCase(),
        password: String(form.get("password") ?? ""),
        password_confirmation: String(form.get("password_confirmation") ?? ""),
      });
      setSuccess(true);
    } catch (cause) {
      setError(
        cause instanceof ApiError
          ? cause
          : new ApiError("Kata sandi belum dapat diatur ulang. Silakan coba lagi.", 0),
      );
    } finally {
      setBusy(false);
    }
  }

  if (!token || !email) {
    return (
      <div className="space-y-4">
        <FormMessage message="Tautan reset tidak lengkap atau tidak valid. Minta tautan baru untuk melanjutkan." />
        <Link
          href="/lupa-sandi"
          className="block text-center text-sm font-semibold text-navy underline"
        >
          Minta tautan reset baru
        </Link>
      </div>
    );
  }

  if (success) {
    return (
      <div className="space-y-4">
        <FormMessage kind="success" message="Kata sandi berhasil diatur ulang. Silakan masuk dengan kata sandi baru." />
        <Link
          href="/masuk"
          className="block rounded bg-navy px-4 py-3 text-center text-sm font-semibold text-white hover:bg-navy-hover"
        >
          Masuk
        </Link>
      </div>
    );
  }

  return (
    <form onSubmit={handleSubmit} noValidate className="space-y-5">
      {error && <FormMessage message={error.message} />}
      <FormField
        id="email"
        name="email"
        label="Email akun"
        type="email"
        autoComplete="email"
        defaultValue={email}
        required
        error={fieldError(error, "email")}
      />
      <FormField
        id="password"
        name="password"
        label="Kata sandi baru"
        type="password"
        autoComplete="new-password"
        minLength={8}
        required
        error={fieldError(error, "password")}
      />
      <FormField
        id="password_confirmation"
        name="password_confirmation"
        label="Ulangi kata sandi baru"
        type="password"
        autoComplete="new-password"
        minLength={8}
        required
        error={fieldError(error, "password_confirmation")}
      />
      <SubmitButton busy={busy} label="Simpan kata sandi baru" />
    </form>
  );
}
