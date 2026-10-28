"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";

const TABS = [
  { href: "/admin", label: "Overview" },
  { href: "/admin/users", label: "Users" },
  { href: "/admin/payments", label: "Payments" },
  { href: "/admin/calls", label: "Calls" },
  { href: "/admin/voices", label: "Voices" },
  { href: "/admin/settings", label: "Settings" },
  { href: "/admin/log", label: "Activity log" },
];

export function AdminTabs() {
  const path = usePathname();
  return (
    <nav className="admin-tabs">
      {TABS.map((t) => (
        <Link
          key={t.href}
          href={t.href}
          className={(t.href === "/admin" ? path === "/admin" : path.startsWith(t.href)) ? "active" : ""}
        >
          {t.label}
        </Link>
      ))}
    </nav>
  );
}
