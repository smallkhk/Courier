// Builds a folder ready to upload to cPanel / Namecheap shared hosting:
//   npm run package:namecheap
// Output: deploy/voice (and deploy/voice.zip, with the app at the zip's
// root, when zip is available). scripts/install-cpanel.sh installs it.
import { cpSync, existsSync, mkdirSync, rmSync } from "node:fs";
import { execSync } from "node:child_process";
import { join } from "node:path";

const root = process.cwd();
const out = join(root, "deploy", "voice");
if (!existsSync(join(root, ".next", "standalone", "server.js"))) {
  console.error("Run `next build` first (npm run package:namecheap does both).");
  process.exit(1);
}

rmSync(join(root, "deploy"), { recursive: true, force: true });
mkdirSync(out, { recursive: true });

// The self-contained server, plus the files it serves.
cpSync(join(root, ".next", "standalone"), out, { recursive: true });
cpSync(join(root, ".next", "static"), join(out, ".next", "static"), { recursive: true });
cpSync(join(root, "public"), join(out, "public"), { recursive: true });

// Source the cron and admin scripts need (they use Node's built-in modules only).
cpSync(join(root, "lib"), join(out, "lib"), { recursive: true });
mkdirSync(join(out, "scripts"));
for (const f of ["scan-payments-once.ts", "add-credit.ts", "approve-deposit.ts"]) {
  cpSync(join(root, "scripts", f), join(out, "scripts", f));
}

cpSync(join(root, "deploy-files", "app.cjs"), join(out, "app.cjs"));
cpSync(join(root, ".env.example"), join(out, ".env.example"));
cpSync(join(root, "docs", "NAMECHEAP.md"), join(out, "NAMECHEAP.md"));

try {
  execSync("zip -qr ../voice.zip .", { cwd: out, stdio: "inherit" });
  console.log("Ready: deploy/voice.zip");
} catch {
  console.log("Ready: deploy/voice (zip this folder's contents to upload it)");
}
