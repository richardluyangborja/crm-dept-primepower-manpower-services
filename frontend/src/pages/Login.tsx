import { useState } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import api from '../lib/apiClient';
import { useSession, type Role } from '../store/session';
import { useToast } from '../components/ui/Toaster';
import { OtpModal } from '../components/auth/OtpModal';

interface LoginTokens {
  access_token: string;
  refresh_token: string;
  user: { id: number; name: string; email: string; role: Role; team_id: number | null };
}

export function LoginPage() {
  const [email, setEmail] = useState('rep.juandelacruz@primepower.ph');
  const [password, setPassword] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [otp, setOtp] = useState<{ challenge: number; expires: number } | null>(null);
  const { setUser, setTheme } = useSession();
  const toast = useToast();
  const nav = useNavigate();
  const [params] = useSearchParams();

  const completeLogin = async (data: LoginTokens) => {
    sessionStorage.setItem('crm.access', data.access_token);
    sessionStorage.setItem('crm.refresh', data.refresh_token);
    setUser(data.user);
    try {
      const prefs = (await api.get('/me/preferences')).data.data as { theme?: 'light' | 'dark' | 'system' };
      if (prefs?.theme) setTheme(prefs.theme);
    } catch {
      // preferences are best-effort at login
    }
    toast('success', 'Welcome back — logged in.');
    nav('/');
  };

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    setBusy(true);
    setError('');
    try {
      const r = await api.post('/auth/login', { email, password });
      if (r.data.data.otp_required) {
        // Second factor (specs/16): admins, superadmins, and opted-in users.
        setOtp({ challenge: r.data.data.challenge_id, expires: r.data.data.expires_in ?? 300 });
        return;
      }
      await completeLogin(r.data.data);
    } catch (e: unknown) {
      const status = (e as { response?: { status?: number } })?.response?.status;
      if (status === 429) {
        setError('Too many attempts — locked for 15 minutes. Try again later.');
      } else {
        setError('Invalid email or password. Try seeded demo: rep.juandelacruz@primepower.ph / PrimePower123!');
      }
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="mx-auto mt-20 w-full max-w-md">
      <p className="text-sm font-bold text-red-600">PRIMEPOWER MANPOWER</p>
      <h1 className="text-2xl font-bold">CRM sign in</h1>
      <p className="mb-4 text-sm text-[var(--text-muted)]">Use your PrimePower account. Admins and opted-in users verify a 6-digit code next.</p>
      {params.get('expired') && <p className="card mb-3 border-l-4 border-l-amber-500 p-3 text-sm">Session expired after inactivity — please log in again.</p>}
      <form onSubmit={submit} className="card flex flex-col gap-3 p-6">
        <label className="text-sm">
          Work email
          <input value={email} onChange={(e) => setEmail(e.target.value)} type="email" required className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2" />
        </label>
        <label className="text-sm">
          Password
          <input value={password} onChange={(e) => setPassword(e.target.value)} type="password" required minLength={8} className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2" />
        </label>
        {error && <p className="text-sm text-red-600">{error}</p>}
        <button disabled={busy} className="rounded-lg bg-sky-600 py-2 text-sm font-semibold text-white disabled:opacity-50">
          {busy ? 'Signing in…' : 'Sign in'}
        </button>
      </form>
      {otp && (
        <OtpModal
          email={email}
          purpose="login"
          expiresIn={otp.expires}
          onVerified={(tokens) => completeLogin(tokens as LoginTokens)}
          onClose={() => setOtp(null)}
        />
      )}
    </div>
  );
}
