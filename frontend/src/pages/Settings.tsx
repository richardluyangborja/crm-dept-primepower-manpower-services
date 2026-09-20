import { useState } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import api from '../lib/apiClient';
import { DataTable } from '../components/ui/DataTable';
import { EmptyState } from '../components/ui/EmptyState';
import { StatusBadge } from '../components/ui/StatusBadge';
import { ConfirmDialog } from '../components/ui/ConfirmDialog';
import { useToast } from '../components/ui/Toaster';
import { hasRole, useSession, type Role } from '../store/session';

type Section = 'general' | 'appearance' | 'organization' | 'notifications' | 'users' | 'security' | 'integrations' | 'data' | 'reports';

function apiErr(e: unknown, fallback: string): string {
  if (typeof e === 'object' && e !== null && 'response' in e) {
    const r = (e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }).response;
    if (r?.data?.errors) return Object.values(r.data.errors).flat().join(' ');
    if (r?.data?.message) return r.data.message;
  }
  return fallback;
}

const SECTIONS: { key: Section; label: string; roles?: Role[] }[] = [
  { key: 'general', label: 'General' },
  { key: 'appearance', label: 'Appearance' },
  { key: 'organization', label: 'Organization', roles: ['superadmin', 'admin'] },
  { key: 'notifications', label: 'Notifications' },
  { key: 'users', label: 'Users & Access', roles: ['superadmin', 'admin', 'manager'] },
  { key: 'security', label: 'Security' },
  { key: 'integrations', label: 'Integrations', roles: ['superadmin', 'admin'] },
  { key: 'data', label: 'Data & Backup', roles: ['superadmin'] },
  { key: 'reports', label: 'AI & Reports', roles: ['superadmin', 'admin', 'manager'] },
];

export function SettingsPage() {
  const { user } = useSession();
  const [section, setSection] = useState<Section>('general');
  const visible = SECTIONS.filter((s) => !s.roles || hasRole(user, ...s.roles));

  return (
    <div className="flex flex-col gap-4">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-xl font-bold">Settings</h1>
          <p className="text-sm text-[var(--text-muted)]">Configure system preferences and organization settings.</p>
        </div>
      </div>
      <div className="grid gap-4 md:grid-cols-[220px_1fr]">
        <nav className="card flex flex-row gap-1 overflow-x-auto p-2 md:flex-col" aria-label="Settings sections">
          {visible.map((s) => (
            <button key={s.key} onClick={() => setSection(s.key)}
              className={`whitespace-nowrap rounded-lg px-3 py-2 text-left text-sm ${section === s.key ? 'bg-sky-100 font-medium text-sky-900 dark:bg-sky-900/40 dark:text-sky-100' : 'hover:bg-slate-100 dark:hover:bg-slate-800'}`}>
              {s.label}
            </button>
          ))}
        </nav>
        <div className="min-w-0">
          {section === 'general' && <GeneralSection />}
          {section === 'appearance' && <AppearanceSection />}
          {section === 'organization' && <OrganizationSection />}
          {section === 'notifications' && <NotificationsSection />}
          {section === 'users' && <UsersSection />}
          {section === 'security' && <SecuritySection />}
          {section === 'integrations' && <IntegrationsSection />}
          {section === 'data' && <DataSection />}
          {section === 'reports' && <ReportsPlaceholder />}
        </div>
      </div>
    </div>
  );
}

/* ---------------------------------- General --------------------------------- */

