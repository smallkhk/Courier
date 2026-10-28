import Link from "next/link";
import { LogoMark } from "@/components/Icons";
import { LogoutButton } from "@/components/LogoutButton";
import { NavLinks } from "@/components/NavLinks";
import { config, formatUsd } from "@/lib/config.ts";
import { pageUser } from "@/lib/page-auth.ts";
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
          <Link href="/wallet" className="balance-chip">
            {formatUsd(getCredit(user.accountId))}
          </Link>
          <span className="logout">
            <LogoutButton />
          </span>
        </div>
      </header>
      <main>{children}</main>
      <NavLinks variant="tabs" />
    </>
  );
}
