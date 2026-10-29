// CallMate Voice Changer for Windows: a small shell around the website's
// voice changer page. It adds what a browser tab can't: it keeps running in
// the tray, and a global hotkey toggles talking while you're in another app.
// Audio goes to the VB-Audio virtual cable, which other apps use as a mic.
const { app, BrowserWindow, Tray, Menu, nativeImage, globalShortcut, ipcMain, shell, session } = require("electron");
const path = require("node:path");
const config = require("./config.json");

const APP_URL = new URL(process.env.CALLMATE_URL || config.appUrl);
const START_URL = new URL("/changer", APP_URL).toString();
const HOTKEY = config.hotkey || "CommandOrControl+Shift+Space";

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
    return new URL(url).origin === APP_URL.origin;
  } catch {
    return false;
  }
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
  win.loadURL(START_URL);

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
