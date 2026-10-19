"use client";

import Image from "next/image";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useState, type FormEvent } from "react";
import {
  changePassword,
  getDeviceSessions,
  getProfile,
  logout,
  revokeDeviceSession,
  resendVerification,
  updateProfile,
  type DeviceSession,
  type User,
} from "@/lib/api/auth";
import { ApiError } from "@/lib/api/client";
import FormField from "@/components/auth/FormField";

function Message({
  children,
  success = false,
}: {
  children: string;
  success?: boolean;
}) {
  return (
    <p
      className={`rounded border px-4 py-3 text-sm ${
        success
          ? "border-emerald-200 bg-emerald-50 text-emerald-800"
          : "border-red-200 bg-red-50 text-red-800"
      }`}
      role={success ? "status" : "alert"}
    >
      {children}
    </p>
  );
}

function fieldError(error: ApiError | null, field: string): string | undefined {
  return error?.fields[field]?.[0];
}

function formatActivity(value: string): string {
  return new Intl.DateTimeFormat("id-ID", {
    dateStyle: "medium",
    timeStyle: "short",
    timeZone: "Asia/Jakarta",
  }).format(new Date(value));
}

export default function ProfileSettings({ sessionReplaced = false }: { sessionReplaced?: boolean }) {
  const router = useRouter();
  const [user, setUser] = useState<User | null>(null);
  const [loading, setLoading] = useState(true);
  const [loadError, setLoadError] = useState<ApiError | null>(null);
  const [profileError, setProfileError] = useState<ApiError | null>(null);
  const [profileMessage, setProfileMessage] = useState<string>();
  const [profileBusy, setProfileBusy] = useState(false);
  const [passwordError, setPasswordError] = useState<ApiError | null>(null);
  const [passwordMessage, setPasswordMessage] = useState<string>();
  const [passwordBusy, setPasswordBusy] = useState(false);
  const [verificationBusy, setVerificationBusy] = useState(false);
  const [verificationMessage, setVerificationMessage] = useState<string>();
  const [verificationError, setVerificationError] = useState<ApiError | null>(null);
  const [logoutBusy, setLogoutBusy] = useState(false);
  const [logoutError, setLogoutError] = useState<ApiError | null>(null);
  const [deviceSessions, setDeviceSessions] = useState<DeviceSession[]>([]);
  const [sessionLimit, setSessionLimit] = useState(2);
  const [sessionsLoading, setSessionsLoading] = useState(true);
  const [sessionsError, setSessionsError] = useState<ApiError | null>(null);
  const [revokingSessionId, setRevokingSessionId] = useState<string>();
  const [sessionsMessage, setSessionsMessage] = useState<string>();
  const [sessionReplacedNotice] = useState(sessionReplaced);

  useEffect(() => {
    let active = true;
    getProfile()
      .then((profile) => {
        if (active) setUser(profile);
      })
      .catch((cause: unknown) => {
        if (active) {
          setLoadError(
            cause instanceof ApiError
              ? cause
              : new ApiError("Profil belum dapat dimuat. Silakan coba lagi.", 0),
          );
        }
      })
      .finally(() => {
        if (active) setLoading(false);
      });

    getDeviceSessions()
      .then((result) => {
        if (active) {
          setDeviceSessions(result.data);
          setSessionLimit(result.meta.max_active_sessions);
        }
      })
      .catch((cause: unknown) => {
        if (active) {
          setSessionsError(
            cause instanceof ApiError
              ? cause
              : new ApiError("Daftar perangkat belum dapat dimuat.", 0),
          );
        }
      })
      .finally(() => {
        if (active) setSessionsLoading(false);
      });

    if (sessionReplaced) {
      router.replace("/pengaturan", { scroll: false });
    }

    return () => {
      active = false;
    };
  }, [router, sessionReplaced]);

  async function saveProfile(event: FormEvent<HTMLFormElement>): Promise<void> {
    event.preventDefault();
    setProfileBusy(true);
    setProfileError(null);
    setProfileMessage(undefined);
    const form = new FormData(event.currentTarget);
    const avatar = form.get("avatar");

    try {
      const updated = await updateProfile({
        name: String(form.get("name") ?? "").trim(),
        whatsapp: String(form.get("whatsapp") ?? "").trim(),
        ...(avatar instanceof File && avatar.size > 0 ? { avatar } : {}),
      });
      setUser(updated);
      setProfileMessage("Profil berhasil diperbarui.");
    } catch (cause) {
      setProfileError(
        cause instanceof ApiError
          ? cause
          : new ApiError("Profil belum dapat disimpan. Silakan coba lagi.", 0),
      );
    } finally {
      setProfileBusy(false);
    }
  }

  async function savePassword(event: FormEvent<HTMLFormElement>): Promise<void> {
    event.preventDefault();
    setPasswordBusy(true);
    setPasswordError(null);
    setPasswordMessage(undefined);
    const formElement = event.currentTarget;
    const form = new FormData(formElement);

    try {
      const result = await changePassword({
        current_password: String(form.get("current_password") ?? ""),
        password: String(form.get("password") ?? ""),
        password_confirmation: String(form.get("password_confirmation") ?? ""),
      });
      formElement.reset();
      setPasswordMessage(result.message);
    } catch (cause) {
      setPasswordError(
        cause instanceof ApiError
          ? cause
          : new ApiError("Kata sandi belum dapat diubah. Silakan coba lagi.", 0),
      );
    } finally {
      setPasswordBusy(false);
    }
  }

  async function sendVerification(): Promise<void> {
    if (!user) return;
    setVerificationBusy(true);
    setVerificationError(null);
    setVerificationMessage(undefined);

    try {
      const result = await resendVerification(user.email);
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

  async function signOut(): Promise<void> {
    setLogoutBusy(true);
    setLogoutError(null);
    try {
      await logout();
      router.replace("/masuk");
      router.refresh();
    } catch (cause) {
      setLogoutError(
        cause instanceof ApiError
          ? cause
          : new ApiError("Belum dapat keluar dari akun. Silakan coba lagi.", 0),
      );
      setLogoutBusy(false);
    }
  }

  async function signOutDevice(session: DeviceSession): Promise<void> {
    setRevokingSessionId(session.id);
    setSessionsError(null);
    setSessionsMessage(undefined);
    try {
      await revokeDeviceSession(session.id);
      setDeviceSessions((current) => current.filter((item) => item.id !== session.id));
      setSessionsMessage(`Sesi ${session.device} berhasil dicabut.`);
    } catch (cause) {
      setSessionsError(
        cause instanceof ApiError
          ? cause
          : new ApiError("Sesi perangkat belum dapat dicabut. Silakan coba lagi.", 0),
      );
    } finally {
      setRevokingSessionId(undefined);
    }
  }

  if (loading) {
    return (
      <main className="mx-auto max-w-3xl px-4 py-12 md:px-8">
        <p className="text-center text-muted" role="status">
          Memuat profil...
        </p>
      </main>
    );
  }

  if (!user) {
    const unauthenticated = loadError?.status === 401;
    return (
      <main className="mx-auto max-w-3xl px-4 py-12 md:px-8">
        <section className="rounded-lg border border-hairline bg-card p-8 text-center">
          <h1 className="font-serif text-3xl font-semibold text-navy">
            {unauthenticated ? "Masuk untuk mengelola akun" : "Profil belum dapat dimuat"}
          </h1>
          <p className="mt-3 text-sm leading-6 text-muted">
            {unauthenticated
              ? "Sesi Anda belum aktif atau telah berakhir. Masuk untuk melihat pengaturan profil."
              : loadError?.message ?? "Terjadi kendala saat memuat akun. Silakan coba lagi."}
          </p>
          <div className="mt-6 flex justify-center gap-3">
            {unauthenticated ? (
              <Link
                href="/masuk?next=%2Fpengaturan"
                className="rounded bg-navy px-4 py-2.5 text-sm font-semibold text-white hover:bg-navy-hover"
              >
                Masuk
              </Link>
            ) : (
              <button
                type="button"
                onClick={() => window.location.reload()}
                className="rounded bg-navy px-4 py-2.5 text-sm font-semibold text-white hover:bg-navy-hover"
              >
                Coba lagi
              </button>
            )}
          </div>
        </section>
      </main>
    );
  }

  return (
    <main className="mx-auto max-w-3xl px-4 py-10 md:px-8">
      <header className="mb-8 flex flex-wrap items-start justify-between gap-4">
        <div>
          <p className="text-xs font-semibold uppercase tracking-[0.16em] text-vip-gold">
            Akun Membership
          </p>
          <h1 className="mt-2 font-serif text-3xl font-semibold text-navy">Pengaturan profil</h1>
          <p className="mt-2 text-sm text-muted">Kelola informasi dan keamanan akun Anda.</p>
        </div>
        <Link href="/langganan" className="text-sm font-semibold text-navy underline">
          Lihat paket
        </Link>
      </header>

      {logoutError && <div className="mb-6"><Message>{logoutError.message}</Message></div>}
      {sessionReplacedNotice && (
        <div className="mb-6">
          <Message success>
            Batas dua sesi aktif tercapai. Sesi yang paling lama tidak aktif telah dicabut.
          </Message>
        </div>
      )}

      <div className="space-y-6">
        <section className="rounded-lg border border-hairline bg-card p-6 md:p-8">
          <h2 className="font-serif text-xl font-semibold text-navy">Informasi pribadi</h2>
          <form onSubmit={saveProfile} noValidate className="mt-6 space-y-5">
            {profileError && <Message>{profileError.message}</Message>}
            {profileMessage && <Message success>{profileMessage}</Message>}

            <div className="flex items-center gap-4">
              {user.avatar_url ? (
                <Image
                  src={user.avatar_url}
                  alt={`Foto profil ${user.name}`}
                  width={72}
                  height={72}
                  unoptimized
                  className="h-18 w-18 rounded-full object-cover"
                />
              ) : (
                <div
                  aria-hidden="true"
                  className="flex h-18 w-18 items-center justify-center rounded-full bg-canvas font-serif text-2xl font-semibold text-navy"
                >
                  {user.name.slice(0, 1).toUpperCase()}
                </div>
              )}
              <div className="text-sm text-muted">
                <p className="font-semibold text-ink">{user.name}</p>
                <p>Foto profil opsional, JPG/PNG/WebP maksimal 2 MB.</p>
              </div>
            </div>

            <FormField
              id="name"
              name="name"
              label="Nama lengkap"
              autoComplete="name"
              maxLength={255}
              defaultValue={user.name}
              required
              error={fieldError(profileError, "name")}
            />
            <FormField
              id="email"
              name="email"
              label="Email"
              type="email"
              autoComplete="email"
              value={user.email}
              readOnly
              hint="Email akun tidak dapat diubah."
            />
            <FormField
              id="whatsapp"
              name="whatsapp"
              label="Nomor WhatsApp (opsional)"
              type="tel"
              autoComplete="tel"
              placeholder="+628123456789"
              defaultValue={user.whatsapp ?? ""}
              hint="Gunakan format +62, misalnya +628123456789."
              error={fieldError(profileError, "whatsapp")}
            />
            <div className="space-y-1.5">
              <label htmlFor="avatar" className="block text-sm font-semibold text-ink">
                Foto profil
              </label>
              <input
                id="avatar"
                name="avatar"
                type="file"
                accept="image/jpeg,image/png,image/webp"
                aria-invalid={Boolean(fieldError(profileError, "avatar"))}
                aria-describedby="avatar-description"
                className="w-full rounded border border-slate-300 bg-white px-3 py-2.5 text-sm text-ink file:mr-3 file:rounded file:border-0 file:bg-canvas file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-navy"
              />
              <p id="avatar-description" className="text-xs leading-5 text-muted">
                JPG, PNG, atau WebP; ukuran maksimal 2 MB.
              </p>
              {fieldError(profileError, "avatar") && (
                <p className="text-xs text-red-700">{fieldError(profileError, "avatar")}</p>
              )}
            </div>

            {!user.email_verified_at && (
              <div className="space-y-3 rounded border border-amber-200 bg-amber-50 p-4">
                <p className="text-sm text-amber-900">
                  Email belum diverifikasi. Verifikasi email untuk mengakses akun sepenuhnya.
                </p>
                {verificationError && <Message>{verificationError.message}</Message>}
                {verificationMessage && <Message success>{verificationMessage}</Message>}
                <button
                  type="button"
                  onClick={sendVerification}
                  disabled={verificationBusy}
                  className="text-sm font-semibold text-navy underline disabled:opacity-60"
                >
                  {verificationBusy ? "Mengirim..." : "Kirim ulang verifikasi"}
                </button>
              </div>
            )}

            <button
              type="submit"
              disabled={profileBusy}
              className="rounded bg-navy px-5 py-3 text-sm font-semibold text-white hover:bg-navy-hover disabled:cursor-wait disabled:opacity-60"
            >
              {profileBusy ? "Menyimpan..." : "Simpan profil"}
            </button>
          </form>
        </section>

        <section className="rounded-lg border border-hairline bg-card p-6 md:p-8">
          <div className="flex flex-wrap items-start justify-between gap-3">
            <div>
              <h2 className="font-serif text-xl font-semibold text-navy">Perangkat dan sesi</h2>
              <p className="mt-2 text-sm leading-6 text-muted">
                Maksimal {sessionLimit} sesi aktif. Saat masuk dari perangkat lain dan batas
                tercapai, sesi yang paling lama tidak aktif akan dicabut.
              </p>
            </div>
            <span className="rounded-full bg-canvas px-3 py-1 text-sm font-semibold text-navy">
              {deviceSessions.length}/{sessionLimit} aktif
            </span>
          </div>

          {sessionsError && (
            <div className="mt-5 space-y-3">
              <Message>{sessionsError.message}</Message>
              <button
                type="button"
                onClick={() => {
                  setSessionsLoading(true);
                  setSessionsError(null);
                  getDeviceSessions()
                    .then((result) => {
                      setDeviceSessions(result.data);
                      setSessionLimit(result.meta.max_active_sessions);
                    })
                    .catch((cause: unknown) => {
                      setSessionsError(
                        cause instanceof ApiError
                          ? cause
                          : new ApiError("Daftar perangkat belum dapat dimuat.", 0),
                      );
                    })
                    .finally(() => setSessionsLoading(false));
                }}
                className="text-sm font-semibold text-navy underline"
              >
                Coba lagi
              </button>
            </div>
          )}
          {sessionsMessage && <div className="mt-5"><Message success>{sessionsMessage}</Message></div>}
          {sessionsLoading ? (
            <p className="mt-5 text-sm text-muted" role="status">Memuat daftar perangkat...</p>
          ) : deviceSessions.length > 0 ? (
            <ul className="mt-5 divide-y divide-hairline">
              {deviceSessions.map((session) => (
                <li
                  key={session.id}
                  className="flex flex-wrap items-center justify-between gap-4 py-4 first:pt-0 last:pb-0"
                >
                  <div>
                    <p className="font-semibold text-ink">
                      {session.device}
                      {session.is_current && (
                        <span className="ml-2 rounded bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-800">
                          Perangkat ini
                        </span>
                      )}
                    </p>
                    <p className="mt-1 text-sm text-muted">
                      Aktivitas terakhir {formatActivity(session.last_activity)}
                      {session.ip_address ? ` · IP ${session.ip_address}` : ""}
                    </p>
                  </div>
                  {session.is_current ? (
                    <span className="text-sm text-muted">Sesi saat ini</span>
                  ) : (
                    <button
                      type="button"
                      onClick={() => signOutDevice(session)}
                      disabled={revokingSessionId !== undefined}
                      className="rounded border border-red-300 px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-50 disabled:cursor-wait disabled:opacity-60"
                    >
                      {revokingSessionId === session.id ? "Mencabut..." : "Cabut sesi"}
                    </button>
                  )}
                </li>
              ))}
            </ul>
          ) : !sessionsError ? (
            <p className="mt-5 text-sm text-muted">Tidak ada sesi aktif yang tercatat.</p>
          ) : null}
        </section>

        <section className="rounded-lg border border-hairline bg-card p-6 md:p-8">
          <h2 className="font-serif text-xl font-semibold text-navy">Ganti kata sandi</h2>
          <form onSubmit={savePassword} noValidate className="mt-6 space-y-5">
            {passwordError && <Message>{passwordError.message}</Message>}
            {passwordMessage && <Message success>{passwordMessage}</Message>}
            <FormField
              id="current_password"
              name="current_password"
              label="Kata sandi saat ini"
              type="password"
              autoComplete="current-password"
              required
              error={fieldError(passwordError, "current_password")}
            />
            <FormField
              id="new_password"
              name="password"
              label="Kata sandi baru"
              type="password"
              autoComplete="new-password"
              minLength={8}
              required
              hint="Minimal 8 karakter."
              error={fieldError(passwordError, "password")}
            />
            <FormField
              id="password_confirmation"
              name="password_confirmation"
              label="Ulangi kata sandi baru"
              type="password"
              autoComplete="new-password"
              minLength={8}
              required
              error={fieldError(passwordError, "password_confirmation")}
            />
            <button
              type="submit"
              disabled={passwordBusy}
              className="rounded border border-navy px-5 py-3 text-sm font-semibold text-navy hover:bg-canvas disabled:cursor-wait disabled:opacity-60"
            >
              {passwordBusy ? "Mengubah..." : "Ganti kata sandi"}
            </button>
          </form>
        </section>

        <section className="flex flex-wrap items-center justify-between gap-4 rounded-lg border border-hairline bg-card p-6">
          <div>
            <h2 className="font-semibold text-navy">Keluar dari akun</h2>
            <p className="mt-1 text-sm text-muted">Akhiri sesi pada perangkat ini.</p>
          </div>
          <button
            type="button"
            onClick={signOut}
            disabled={logoutBusy}
            className="rounded border border-red-300 px-4 py-2.5 text-sm font-semibold text-red-700 hover:bg-red-50 disabled:cursor-wait disabled:opacity-60"
          >
            {logoutBusy ? "Keluar..." : "Keluar"}
          </button>
        </section>
      </div>
    </main>
  );
}
