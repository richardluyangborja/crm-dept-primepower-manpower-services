import { useEffect, useRef, useState } from 'react';
import { MailCheck, Timer, TimerOff } from 'lucide-react';
import api from '../../lib/apiClient';

interface VerifiedLogin {
  access_token: string;
  refresh_token: string;
  user: { id: number; name: string; email: string; role: string; team_id: number | null };
}
interface VerifiedStepUp {
  step_up_token: string;
}

type Status = 'sending' | 'sent' | 'verifying' | 'verified' | 'failed';

const RESEND_COOLDOWN = 60;

/**
 * 6-box OTP modal: auto-focus, paste support, expiry countdown, resend
 * cooldown, attempts-left + locked-out statuses. Login mode is public
 * (email+code); step-up mode sends/verifies under the current session.
 */
export function OtpModal({
  email,
  purpose,
  expiresIn = 300,
  sentTo = null,
  onResend,
  onVerified,
  onClose,
}: {
  email: string;
  purpose: 'login' | 'step_up';
  expiresIn?: number;
  sentTo?: string | null;
  onResend?: () => Promise<{ expiresIn?: number; sentTo?: string | null } | void>;
  onVerified: (payload: VerifiedLogin | VerifiedStepUp) => void;
  onClose: () => void;
}) {
  const [digits, setDigits] = useState<string[]>(['', '', '', '', '', '']);
  const [status, setStatus] = useState<Status>('sent');
  const [notice, setNotice] = useState(
    sentTo ? `Code sent to ${sentTo} — check your inbox (and spam).` : 'A 6-digit code is on its way to your email.',
  );
  const [err, setErr] = useState('');
  const [locked, setLocked] = useState(false);
  const [attemptsLeft, setAttemptsLeft] = useState<number | null>(null);
  const [left, setLeft] = useState(expiresIn);
  const [coolleft, setCoolleft] = useState(RESEND_COOLDOWN);
  const boxes = useRef<(HTMLInputElement | null)[]>([]);

  // Step-up: request the code on open (login codes arrive from the sign-in call).
  useEffect(() => {
    if (purpose !== 'step_up') return;
    setStatus('sending');
    api.post('/auth/otp/send', { purpose: 'step_up' }).then(
      (r) => {
        setStatus('sent');
        setNotice(`Code sent${r.data.data?.sent_to ? ` to ${r.data.data.sent_to}` : ''} — check your inbox (and spam).`);
        if (r.data.data?.expires_in) setLeft(r.data.data.expires_in);
      },
      () => {
        setStatus('failed');
        setErr('Could not send a code. Check the connection and resend.');
      },
    );
    // eslint-disable-next-line react-hooks/exhaustive-deps
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
    setStatus('sending');
    try {
      if (onResend) {
        const fresh = await onResend();
        if (fresh && typeof fresh === 'object') {
          if (fresh.expiresIn) setLeft(fresh.expiresIn);
          if (fresh.sentTo) setNotice(`Fresh code sent to ${fresh.sentTo} — the old one stops working.`);
          else setNotice('Fresh code sent — the old one stops working.');
        } else {
          setNotice('Fresh code sent — the old one stops working.');
        }
      } else if (purpose === 'step_up') {
        const r = await api.post('/auth/otp/send', { purpose: 'step_up' });
        if (r.data.data?.expires_in) setLeft(r.data.data.expires_in);
        setNotice('Fresh code sent — the old one stops working.');
      } else {
        // Login without a resend handler: minting needs the credentials again.
        setNotice('Sign in again to get a fresh code.');
        setStatus('sent');
        return;
      }
      setStatus('sent');
      setDigits(['', '', '', '', '', '']);
      setAttemptsLeft(null);
      setLocked(false);
      setCoolleft(RESEND_COOLDOWN);
      boxes.current[0]?.focus();
    } catch (e: unknown) {
      const resp = (e as { response?: { status?: number; data?: { message?: string; meta?: { retry_after?: number } } } }).response;
      const wait = resp?.data?.meta?.retry_after;
      if (resp?.status === 429 && wait) {
        setCoolleft(wait);
        setErr(`A code was just sent — resend available in ${wait}s.`);
      } else if (resp?.status === 429) {
        setLocked(true);
        setErr(resp.data?.message ?? 'Too many attempts — locked for 15 minutes.');
      } else {
        setErr('Could not resend yet — wait a moment and retry.');
      }
      setStatus('sent');
    }
  };

  const submit = async (ev: React.FormEvent) => {
    ev.preventDefault();
    const code = digits.join('');
    if (code.length !== 6) {
      setErr('Enter all 6 digits.');
      return;
    }
    setStatus('verifying');
    setErr('');
    try {
      const body = purpose === 'step_up' ? { code, purpose } : { email, code };
      const r = await api.post('/auth/otp/verify', body);
      setStatus('verified');
      onVerified(r.data.data);
    } catch (e: unknown) {
      const resp = (e as { response?: { status?: number; data?: { message?: string; meta?: { attempts_left?: number } } } }).response;
      setStatus('failed');
      if (resp?.status === 429) {
        setLocked(true);
        setErr(resp.data?.message ?? 'Too many attempts — locked for 15 minutes. Try again later.');
      } else if (resp?.status === 410) {
        const n = resp.data?.meta?.attempts_left;
        if (typeof n === 'number') {
          setAttemptsLeft(n);
          setErr(n > 0 ? `That code didn't match — ${n} ${n === 1 ? 'try' : 'tries'} left before lockout.` : 'No tries left — resend for a fresh code.');
        } else {
          setErr('Code expired or already used — resend for a fresh one.');
        }
        setDigits(['', '', '', '', '', '']);
        boxes.current[0]?.focus();
      } else {
        setErr('Could not verify — check the connection and retry.');
      }
    }
  };

  const mm = `${Math.floor(left / 60)}:${String(left % 60).padStart(2, '0')}`;
  const expired = left <= 0;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" role="dialog" aria-modal="true" aria-label="Verification code">
      <form onSubmit={submit} className="card w-full max-w-sm p-6">
        <h2 className="text-lg font-semibold">Enter verification code</h2>
        <p className="mb-1 flex items-start gap-1.5 text-xs text-[var(--text-muted)]">
          <MailCheck size={15} className="mt-0.5 shrink-0 text-sky-600" />
          <span>
            {purpose === 'step_up'
              ? 'A 6-digit code was sent for this sensitive action. It expires in 5 minutes and works once.'
              : `We sent a 6-digit code for ${email}. It expires in 5 minutes and works once.`}
          </span>
        </p>
        <p className="mb-3 flex items-center gap-1.5 font-mono text-sm tabular-nums" aria-live="polite">
          {!expired ? (
            <>
              <Timer size={15} className={left <= 60 ? 'text-red-600' : 'text-sky-600'} />
              <span className={left <= 60 ? 'font-semibold text-red-600' : ''}>{mm} left</span>
              {status === 'sending' && <span className="font-sans text-xs text-[var(--text-muted)]">· sending…</span>}
              {status === 'verifying' && <span className="font-sans text-xs text-[var(--text-muted)]">· verifying…</span>}
            </>
          ) : (
            <>
              <TimerOff size={15} className="text-[var(--text-muted)]" />
              <span className="font-sans text-xs text-[var(--text-muted)]">Code expired — resend for a fresh one.</span>
            </>
          )}
        </p>
        {!expired && notice && <p className="mb-2 text-xs text-sky-700 dark:text-sky-300">{notice}</p>}
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
              disabled={locked}
              className="h-12 w-11 rounded-lg border border-[var(--border)] bg-transparent text-center text-xl font-bold tabular-nums disabled:opacity-50"
            />
          ))}
        </div>
        {attemptsLeft !== null && !locked && (
          <p className="mt-1 text-xs text-amber-600">{attemptsLeft} {attemptsLeft === 1 ? 'try' : 'tries'} left before a 15-minute lockout.</p>
        )}
        {err && <p className="mt-2 text-sm text-red-600" role="alert">{err}</p>}
        <button disabled={status === 'verifying' || locked || expired} className="mt-4 w-full rounded-lg bg-sky-600 py-2 text-sm font-semibold text-white disabled:opacity-50">
          {status === 'verifying' ? 'Verifying…' : status === 'verified' ? 'Verified ✓' : 'Verify'}
        </button>
        <div className="mt-2 flex justify-between text-xs">
          <button type="button" disabled={coolleft > 0 || status === 'sending'} onClick={resend} className="text-sky-600 underline disabled:text-[var(--text-muted)] disabled:no-underline">
            {coolleft > 0 ? `Resend in ${coolleft}s` : 'Resend code'}
          </button>
          <button type="button" onClick={onClose} className="text-[var(--text-muted)] underline">Cancel</button>
        </div>
      </form>
    </div>
  );
}