function GeneralSection() {
  const { user } = useSession();
  const toast = useToast();
  const qc = useQueryClient();
  const settingsQ = useQuery({
    queryKey: ['settings'],
    queryFn: async () => (await api.get('/settings')).data.data as Record<string, unknown>,
  });
  const [form, setForm] = useState<Record<string, string> | null>(null);
  const data = form ?? (settingsQ.data as Record<string, string> | undefined);
  const canEdit = hasRole(user, 'superadmin');
  const set = (k: string) => (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) =>
    setForm({ ...(data ?? {}), [k]: e.target.value });

  const save = async () => {
    try {
      await api.put('/settings', { settings: data });
      toast('success', 'Settings saved.');
      setForm(null);
      qc.invalidateQueries({ queryKey: ['settings'] });
    } catch (e) {
      toast('error', apiErr(e, 'Could not save settings.'));
    }
  };

  if (settingsQ.isLoading) return <p className="text-sm text-[var(--text-muted)]">Loading…</p>;
  const fields: [string, string][] = [
    ['org_name', 'Organization name'],
    ['timezone', 'Timezone'],
    ['currency', 'Currency symbol'],
    ['date_format', 'Date format'],
    ['language', 'Language'],
  ];

  return (
    <div className="card p-6">
      <h2 className="font-semibold">General</h2>
      <p className="mb-3 text-xs text-[var(--text-muted)]">Organization identity and locale. {canEdit ? '' : 'Read-only — superadmin only.'}</p>
      <div className="grid gap-3 sm:grid-cols-2">
        {fields.map(([k, label]) => (
          <label key={k} className="text-sm">{label}
            <input value={String(data?.[k] ?? '')} onChange={set(k)} disabled={!canEdit} className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2 disabled:opacity-60" />
          </label>
        ))}
      </div>
      {canEdit && <div className="mt-4 flex justify-end"><button onClick={save} className="rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white">💾 Save Changes</button></div>}
    </div>
  );
}

/* --------------------------------- Appearance -------------------------------- */

function AppearanceSection() {
  const { theme, setTheme } = useSession();
  const toast = useToast();
  const [sync, setSync] = useState(true);

  const pick = async (t: 'light' | 'dark' | 'system') => {
    setTheme(t);
    try {
      await api.put('/me/preferences', { theme: t, sync_system: t === 'system' ? sync : false });
      toast('success', `Appearance saved (${t}).`);
    } catch {
      toast('info', 'Applied on this device — sign-in sync needs the backend.');
    }
  };

  const Card = ({ mode, label, dark }: { mode: 'light' | 'dark'; label: string; dark?: boolean }) => (
    <button onClick={() => pick(mode)} className={`relative rounded-xl border-2 p-2 text-left ${theme === mode ? 'border-sky-500' : 'border-[var(--border)]'}`}>
      <span className={`block h-24 rounded-lg ${dark ? 'bg-[#0B1526]' : 'bg-white border border-slate-200'}`}>
        <span className={`mx-2 mt-2 block h-3 w-2/3 rounded ${dark ? 'bg-slate-700' : 'bg-slate-200'}`} />
        <span className="mx-2 mt-1 flex gap-1">
          <span className={`h-6 flex-1 rounded ${dark ? 'bg-slate-800' : 'bg-slate-100'}`} />
          <span className={`h-6 flex-1 rounded ${dark ? 'bg-slate-800' : 'bg-slate-100'}`} />
        </span>
      </span>
      <span className="mt-1 block text-center text-sm">{label}</span>
      {theme === mode && <span className="absolute right-2 top-2 flex h-5 w-5 items-center justify-center rounded-full bg-sky-500 text-xs text-white">✓</span>}
    </button>
  );

  return (
    <div className="card p-6">
      <h2 className="font-semibold">Appearance</h2>
      <p className="mb-3 text-xs text-[var(--text-muted)]">Choose how the CRM looks on your device. Saved to your profile.</p>
      <div className="grid max-w-lg grid-cols-2 gap-3">
        <Card mode="light" label="Light" />
        <Card mode="dark" label="Dark" dark />
      </div>
      <label className="mt-4 flex max-w-lg items-center justify-between gap-3 text-sm">
        <span><span className="font-medium">Sync with System</span><br /><span className="text-xs text-[var(--text-muted)]">Automatically match your operating system's theme (uses “system”).</span></span>
        <input type="checkbox" checked={theme === 'system' ? sync : false} onChange={(e) => { setSync(e.target.checked); if (e.target.checked) pick('system'); }} className="h-5 w-5" />
      </label>
      {theme !== 'system' && sync === false && null}
    </div>
  );
}

