import type { Metadata } from "next";
import { Suspense } from "react";
import AuthShell from "@/components/auth/AuthShell";
import { ResetPasswordForm } from "@/components/auth/AuthForms";

type ResetPasswordPageProps = {
  searchParams: Promise<{ [key: string]: string | string[] | undefined }>;
};

export const metadata: Metadata = {
  title: "Kata Sandi Baru | Solopos Membership",
  description: "Atur kata sandi baru untuk akun Solopos Membership.",
};

async function ResetPasswordFormWithSearchParams({
  searchParams,
}: ResetPasswordPageProps) {
  const params = await searchParams;
  const token = typeof params.token === "string" ? params.token : "";
  const email = typeof params.email === "string" ? params.email : "";

  return <ResetPasswordForm token={token} email={email} />;
}

export default function ResetPasswordPage({ searchParams }: ResetPasswordPageProps) {
  return (
    <AuthShell
      eyebrow="Keamanan akun"
      title="Buat kata sandi baru"
      description="Gunakan kata sandi minimal 8 karakter. Tautan reset hanya dapat digunakan sekali."
    >
      <Suspense fallback={<p className="text-center text-sm text-muted">Memeriksa tautan reset...</p>}>
        <ResetPasswordFormWithSearchParams searchParams={searchParams} />
      </Suspense>
    </AuthShell>
  );
}
