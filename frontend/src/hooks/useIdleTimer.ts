import { useEffect, useRef, useState } from 'react';

export interface IdleState {
  warning: boolean;
  secondsLeft: number;
  stay: () => void;
}

/** Tracks user inactivity; warns at warnMs, fires onTimeout at idleMs (specs/16). */
export function useIdleTimer(opts: { idleMs?: number; warnMs?: number; onTimeout: () => void }): IdleState {
  const { idleMs = 300_000, warnMs = 240_000, onTimeout } = opts;
  const lastActive = useRef(Date.now());
  const [warning, setWarning] = useState(false);
  const [secondsLeft, setSecondsLeft] = useState(0);
  const timedOut = useRef(false);
  const cb = useRef(onTimeout);
  cb.current = onTimeout;

  useEffect(() => {
    const poke = () => {
      lastActive.current = Date.now();
      timedOut.current = false;
      setWarning(false);
    };
    const events = ['mousemove', 'keydown', 'scroll', 'touchstart', 'click'];
    events.forEach((e) => window.addEventListener(e, poke, { passive: true }));
    const tick = window.setInterval(() => {
      const elapsed = Date.now() - lastActive.current;
      if (elapsed >= idleMs) {
        if (!timedOut.current) {
          timedOut.current = true;
          cb.current();
        }
      } else if (elapsed >= warnMs) {
        setWarning(true);
        setSecondsLeft(Math.ceil((idleMs - elapsed) / 1000));
      } else {
        setWarning(false);
      }
    }, 1000);
    return () => {
      events.forEach((e) => window.removeEventListener(e, poke));
      window.clearInterval(tick);
    };
  }, [idleMs, warnMs]);

  return {
    warning,
    secondsLeft,
    stay: () => {
      lastActive.current = Date.now();
      timedOut.current = false;
      setWarning(false);
    },
  };
}
