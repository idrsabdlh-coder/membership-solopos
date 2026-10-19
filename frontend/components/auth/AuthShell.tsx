import Link from "next/link";
import type { ReactNode } from "react";

export default function AuthShell({
  eyebrow,
  title,
  description,
  children,
}: {
  eyebrow: string;
  title: string;
  description: string;
  children: ReactNode;
}) {
  return (
    <main className="mx-auto flex min-h-screen w-full max-w-7xl flex-col px-4 py-6 md:px-8">
      <header className="flex items-center justify-between border-b border-hairline pb-5">
        <Link href="/" className="font-serif text-xl font-semibold tracking-wide text-navy">
          SOLOPOS
          <span className="ml-2 font-sans text-xs font-medium tracking-normal text-muted">
            MEMBERSHIP
          </span>
        </Link>
        <Link
          href="/langganan"
          className="rounded px-3 py-2 text-sm font-semibold text-navy hover:bg-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-navy"
        >
          Lihat paket
        </Link>
      </header>

      <section className="mx-auto my-auto w-full max-w-lg py-12">
        <div className="mb-7 text-center">
          <p className="text-xs font-semibold uppercase tracking-[0.16em] text-vip-gold">
            {eyebrow}
          </p>
          <h1 className="mt-3 font-serif text-3xl font-semibold text-navy md:text-4xl">
            {title}
          </h1>
          <p className="mt-3 text-sm leading-6 text-muted">{description}</p>
        </div>
        <div className="rounded-lg border border-hairline bg-card p-6 shadow-sm md:p-8">
          {children}
        </div>
        <p className="mt-6 text-center text-xs leading-5 text-muted">
          Informasi akun digunakan untuk menyediakan layanan Solopos Membership.
        </p>
      </section>
    </main>
  );
}
