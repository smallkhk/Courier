import Link from "next/link";
import { notFound } from "next/navigation";
import { AdminTabs } from "@/components/admin/AdminTabs";
import { LogoMark } from "@/components/Icons";
import { currentUser } from "@/lib/auth.ts";
import { config } from "@/lib/config.ts";

export const dynamic = "force-dynamic";

export default async function AdminLayout({ children }: { children: React.ReactNode }) {
  const user = await currentUser();
  // Non-admins get the normal 404, so the admin area doesn't reveal itself.
  if (!user?.isAdmin) notFound();
  return (
    <>
      <header className="topbar">
        <div className="topbar-inner" style={{ maxWidth: 1200 }}>
          <Link href="/admin" className="brand">
            <LogoMark />
            {config.platformName} <span className="admin-badge">ADMIN</span>
          </Link>
          <span className="small muted" style={{ display: "none" }} />
          <Link href="/home" className="button secondary" style={{ padding: "8px 14px", fontSize: "0.85rem" }}>
            Back to app
          </Link>
        </div>
        <AdminTabs />
      </header>
      <main className="admin-main">{children}</main>
    </>
  );
}