/* -------------------------------- Organization ------------------------------- */

interface Team {
  id: number;
  name: string;
  region: string | null;
  users_count?: number;
}

function OrganizationSection() {
  const toast = useToast();
  const qc = useQueryClient();
  const [name, setName] = useState('');
  const [region, setRegion] = useState('');
  const teamsQ = useQuery({
    queryKey: ['teams'],
    queryFn: async () => (await api.get('/teams')).data.data as Team[],
  });

  const create = async (ev: React.FormEvent) => {
    ev.preventDefault();
    try {
      await api.post('/teams', { name: name.trim(), region: region.trim() || undefined });
      toast('success', 'Team created.');
      setName('');
      setRegion('');
      qc.invalidateQueries({ queryKey: ['teams'] });
    } catch (e) {
      toast('error', apiErr(e, 'Could not create team.'));
    }
  };

  return (
    <div className="flex flex-col gap-4">
      <div className="card p-6">
        <h2 className="font-semibold">Teams</h2>
        <p className="mb-3 text-xs text-[var(--text-muted)]">Sales territories. Reps and managers must belong to one.</p>
        <DataTable<Team>
          rows={teamsQ.data ?? []}
          columns={[
            { key: 'n', header: 'Team', render: (r) => <span className="font-medium">{r.name}</span> },
            { key: 'r', header: 'Region', render: (r) => r.region ?? '—' },
            { key: 'u', header: 'Members', render: (r) => String(r.users_count ?? '—') },
          ]}
          empty={teamsQ.isLoading ? <p className="text-sm">Loading…</p> : <EmptyState title="No teams" hint="Create the first sales territory." />}
        />
        <form onSubmit={create} className="mt-3 flex flex-wrap gap-2">
          <input value={name} onChange={(e) => setName(e.target.value)} required placeholder="Team name (e.g. Davao)" className="rounded-lg border border-[var(--border)] bg-transparent px-3 py-2 text-sm" />
          <input value={region} onChange={(e) => setRegion(e.target.value)} placeholder="Region" className="rounded-lg border border-[var(--border)] bg-transparent px-3 py-2 text-sm" />
          <button className="rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white">Add team</button>
        </form>
      </div>
      <div className="card p-6">
        <h2 className="font-semibold">Master data</h2>
        <p className="text-xs text-[var(--text-muted)]">Industries, sources, pipeline stages and lost reasons are managed here in a later iteration — stage labels and lost reasons currently follow specs/05. Industry list seeds from Settings (BPO, Manufacturing, Hospitality, Retail, Healthcare, Logistics).</p>
      </div>
    </div>
  );
}

/* -------------------------------- Notifications ------------------------------ */

const NOTIF_KEYS = [
  ['reminder_due', 'Reminder due soon'],
  ['overdue', 'Overdue follow-ups'],
  ['escalation', 'Escalations'],
  ['survey_response', 'Survey responses'],
  ['assignment', 'New assignments'],
] as const;

