import Link from "next/link";
import { LogoMark } from "@/components/Icons";
import { LogoutButton } from "@/components/LogoutButton";
import { NavLinks } from "@/components/NavLinks";
import { config, formatUsd } from "@/lib/config.ts";
import { pageUser } from "@/lib/page-auth.ts";
import { ShieldIcon } from "@/components/Icons";
import { getCredit } from "@/lib/repo.ts";

export const dynamic = "force-dynamic";

export default async function AppLayout({ children }: { children: React.ReactNode }) {
  const user = await pageUser();
  return (
    <>
      <header className="topbar">
        <div className="topbar-inner">
          <Link href="/home" className="brand">
            <LogoMark />
            {config.platformName}
          </Link>
          <NavLinks variant="top" />
          {user.isAdmin && (
            <Link href="/admin" className="balance-chip" title="Admin" style={{ display: "inline-flex", gap: 6, alignItems: "center" }}>
              <ShieldIcon size={15} /> Admin
            </Link>
          )}
          <Link href="/wallet" className="balance-chip">
            {formatUsd(getCredit(user.accountId))}
          </Link>
          <span className="logout">
            <LogoutButton />
          </span>
        </div>
        {config.announcement && <div className="banner">📣 {config.announcement}</div>}
      </header>
      <main>{children}</main>
      <NavLinks variant="tabs" />
    </>
  );
}
