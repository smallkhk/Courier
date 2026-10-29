import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  // A self-contained server in .next/standalone, so shared hosting can run
  // it without installing packages (see scripts/package-namecheap.mjs).
  output: "standalone",
  // The microphone is needed on the practice page only.
  async headers() {
    return [
      {
        source: "/:path*",
        headers: [
          { key: "X-Content-Type-Options", value: "nosniff" },
          { key: "Referrer-Policy", value: "same-origin" },
          { key: "Permissions-Policy", value: "microphone=(self), camera=()" },
        ],
      },
    ];
  },
};

export default nextConfig;
