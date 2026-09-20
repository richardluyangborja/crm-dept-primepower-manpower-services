import { useState } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import api from '../lib/apiClient';
import { useSession } from '../store/session';
import { useToast } from '../components/ui/Toaster';

export function LoginPage() {
  const [email, setEmail] = useState('rep.juandelacruz@primepower.ph');
  const [password, setPassword] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const { setUser, setTheme } = useSession();
  const toast = useToast();
  const nav = useNavigate();
  const [params] = useSearchParams();

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    setBusy(true);
    setError('');
    try {
      const r = await api.post('/auth/login', { email, password });
      sessionStorage.setItem('crm.access', r.data.data.access_token);
      sessionStorage.setItem('crm.refresh', r.data.data.refresh_token);
      setUser(r.data.data.user);
      try {
        const prefs = (await api.get('/me/preferences')).data.data as { theme?: 'light' | 'dark' | 'system' };
        if (prefs?.theme) setTheme(prefs.theme);
      } catch {
        // preferences are best-effort at login
      }
      toast('success', 'Welcome back — logged in.');
      nav('/');
    } catch {
      setError('Invalid email or password. Try seeded demo: rep.juandelacruz@primepower.ph / PrimePower123!');
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="mx-auto mt-20 w-full max-w-md">
      <p className="text-sm font-bold text-red-600">PRIMEPOWER MANPOWER</p>
      <h1 className="text-2xl font-bold">CRM sign in</h1>
      <p className="mb-4 text-sm text-[var(--text-muted)]">Use your PrimePower account. OTP second factor arrives in v2 (specs/16).</p>
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
    </div>
  );
}