function NotificationsSection() {
  const toast = useToast();
  const qc = useQueryClient();
  const prefsQ = useQuery({
    queryKey: ['me-preferences'],
    queryFn: async () => (await api.get('/me/preferences')).data.data as Record<string, unknown>,
  });
  const [draft, setDraft] = useState<Record<string, unknown> | null>(null);
  const data = draft ?? prefsQ.data;
  const notifs = (data?.notifications as Record<string, boolean> | undefined) ?? {};

  const toggle = (k: string) => setDraft({ ...(data ?? {}), notifications: { ...notifs, [k]: !notifs[k] } });
  const save = async () => {
    try {
      await api.put('/me/preferences', { notifications: (draft?.notifications ?? notifs) });
      toast('success', 'Notification preferences saved.');
      setDraft(null);
      qc.invalidateQueries({ queryKey: ['me-preferences'] });
    } catch (e) {
      toast('error', apiErr(e, 'Could not save.'));
    }
  };

  if (prefsQ.isLoading) return <p className="text-sm text-[var(--text-muted)]">Loading…</p>;
  return (
    <div className="card p-6">
      <h2 className="font-semibold">Notifications</h2>
      <p className="mb-3 text-xs text-[var(--text-muted)]">In-app toggles. Browser push and quiet hours (PHT) arrive with later iterations.</p>
      <div className="flex flex-col gap-2">
        {NOTIF_KEYS.map(([k, label]) => (
          <label key={k} className="flex cursor-pointer items-center justify-between rounded-lg border border-[var(--border)] px-3 py-2 text-sm">
            {label}
            <input type="checkbox" checked={notifs[k] !== false} onChange={() => toggle(k)} className="h-5 w-5" />
          </label>
        ))}
      </div>
      <div className="mt-4 flex justify-end"><button onClick={save} className="rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white">💾 Save Changes</button></div>
    </div>
  );
}

/* -------------------------------- Users & Access ----------------------------- */

interface U {
  id: number;
  name: string;
  email: string;
  role: string;
  team_id: number | null;
  team_name?: string;
  phone: string | null;
  is_active: boolean;
  last_login_at: string | null;
}

function UsersSection() {
  const { user } = useSession();
  const toast = useToast();
  const qc = useQueryClient();
  const [q, setQ] = useState('');
  const [showInvite, setShowInvite] = useState(false);
  const [deactivateId, setDeactivateId] = useState<number | null>(null);
  const [resetId, setResetId] = useState<number | null>(null);
  const [roleEdit, setRoleEdit] = useState<U | null>(null);
  const readonly = hasRole(user, 'manager') && !hasRole(user, 'admin');

  const usersQ = useQuery({
    queryKey: ['users', q],
    queryFn: async () => (await api.get('/users', { params: { q: q || undefined, per_page: 50 } })).data.data as U[],
  });
  const invalidate = () => qc.invalidateQueries({ queryKey: ['users'] });

  const doDeactivate = async () => {
    if (!deactivateId) return;
    try {
      await api.post(`/users/${deactivateId}/deactivate`);
      toast('success', 'Account deactivated.');
      invalidate();
    } catch (e) {
      toast('error', apiErr(e, 'Could not deactivate.'));
    } finally {
      setDeactivateId(null);
    }
  };

  return (
    <>
      <div className="flex gap-2">
        <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Search name or email…" className="card flex-1 px-3 py-2 text-sm outline-none" />
        {!readonly && <button onClick={() => setShowInvite(true)} className="rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white">+ Invite user</button>}
      </div>
      {readonly && <p className="card border-l-4 border-l-sky-500 p-3 text-xs">Team view is read-only for managers — user changes need an admin.</p>}
      {usersQ.isLoading ? <p className="text-sm text-[var(--text-muted)]">Loading users…</p> : (
        <DataTable<U>
          rows={usersQ.data ?? []}
          columns={[
            { key: 'n', header: 'Name', render: (r) => <span className="font-medium">{r.name}<br /><span className="text-xs font-normal text-[var(--text-muted)]">{r.email}</span></span> },
            { key: 'r', header: 'Role', render: (r) => <StatusBadge value={r.role} /> },
            { key: 't', header: 'Team', render: (r) => r.team_name ?? '—' },
            { key: 'a', header: 'Active', render: (r) => (r.is_active ? 'Yes' : 'No') },
            {
              key: 'x', header: 'Actions', render: (r) => readonly ? <span className="text-xs text-[var(--text-muted)]">—</span> : (
                <span className="flex flex-wrap gap-1">
                  <button onClick={() => setRoleEdit(r)} className="rounded border border-[var(--border)] px-2 py-0.5 text-xs">Role/team</button>
                  <button onClick={() => setResetId(r.id)} className="rounded border border-[var(--border)] px-2 py-0.5 text-xs">Reset pw</button>
                  {r.is_active && <button onClick={() => setDeactivateId(r.id)} className="rounded border border-red-300 px-2 py-0.5 text-xs text-red-600">Deactivate</button>}
                </span>
              ),
            },
          ]}
          empty={<EmptyState title="No users found" hint="Try a different search, or invite the first teammate." />}
        />
      )}
      {showInvite && <InviteForm onClose={() => setShowInvite(false)} onDone={invalidate} />}
      {roleEdit && <RoleForm user={roleEdit} onClose={() => setRoleEdit(null)} onDone={invalidate} />}
      {resetId !== null && <ResetForm userId={resetId} onClose={() => setResetId(null)} />}
      <ConfirmDialog open={deactivateId !== null} title="Deactivate this account?" body="They will be signed out and cannot log in until reactivated by an admin." onCancel={() => setDeactivateId(null)} onConfirm={doDeactivate} />
    </>
  );
}

