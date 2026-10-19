import type { Metadata } from "next";
import { Suspense } from "react";
import ProfileSettings from "@/components/profile/ProfileSettings";

export const metadata: Metadata = {
  title: "Pengaturan Profil | Solopos Membership",
  description: "Kelola profil dan keamanan akun Solopos Membership.",
};

type ProfilePageProps = {
  searchParams: Promise<{ [key: string]: string | string[] | undefined }>;
};

async function ProfileSettingsPage({ searchParams }: ProfilePageProps) {
  const query = await searchParams;
  const sessionReplaced = query.session_replaced === "1";

  return <ProfileSettings sessionReplaced={sessionReplaced} />;
}

export default function ProfilePage({ searchParams }: ProfilePageProps) {
  return (
    <Suspense
      fallback={
        <main className="mx-auto max-w-3xl px-4 py-12 md:px-8">
          <p className="text-center text-muted" role="status">
            Memuat pengaturan profil...
          </p>
        </main>
      }
    >
      <ProfileSettingsPage searchParams={searchParams} />
    </Suspense>
  );
}
