// Startup file for cPanel "Setup Node.js App" (Phusion Passenger).
// Loads settings from .env next to this file, then starts the app.
const fs = require("node:fs");
const path = require("node:path");

const envFile = path.join(__dirname, ".env");
if (fs.existsSync(envFile)) process.loadEnvFile(envFile);
// Some hosts export the machine name as HOSTNAME, which the server would
// otherwise try to listen on.
process.env.HOSTNAME = process.env.LISTEN_HOST || "0.0.0.0";

import("./server.js").catch((err) => {
  console.error("Failed to start:", err);
  process.exit(1);
});
