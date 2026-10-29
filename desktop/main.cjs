// CallMate Voice Changer for Windows: a small shell around the website's
// voice changer page. It adds what a browser tab can't: it keeps running in
// the tray, and a global hotkey toggles talking while you're in another app.
// Audio goes to the VB-Audio virtual cable, which other apps use as a mic.
const { app, BrowserWindow, Tray, Menu, nativeImage, globalShortcut, ipcMain, shell, session } = require("electron");
const fs = require("node:fs");
const path = require("node:path");
const config = require("./config.json");

const HOTKEY = config.hotkey || "CommandOrControl+Shift+Space";
const settingsFile = () => path.join(app.getPath("userData"), "settings.json");

// The site address comes from CALLMATE_URL, then what the user entered on
// first run, then the address built into config.json.
function savedSiteUrl() {
  try {
    return JSON.parse(fs.readFileSync(settingsFile(), "utf8")).appUrl || "";
  } catch {
    return "";
  }
}

function normaliseSite(raw) {
  try {
    const url = new URL(/^https?:\/\//i.test(raw.trim()) ? raw.trim() : `https://${raw.trim()}`);
    if (url.protocol !== "https:" && url.hostname !== "localhost") return null;
    return url.origin;
  } catch {
    return null;
  }
}

let APP_URL = null;
function loadSiteUrl() {
  const raw = process.env.CALLMATE_URL || savedSiteUrl() || config.appUrl || "";
  APP_URL = raw ? new URL(raw) : null;
}

// Shown once when no site address is known yet.
const SETUP_PAGE = `data:text/html;charset=utf-8,${encodeURIComponent(`<!doctype html><html><head><meta charset="utf-8">
<style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#07070d;color:#f4f3fb;font:15px system-ui,sans-serif}
.card{width:min(360px,90vw);padding:28px;border-radius:20px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1)}
h1{font-size:20px;margin:0 0 6px}p{color:#9a98b3;margin:0 0 16px}input{width:100%;box-sizing:border-box;padding:12px 14px;border-radius:12px;border:1px solid rgba(255,255,255,.15);background:#000;color:#fff;font:inherit}
button{margin-top:12px;width:100%;padding:12px;border:0;border-radius:999px;color:#fff;font-weight:700;font:inherit;background:linear-gradient(135deg,#8b5cf6,#ec4899 55%,#f59e0b);cursor:pointer}#e{color:#fb7185;font-size:13px;margin-top:8px}</style></head>
<body><form class="card" id="f"><h1>Welcome</h1><p>Enter the website address you use for the voice changer.</p>
<input id="u" placeholder="yourdomain.com" autofocus><button>Continue</button><div id="e"></div></form>
<script>document.getElementById("f").onsubmit=async(ev)=>{ev.preventDefault();const r=await window.callmateDesktop.saveSite(document.getElementById("u").value);if(!r)document.getElementById("e").textContent="That doesn't look like a website address.";};</script>
</body></html>`)}`;

let win = null;
let tray = null;
let quitting = false;

if (!app.requestSingleInstanceLock()) {
  app.quit();
}

// A small gradient dot for the tray, drawn in code so the app needs no image files.
function trayIcon() {
  const size = 16;
  const buf = Buffer.alloc(size * size * 4);
  for (let y = 0; y < size; y++) {
    for (let x = 0; x < size; x++) {
      const d = Math.hypot(x - 7.5, y - 7.5);
      const i = (y * size + x) * 4;
      const t = x / size;
      buf[i] = Math.round(246 * (1 - t) + 72 * t); // B
      buf[i + 1] = Math.round(92 * (1 - t) + 72 * t); // G
      buf[i + 2] = Math.round(139 * (1 - t) + 236 * t); // R
      buf[i + 3] = d <= 7.5 ? 255 : 0; // A
    }
  }
  return nativeImage.createFromBitmap(buf, { width: size, height: size });
}

function isOurs(url) {
  try {
    return APP_URL !== null && new URL(url).origin === APP_URL.origin;
  } catch {
    return false;
  }
}

function openSite() {
  win.loadURL(APP_URL ? new URL("/changer", APP_URL).toString() : SETUP_PAGE);
}

function createWindow() {
  win = new BrowserWindow({
    width: 480,
    height: 860,
    minWidth: 380,
    backgroundColor: "#07070d",
    title: "CallMate Voice Changer",
    autoHideMenuBar: true,
    webPreferences: {
      preload: path.join(__dirname, "preload.cjs"),
      contextIsolation: true,
      sandbox: true,
      nodeIntegration: false,
      // Keep converting while the window is hidden in the tray.
      backgroundThrottling: false,
    },
  });
  openSite();

  // Links to other sites open in the normal browser.
  win.webContents.setWindowOpenHandler(({ url }) => {
    if (url.startsWith("https://")) shell.openExternal(url);
    return { action: "deny" };
  });
  win.webContents.on("will-navigate", (event, url) => {
    if (!isOurs(url)) {
      event.preventDefault();
      if (url.startsWith("https://")) shell.openExternal(url);
    }
  });

  // Closing hides to the tray so the voice keeps working; quit from the tray.
  win.on("close", (event) => {
    if (!quitting) {
      event.preventDefault();
      win.hide();
    }
  });
}

app.whenReady().then(() => {
  loadSiteUrl();
  // Only our own site may use the microphone or pick audio outputs.
  session.defaultSession.setPermissionRequestHandler((wc, permission, callback) => {
    callback(isOurs(wc.getURL()) && ["media", "speaker-selection"].includes(permission));
  });
  session.defaultSession.setPermissionCheckHandler((wc, permission, origin) => {
    return isOurs(origin) && ["media", "speaker-selection"].includes(permission);
  });

  createWindow();

  tray = new Tray(trayIcon());
  tray.setToolTip("CallMate Voice Changer");
  tray.setContextMenu(
    Menu.buildFromTemplate([
      { label: "Show", click: () => win.show() },
      {
        label: "Change website address…",
        click: () => {
          fs.rmSync(settingsFile(), { force: true });
          APP_URL = null;
          openSite();
          win.show();
        },
      },
      { label: `Talk on/off (${HOTKEY.replace("CommandOrControl", "Ctrl")})`, click: () => win.webContents.send("talk-toggle") },
      { type: "separator" },
      {
        label: "Quit",
        click: () => {
          quitting = true;
          app.quit();
        },
      },
    ]),
  );
  tray.on("click", () => (win.isVisible() ? win.focus() : win.show()));

  if (!globalShortcut.register(HOTKEY, () => win.webContents.send("talk-toggle"))) {
    console.warn(`Couldn't register the ${HOTKEY} hotkey; another app may be using it.`);
  }

  ipcMain.handle("save-site", (_event, raw) => {
    const origin = typeof raw === "string" ? normaliseSite(raw) : null;
    if (!origin) return false;
    fs.mkdirSync(path.dirname(settingsFile()), { recursive: true });
    fs.writeFileSync(settingsFile(), JSON.stringify({ appUrl: origin }));
    APP_URL = new URL(origin);
    openSite();
    return true;
  });

  ipcMain.handle("open-external", (_event, url) => {
    if (typeof url === "string" && url.startsWith("https://")) return shell.openExternal(url);
  });
});

app.on("second-instance", () => {
  if (win) {
    win.show();
    win.focus();
  }
});

app.on("before-quit", () => {
  quitting = true;
});

app.on("will-quit", () => globalShortcut.unregisterAll());