function InviteForm({ onClose, onDone }: { onClose: () => void; onDone: () => void }) {
  const toast = useToast();
  const [f, setF] = useState({ name: '', email: '', password: '', role: 'sales_rep', team_id: '', phone: '' });
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState('');
  const teamsQ = useQuery({ queryKey: ['teams'], queryFn: async () => (await api.get('/teams')).data.data as Team[] });
  const set = (k: keyof typeof f) => (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => setF({ ...f, [k]: e.target.value });

  const submit = async (ev: React.FormEvent) => {
    ev.preventDefault();
    setBusy(true);
    setErr('');
    try {
      await api.post('/users', { ...f, team_id: f.team_id ? Number(f.team_id) : undefined, phone: f.phone || undefined });
      toast('success', 'Account created — share the temporary password securely.');
      onDone();
      onClose();
    } catch (e) {
      setErr(apiErr(e, 'Could not create account.'));
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" role="dialog" aria-modal="true">
      <form onSubmit={submit} className="card w-full max-w-md p-6">
        <h2 className="text-lg font-semibold">Invite user</h2>
        <div className="mt-2 flex flex-col gap-2 text-sm">
          <label>Name *<input required value={f.name} onChange={set('name')} className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2" /></label>
          <label>Work email *<input required type="email" value={f.email} onChange={set('email')} className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2" /></label>
          <label>Temporary password (10+ chars) *<input required minLength={10} type="text" value={f.password} onChange={set('password')} className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2" /></label>
          <div className="flex gap-2">
            <label className="flex-1">Role<select value={f.role} onChange={set('role')} className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2">
              {['sales_rep', 'manager', 'admin', 'superadmin'].map((r) => <option key={r} value={r}>{r}</option>)}
            </select></label>
            <label className="flex-1">Team<select value={f.team_id} onChange={set('team_id')} className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2">
              <option value="">—</option>
              {teamsQ.data?.map((t) => <option key={t.id} value={t.id}>{t.name}</option>)}
            </select></label>
          </div>
          <label>Mobile (optional)<input value={f.phone} onChange={set('phone')} placeholder="+639XXXXXXXXX" className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2" /></label>
        </div>
        {err && <p className="mt-2 text-sm text-red-600">{err}</p>}
        <div className="mt-4 flex justify-end gap-2">
          <button type="button" onClick={onClose} className="rounded-lg border border-[var(--border)] px-4 py-2 text-sm">Cancel</button>
          <button disabled={busy} className="rounded-lg bg-sky-600 px-4 py-2 text-sm text-white disabled:opacity-50">{busy ? 'Creating…' : 'Create account'}</button>
        </div>
      </form>
    </div>
  );
}

function RoleForm({ user, onClose, onDone }: { user: U; onClose: () => void; onDone: () => void }) {
  const toast = useToast();
  const [role, setRole] = useState(user.role);
  const [teamId, setTeamId] = useState(user.team_id ? String(user.team_id) : '');
  const [busy, setBusy] = useState(false);
  const teamsQ = useQuery({ queryKey: ['teams'], queryFn: async () => (await api.get('/teams')).data.data as Team[] });

  const submit = async (ev: React.FormEvent) => {
    ev.preventDefault();
    setBusy(true);
    try {
      await api.put(`/users/${user.id}`, { role, team_id: teamId ? Number(teamId) : null });
      toast('success', 'Account updated.');
      onDone();
      onClose();
    } catch (e) {
      toast('error', apiErr(e, 'Could not update (last superadmin is protected).'));
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" role="dialog" aria-modal="true">
      <form onSubmit={submit} className="card w-full max-w-sm p-6">
        <h2 className="text-lg font-semibold">Role & team — {user.name}</h2>
        <div className="mt-2 flex flex-col gap-2 text-sm">
          <label>Role<select value={role} onChange={(e) => setRole(e.target.value)} className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2">
            {['sales_rep', 'manager', 'admin', 'superadmin'].map((r) => <option key={r} value={r}>{r}</option>)}
          </select></label>
          <label>Team<select value={teamId} onChange={(e) => setTeamId(e.target.value)} className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2">
            <option value="">—</option>
            {teamsQ.data?.map((t) => <option key={t.id} value={t.id}>{t.name}</option>)}
          </select></label>
        </div>
        <div className="mt-4 flex justify-end gap-2">
          <button type="button" onClick={onClose} className="rounded-lg border border-[var(--border)] px-4 py-2 text-sm">Cancel</button>
          <button disabled={busy} className="rounded-lg bg-sky-600 px-4 py-2 text-sm text-white disabled:opacity-50">Save</button>
        </div>
      </form>
    </div>
  );
}

function ResetForm({ userId, onClose }: { userId: number; onClose: () => void }) {
  const toast = useToast();
  const [pw, setPw] = useState('');
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState('');

  const submit = async (ev: React.FormEvent) => {
    ev.preventDefault();
    setBusy(true);
    setErr('');
    try {
      await api.post(`/users/${userId}/reset-password`, { password: pw });
      toast('success', 'Password reset — share the new temporary password securely.');
      onClose();
    } catch (e) {
      setErr(apiErr(e, 'Could not reset password.'));
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" role="dialog" aria-modal="true">
      <form onSubmit={submit} className="card w-full max-w-sm p-6">
        <h2 className="text-lg font-semibold">Reset password</h2>
        <label className="mt-2 block text-sm">New temporary password (10+ chars)
          <input type="text" required minLength={10} value={pw} onChange={(e) => setPw(e.target.value)} className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2" />
        </label>
        {err && <p className="mt-2 text-sm text-red-600">{err}</p>}
        <div className="mt-4 flex justify-end gap-2">
          <button type="button" onClick={onClose} className="rounded-lg border border-[var(--border)] px-4 py-2 text-sm">Cancel</button>
          <button disabled={busy} className="rounded-lg bg-sky-600 px-4 py-2 text-sm text-white disabled:opacity-50">Reset</button>
        </div>
      </form>
    </div>
  );
}

/* ---------------------------------- Security --------------------------------- */

interface SessionRow {
  id: number;
  user_id: number;
  ip: string | null;
  user_agent: string | null;
  last_activity_at: string;
  expired_at: string | null;
  created_at: string;
}
interface LoginRow {
  id: number;
  user_id: number;
  meta: { ip?: string } | null;
  created_at: string;
}

function SecuritySection() {
  const toast = useToast();
  const qc = useQueryClient();
  const [cur, setCur] = useState('');
  const [pw, setPw] = useState('');
  const [pw2, setPw2] = useState('');

  const sessionsQ = useQuery({
    queryKey: ['my-sessions'],
    queryFn: async () => (await api.get('/users-sessions')).data.data as SessionRow[],
  });
  const loginsQ = useQuery({
    queryKey: ['my-logins'],
    queryFn: async () => (await api.get('/me/logins')).data.data as LoginRow[],
  });

  const changePw = async (ev: React.FormEvent) => {
    ev.preventDefault();
    try {
      await api.post('/me/password', { current_password: cur, password: pw, password_confirmation: pw2 });
      toast('success', 'Password changed.');
      setCur('');
      setPw('');
      setPw2('');
    } catch (e) {
      toast('error', apiErr(e, 'Could not change password.'));
    }
  };

  const revoke = async (id: number) => {
    try {
      await api.delete(`/users-sessions/${id}`);
      toast('success', 'Session revoked.');
      qc.invalidateQueries({ queryKey: ['my-sessions'] });
    } catch (e) {
      toast('error', apiErr(e, 'Could not revoke session.'));
    }
  };

  return (
    <div className="flex flex-col gap-4">
      <form onSubmit={changePw} className="card p-6">
        <h2 className="font-semibold">Change password</h2>
        <div className="mt-2 grid gap-2 text-sm sm:grid-cols-3">
          <label>Current<input type="password" required value={cur} onChange={(e) => setCur(e.target.value)} className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2" /></label>
          <label>New (10+ chars)<input type="password" required minLength={10} value={pw} onChange={(e) => setPw(e.target.value)} className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2" /></label>
          <label>Confirm<input type="password" required value={pw2} onChange={(e) => setPw2(e.target.value)} className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2" /></label>
        </div>
        <div className="mt-3 flex justify-end"><button className="rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white">💾 Save Changes</button></div>
      </form>

      <div className="card p-6">
        <h2 className="font-semibold">Active sessions</h2>
        <p className="mb-2 text-xs text-[var(--text-muted)]">Devices currently signed in as you. Revoking signs that device out on next request.</p>
        <DataTable<SessionRow>
          rows={sessionsQ.data ?? []}
          columns={[
            { key: 'i', header: 'IP', render: (r) => r.ip ?? '—' },
            { key: 'u', header: 'Device', render: (r) => <span className="max-w-56 truncate text-xs">{r.user_agent ?? '—'}</span> },
            { key: 'l', header: 'Last activity', render: (r) => new Date(r.last_activity_at).toLocaleString('en-PH') },
            { key: 'x', header: '', render: (r) => <button onClick={() => revoke(r.id)} className="rounded border border-[var(--border)] px-2 py-0.5 text-xs">Revoke</button> },
          ]}
          empty={<EmptyState title="No active sessions" hint="Sign-ins appear here." />}
        />
      </div>

      <div className="card p-6">
        <h2 className="font-semibold">Two-factor (OTP)</h2>
        <p className="text-xs text-[var(--text-muted)]">One-time codes (6 digits, 5-minute expiry) and the 5-minute idle timeout are specified and scaffolded, enforcement lands in Step 8 (specs/16) — after the complexity-heavy auth work. Nothing to configure yet.</p>
      </div>

      <div className="card p-6">
        <h2 className="font-semibold">Login history</h2>
        <DataTable<LoginRow>
          rows={loginsQ.data ?? []}
          columns={[
            { key: 'w', header: 'When', render: (r) => new Date(r.created_at).toLocaleString('en-PH') },
            { key: 'i', header: 'IP', render: (r) => r.meta?.ip ?? '—' },
          ]}
          empty={<p className="text-sm text-[var(--text-muted)]">No logins recorded yet.</p>}
        />
      </div>
    </div>
  );
}

/* -------------------------------- Integrations ------------------------------- */

interface Integration {
  key: string;
  dept: string;
  contract: string;
  mode: string;
  fixture_present: boolean | null;
}

function IntegrationsSection() {
  const toast = useToast();
  const statusQ = useQuery({
    queryKey: ['integrations'],
    queryFn: async () => (await api.get('/integrations/status')).data.data as { mode: string; services: Integration[] },
  });

  const test = async (key: string) => {
    try {
      const r = await api.get(`/integrations/${key}/test`);
      toast('success', `Mock OK: ${JSON.stringify(r.data.data.result).slice(0, 120)}`);
    } catch (e) {
      toast('error', apiErr(e, 'Test connection failed.'));
    }
  };

  if (statusQ.isLoading) return <p className="text-sm text-[var(--text-muted)]">Loading…</p>;
  return (
    <div className="card p-6">
      <h2 className="font-semibold">Integrations <span className="ml-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-800">mock mode (v1)</span></h2>
      <p className="mb-3 text-xs text-[var(--text-muted)]">Every cross-dept call resolves to a mock service + JSON fixture. Live mode unlocks in Step 9 hardening.</p>
      <DataTable<Integration & { id: string }>
        rows={(statusQ.data?.services ?? []).map((s) => ({ ...s, id: s.key }))}
        columns={[
          { key: 's', header: 'Service', render: (r) => <span className="font-medium">{r.key}<br /><span className="text-xs font-normal text-[var(--text-muted)]">{r.dept}</span></span> },
          { key: 'c', header: 'Contract', render: (r) => <span className="text-xs">{r.contract}</span> },
          { key: 'f', header: 'Fixture', render: (r) => (r.fixture_present === null ? '—' : r.fixture_present ? 'Yes' : 'Missing') },
          { key: 't', header: '', render: (r) => <button onClick={() => test(r.key)} className="rounded border border-[var(--border)] px-2 py-0.5 text-xs">Test connection</button> },
        ]}
        empty={<EmptyState title="No integrations" hint="Services register here." />}
      />
    </div>
  );
}

/* -------------------------------- Data & Backup ------------------------------ */

const ENTITIES = ['users', 'clients', 'leads', 'opportunities', 'activities', 'surveys', 'followups'];

function DataSection() {
  const toast = useToast();
  const download = async (entity: string) => {
    try {
      const r = await api.get(`/exports/${entity}.csv`, { responseType: 'blob' });
      const url = URL.createObjectURL(new Blob([r.data], { type: 'text/csv' }));
      const a = document.createElement('a');
      a.href = url;
      a.download = `${entity}.csv`;
      a.click();
      URL.revokeObjectURL(url);
      toast('success', `${entity}.csv downloaded.`);
    } catch {
      toast('error', 'Export failed.');
    }
  };

  return (
    <div className="flex flex-col gap-4">
      <div className="card p-6">
        <h2 className="font-semibold">Export CSV</h2>
        <p className="mb-3 text-xs text-[var(--text-muted)]">Full-table exports for audits and the BI team. Superadmin only.</p>
        <div className="flex flex-wrap gap-2">
          {ENTITIES.map((e) => (
            <button key={e} onClick={() => download(e)} className="rounded-lg border border-[var(--border)] px-3 py-1.5 text-sm capitalize">⬇ {e}</button>
          ))}
        </div>
      </div>
      <div className="card p-6">
        <h2 className="font-semibold">Backup & retention</h2>
        <p className="text-xs text-[var(--text-muted)]">Cloud database (Neon) snapshots automatically — no action needed. Local Postgres: <code>docker compose exec db pg_dump -U crm crm_primepower &gt; backup.sql</code>. Soft-deleted records are retained 90 days (see <code>retention_days</code> in General).</p>
      </div>
    </div>
  );
}

/* ------------------------------ AI & Reports stub ---------------------------- */

function ReportsPlaceholder() {
  return (
    <div className="card p-6">
      <h2 className="font-semibold">AI & Reports</h2>
      <p className="text-xs text-[var(--text-muted)]">Insight visibility, weekly/monthly report schedule, and feedback review land in Step 7 (specs/15). The <code>report_schedule</code> org key (General, superadmin) already reserves the setting.</p>
    </div>
  );
}
