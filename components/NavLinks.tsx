"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { HomeIcon, UserIcon, WalletIcon, WaveIcon } from "./Icons";

const LINKS = [
  { href: "/home", label: "Home", Icon: HomeIcon, match: ["/home", "/callers"] },
  { href: "/profile", label: "Profile", Icon: UserIcon, match: ["/profile"] },
  { href: "/voices", label: "Voices", Icon: WaveIcon, match: ["/voices"] },
  { href: "/wallet", label: "Wallet", Icon: WalletIcon, match: ["/wallet"] },
];

export function NavLinks({ variant }: { variant: "top" | "tabs" }) {
  const path = usePathname();
  return (
    <nav className={variant === "top" ? "nav" : "tabbar"}>
      {LINKS.map(({ href, label, Icon, match }) => (
        <Link key={href} href={href} className={match.some((m) => path.startsWith(m)) ? "active" : ""}>
          <Icon size={variant === "top" ? 16 : 20} />
          {label}
        </Link>
      ))}
    </nav>
  );
}
