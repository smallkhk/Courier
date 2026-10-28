import Link from "next/link";
import { LogoutButton } from "@/components/LogoutButton";
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
            {config.platformName}
          </Link>
          <nav className="nav">
            <Link href="/home">Home</Link>
            <Link href="/profile">My profile</Link>
            <Link href="/voices">Voices</Link>
            <Link href="/wallet">Wallet · {formatUsd(getCredit(user.accountId))}</Link>
          </nav>
          <LogoutButton />
        </div>
      </header>
      <main>{children}</main>
    </>
  );
}
