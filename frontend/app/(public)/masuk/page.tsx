import type { Metadata } from "next";
import { Suspense } from "react";
import AuthShell from "@/components/auth/AuthShell";
import { LoginForm } from "@/components/auth/AuthForms";

type LoginPageProps = {
  searchParams: Promise<{ [key: string]: string | string[] | undefined }>;
};

export const metadata: Metadata = {
  title: "Masuk | Solopos Membership",
  description: "Masuk ke akun Solopos Membership.",
};

async function LoginFormWithSearchParams({ searchParams }: LoginPageProps) {
  const next = (await searchParams).next;
  const continuePath =
    typeof next === "string" &&
    next.startsWith("/") &&
    !next.startsWith("//") &&
    !next.includes("\\")
      ? next
      : "/pengaturan";

  return <LoginForm continuePath={continuePath} />;
}

export default function LoginPage({ searchParams }: LoginPageProps) {
  return (
    <AuthShell
      eyebrow="Selamat datang kembali"
      title="Masuk ke akun"
      description="Gunakan email terverifikasi untuk mengakses akun Solopos Membership."
    >
      <Suspense fallback={<p className="text-center text-sm text-muted">Memuat formulir...</p>}>
        <LoginFormWithSearchParams searchParams={searchParams} />
      </Suspense>
    </AuthShell>
  );
}
