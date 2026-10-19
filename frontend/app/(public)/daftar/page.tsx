import type { Metadata } from "next";
import { Suspense } from "react";
import AuthShell from "@/components/auth/AuthShell";
import { RegisterForm } from "@/components/auth/AuthForms";

type RegisterPageProps = {
  searchParams: Promise<{ [key: string]: string | string[] | undefined }>;
};

export const metadata: Metadata = {
  title: "Daftar | Solopos Membership",
  description: "Buat akun Solopos Membership.",
};

async function RegisterFormWithSearchParams({ searchParams }: RegisterPageProps) {
  const next = (await searchParams).next;
  const continuePath =
    typeof next === "string" &&
    next.startsWith("/") &&
    !next.startsWith("//") &&
    !next.includes("\\")
      ? next
      : "/pengaturan";

  return <RegisterForm continuePath={continuePath} />;
}

export default function RegisterPage({ searchParams }: RegisterPageProps) {
  return (
    <AuthShell
      eyebrow="Mulai berlangganan"
      title="Buat akun"
      description="Daftar dengan email untuk mengelola langganan dan menikmati layanan Solopos Membership."
    >
      <Suspense fallback={<p className="text-center text-sm text-muted">Memuat formulir...</p>}>
        <RegisterFormWithSearchParams searchParams={searchParams} />
      </Suspense>
    </AuthShell>
  );
}
