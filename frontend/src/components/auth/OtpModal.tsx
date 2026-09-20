import { useEffect, useRef, useState } from 'react';
import api from '../../lib/apiClient';

interface VerifiedLogin {
  access_token: string;
  refresh_token: string;
  user: { id: number; name: string; email: string; role: string; team_id: number | null };
}
interface VerifiedStepUp {
  step_up_token: string;
}

/**
 * 6-box OTP modal (specs/16): auto-focus, paste support, 05:00 countdown,
 * resend after 60s, lockout messaging. Login mode is public (email+code);
 * step-up mode sends/verifies under the current session.
 */
export function OtpModal({
  email,
  purpose,
  expiresIn = 300,
  onVerified,
  onClose,
}: {
  email: string;
  purpose: 'login' | 'step_up';
  expiresIn?: number;
  onVerified: (payload: VerifiedLogin | VerifiedStepUp) => void;
  onClose: () => void;
}) {
  const [digits, setDigits] = useState<string[]>(['', '', '', '', '', '']);
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState('');
  const [locked, setLocked] = useState(false);
  const [left, setLeft] = useState(expiresIn);
  const [coolleft, setCoolleft] = useState(60);
  const boxes = useRef<(HTMLInputElement | null)[]>([]);

  // Step-up: request the code on open (login codes arrive from the sign-in call).
  useEffect(() => {
    if (purpose !== 'step_up') return;
    api.post('/auth/otp/send', { purpose: 'step_up' }).catch(() => {
      setErr('Could not send a code. Please try again.');
    });
  }, [purpose]);

  useEffect(() => {
    boxes.current[0]?.focus();
  }, []);

  useEffect(() => {
    if (left <= 0) return;
    const t = window.setTimeout(() => setLeft((v) => v - 1), 1000);
    return () => window.clearTimeout(t);
  }, [left]);

  useEffect(() => {
    if (coolleft <= 0) return;
    const t = window.setTimeout(() => setCoolleft((v) => v - 1), 1000);
    return () => window.clearTimeout(t);
  }, [coolleft]);

  const set = (i: number, v: string) => {
    const d = v.replace(/\D/g, '').slice(-1);
    setDigits((prev) => {
      const next = [...prev];
      next[i] = d;
      return next;
    });
    if (d && i < 5) boxes.current[i + 1]?.focus();
  };

  const onKey = (i: number, e: React.KeyboardEvent) => {
    if (e.key === 'Backspace' && !digits[i] && i > 0) boxes.current[i - 1]?.focus();
  };

  const onPaste = (e: React.ClipboardEvent) => {
    const nums = e.clipboardData.getData('text').replace(/\D/g, '').slice(0, 6).split('');
    if (nums.length === 0) return;
    e.preventDefault();
    setDigits((prev) => prev.map((_, i) => nums[i] ?? ''));
    boxes.current[Math.min(nums.length, 5)]?.focus();
  };

  const resend = async () => {
    setErr('');
    try {
      if (purpose === 'step_up') {
        await api.post('/auth/otp/send', { purpose: 'step_up' });
      } else {
        // Login resend: re-run sign-in to mint a fresh challenge.
        setErr('Go back and sign in again to get a fresh code.');
        return;
      }
      setLeft(expiresIn);
      setCoolleft(60);
    } catch {
      setErr('Could not resend yet — wait a moment and retry.');
    }
  };

  const submit = async (ev: React.FormEvent) => {
    ev.preventDefault();
    const code = digits.join('');
    if (code.length !== 6) {
      setErr('Enter all 6 digits.');
      return;
    }
    setBusy(true);
    setErr('');
    try {
      const body = purpose === 'step_up' ? { code, purpose } : { email, code };
      const r = await api.post('/auth/otp/verify', body);
      onVerified(r.data.data);
    } catch (e: unknown) {
      const status = (e as { response?: { status?: number } })?.response?.status;
      if (status === 429) {
        setLocked(true);
        setErr('Too many attempts — locked for 15 minutes. Try again later.');
      } else {
        setErr('Invalid or expired code. Check and retry.');
      }
    } finally {
      setBusy(false);
    }
  };

  const mm = `${Math.floor(left / 60)}:${String(left % 60).padStart(2, '0')}`;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" role="dialog" aria-modal="true" aria-label="Verification code">
      <form onSubmit={submit} className="card w-full max-w-sm p-6">
        <h2 className="text-lg font-semibold">Enter verification code</h2>
        <p className="mb-1 text-xs text-[var(--text-muted)]">
          {purpose === 'step_up'
            ? 'A 6-digit code was sent for this sensitive action. It expires in 5 minutes and works once.'
            : `We sent a 6-digit code for ${email}. It expires in 5 minutes.`}
        </p>
        <p className="mb-3 font-mono text-sm tabular-nums" aria-live="polite">
          {left > 0 ? `⏳ ${mm} left` : '⌛ Code expired — resend or sign in again.'}
        </p>
        <div className="flex justify-between gap-1.5" onPaste={onPaste}>
          {digits.map((d, i) => (
            <input
              key={i}
              ref={(el) => {
                boxes.current[i] = el;
              }}
              value={d}
              onChange={(e) => set(i, e.target.value)}
              onKeyDown={(e) => onKey(i, e)}
              inputMode="numeric"
              maxLength={1}
              aria-label={`Digit ${i + 1}`}
              className="h-12 w-11 rounded-lg border border-[var(--border)] bg-transparent text-center text-xl font-bold tabular-nums"
            />
          ))}
        </div>
        {err && <p className="mt-2 text-sm text-red-600">{err}</p>}
        <button disabled={busy || locked || left <= 0} className="mt-4 w-full rounded-lg bg-sky-600 py-2 text-sm font-semibold text-white disabled:opacity-50">
          {busy ? 'Verifying…' : 'Verify'}
        </button>
        <div className="mt-2 flex justify-between text-xs">
          <button type="button" disabled={coolleft > 0} onClick={resend} className="text-sky-600 underline disabled:text-[var(--text-muted)] disabled:no-underline">
            {coolleft > 0 ? `Resend in ${coolleft}s` : 'Resend code'}
          </button>
          <button type="button" onClick={onClose} className="text-[var(--text-muted)] underline">Cancel</button>
        </div>
      </form>
    </div>
  );
}
