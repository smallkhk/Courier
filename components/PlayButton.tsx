"use client";

import { useEffect, useState } from "react";
import { PauseIcon, PlayIcon } from "./Icons";

// One shared audio element, so starting a sample stops the previous one.
let shared: HTMLAudioElement | null = null;
const listeners = new Set<() => void>();

function notify() {
  listeners.forEach((fn) => fn());
}

export function PlayButton({ src }: { src: string }) {
  const [, force] = useState(0);
  useEffect(() => {
    const fn = () => force((n) => n + 1);
    listeners.add(fn);
    return () => {
      listeners.delete(fn);
    };
  }, []);
  const playing = shared !== null && shared.src === new URL(src, location.href).href && !shared.paused;

  function toggle() {
    if (playing) {
      shared!.pause();
    } else {
      shared?.pause();
      shared = new Audio(src);
      shared.onended = notify;
      shared.onpause = notify;
      void shared.play().then(notify);
    }
    notify();
  }

  return (
    <span className="row" style={{ gap: 10, flexWrap: "nowrap" }}>
      <button type="button" className="play-btn" onClick={toggle} aria-label={playing ? "Pause sample" : "Play sample"}>
        {playing ? <PauseIcon size={16} /> : <PlayIcon size={16} />}
      </button>
      <span className={`wave ${playing ? "" : "paused"}`} aria-hidden>
        <i />
        <i />
        <i />
        <i />
      </span>
    </span>
  );
}
