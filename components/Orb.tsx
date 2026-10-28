"use client";

import { useEffect, useRef } from "react";

// The glowing voice orb. When getLevel is given it pulses with the audio.
export function Orb({ getLevel, idle, small }: { getLevel?: () => number; idle?: boolean; small?: boolean }) {
  const orb = useRef<HTMLDivElement>(null);
  useEffect(() => {
    if (!getLevel) return;
    let frame = 0;
    let smooth = 0;
    const tick = () => {
      const level = Math.min(1, Math.max(0, getLevel() || 0));
      smooth = smooth * 0.75 + level * 0.25;
      if (orb.current) orb.current.style.transform = `scale(${1 + smooth * 0.55})`;
      frame = requestAnimationFrame(tick);
    };
    frame = requestAnimationFrame(tick);
    return () => cancelAnimationFrame(frame);
  }, [getLevel]);

  return (
    <div className={`orb-wrap ${idle ? "idle" : ""} ${small ? "sm" : ""}`} aria-hidden>
      <span className="orb-ring" />
      <span className="orb-ring" />
      <span className="orb-ring" />
      <div className="orb" ref={orb} />
    </div>
  );
}
