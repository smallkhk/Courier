import type { Metadata } from "next";
import { config } from "@/lib/config.ts";
import "./globals.css";

export const metadata: Metadata = {
  title: config.platformName,
  description: "Practice real conversations with a voice AI.",
};

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="en">
      <body>{children}</body>
    </html>
  );
}
