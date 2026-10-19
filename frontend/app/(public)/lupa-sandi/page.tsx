import type { Metadata } from "next";
import AuthShell from "@/components/auth/AuthShell";
import { ForgotPasswordForm } from "@/components/auth/AuthForms";

export const metadata: Metadata = {
  title: "Lupa Kata Sandi | Solopos Membership",
  description: "Minta tautan untuk mengatur ulang kata sandi Solopos Membership.",
};

export default function ForgotPasswordPage() {
  return (
    <AuthShell
      eyebrow="Keamanan akun"
      title="Atur ulang kata sandi"
      description="Masukkan email akun. Jika terdaftar, kami akan mengirim tautan reset yang berlaku selama 60 menit."
    >
      <ForgotPasswordForm />
    </AuthShell>
  );
}
