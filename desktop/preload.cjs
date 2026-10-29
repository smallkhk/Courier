// The only bridge between the website and the desktop app: a talk-toggle
// event from the global hotkey, and opening links in the normal browser.
const { contextBridge, ipcRenderer } = require("electron");

contextBridge.exposeInMainWorld("callmateDesktop", {
  isDesktop: true,
  hotkey: "Ctrl+Shift+Space",
  onTalkToggle(callback) {
    const handler = () => callback();
    ipcRenderer.on("talk-toggle", handler);
    return () => ipcRenderer.removeListener("talk-toggle", handler);
  },
  openExternal(url) {
    return ipcRenderer.invoke("open-external", url);
  },
});
