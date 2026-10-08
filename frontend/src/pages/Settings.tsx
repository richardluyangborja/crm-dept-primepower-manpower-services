import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import api from '../lib/apiClient';
import { DataTable } from '../components/ui/DataTable';
import { EmptyState } from '../components/ui/EmptyState';
import { StatusBadge } from '../components/ui/StatusBadge';
import { ConfirmDialog } from '../components/ui/ConfirmDialog';
import { useToast } from '../components/ui/Toaster';
import { OtpModal } from '../components/auth/OtpModal';
import { hasRole, useSession, type Role } from '../store/session';
import { Check, Download, Save, X } from 'lucide-react';

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
      {canEdit && <div className="mt-4 flex justify-end"><button onClick={save} className="rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white"><span className="inline-flex items-center gap-1.5"><Save size={14} /> Save Changes</span></button></div>}
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
      {theme === mode && <span className="absolute right-2 top-2 flex h-5 w-5 items-center justify-center rounded-full bg-sky-500 text-white"><Check size={12} /></span>}
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
  const [membersOf, setMembersOf] = useState<Team | null>(null);
  const teamsQ = useQuery({
    queryKey: ['teams'],
    queryFn: async () => (await api.get('/teams')).data.data as Team[],
  });

  return (
    <div className="flex flex-col gap-4">
      <div className="card p-6">
        <h2 className="font-semibold">Teams</h2>
        <p className="mb-3 text-xs text-[var(--text-muted)]">One sales team for the whole crew — membership is automatic.</p>
        <DataTable<Team>
          rows={teamsQ.data ?? []}
          columns={[
            { key: 'n', header: 'Team', render: (r) => <span className="font-medium">{r.name}</span> },
            { key: 'r', header: 'Region', render: (r) => r.region ?? '—' },
            { key: 'u', header: 'Members', render: (r) => (
              <button onClick={() => setMembersOf(r)} className="text-sky-700 hover:underline dark:text-sky-300" title={`View members of ${r.name}`}>
                {r.users_count ?? '—'} →
              </button>
            )},
          ]}
          empty={teamsQ.isLoading ? <p className="text-sm">Loading…</p> : <EmptyState title="No teams" hint="Teams are managed by migration." />}
        />
        {membersOf && <TeamMembersModal team={membersOf} onClose={() => setMembersOf(null)} />}
      </div>
      <MasterDataSection />
    </div>
  );
}

function TeamMembersModal({ team, onClose }: { team: Team; onClose: () => void }) {
  const membersQ = useQuery({
    queryKey: ['users', `team-${team.id}`],
    queryFn: async () => (await api.get('/users', { params: { team_id: team.id, per_page: 100 } })).data.data as U[],
  });
  const rows = membersQ.data ?? [];
  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" role="dialog" aria-modal="true">
      <div className="card max-h-[80vh] w-full max-w-lg overflow-y-auto p-6">
        <h2 className="text-lg font-semibold">{team.name}</h2>
        <p className="mb-3 text-xs text-[var(--text-muted)]">{team.region ?? 'No region set'} · {rows.length} member{rows.length === 1 ? '' : 's'}</p>
        {membersQ.isLoading ? <p className="text-sm text-[var(--text-muted)]">Loading members…</p>
          : membersQ.isError ? <p className="text-sm text-red-600">Couldn't load members.</p>
          : rows.length === 0 ? <p className="text-sm text-[var(--text-muted)]">Nobody assigned yet.</p>
          : (
            <ul className="flex flex-col gap-2">
              {rows.map((u) => (
                <li key={u.id} className="flex items-center gap-3 rounded-lg border border-[var(--border)] p-2.5 text-sm">
                  <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-sky-600 text-xs font-bold text-white">
                    {(u.name ?? '?').slice(0, 1)}
                  </span>
                  <span className="min-w-0 flex-1">
                    <span className="block truncate font-medium">{u.name}</span>
                    <span className="block truncate text-xs text-[var(--text-muted)]">{u.email} · {u.phone ?? 'no phone'}</span>
                  </span>
                  <span className="flex shrink-0 flex-col items-end gap-1">
                    <StatusBadge value={u.role} />
                    <span className="text-[11px] text-[var(--text-muted)]">{u.is_active ? `active${u.last_login_at ? ` · last seen ${new Date(u.last_login_at).toLocaleDateString('en-PH', { month: 'short', day: 'numeric' })}` : ''}` : 'deactivated'}</span>
                  </span>
                </li>
              ))}
            </ul>
          )}
        <div className="mt-4 flex justify-end">
          <button onClick={onClose} className="rounded-lg border border-[var(--border)] px-4 py-2 text-sm">Close</button>
        </div>
      </div>
    </div>
  );
}

function MasterDataSection() {
  const { user } = useSession();
  const toast = useToast();
  const qc = useQueryClient();
  const canEdit = hasRole(user, 'superadmin');
  const settingsQ = useQuery({
    queryKey: ['settings'],
    queryFn: async () => (await api.get('/settings')).data.data as Record<string, unknown>,
  });
  const [draft, setDraft] = useState<Record<string, unknown> | null>(null);
  const data = (draft ?? settingsQ.data ?? {}) as Record<string, unknown>;
  const strList = (k: string): string[] => (Array.isArray(data[k]) && (data[k] as unknown[]).every((x) => typeof x === 'string') ? (data[k] as string[]) : []);
  const stageList = (): { key: string; label: string }[] =>
    Array.isArray(data.pipeline_stages) ? (data.pipeline_stages as { key: string; label: string }[]) : [];

  const setList = (k: string, v: string[]) => setDraft({ ...data, [k]: v });
  const editItem = (k: string, i: number, v: string) => {
    const next = [...strList(k)];
    next[i] = v;
    setList(k, next);
  };
  const addItem = (k: string) => setList(k, [...strList(k), 'New item']);
  const delItem = (k: string, i: number) => setList(k, strList(k).filter((_, j) => j !== i));
  const setStageLabel = (key: string, label: string) =>
    setDraft({ ...data, pipeline_stages: stageList().map((s) => (s.key === key ? { ...s, label } : s)) });

  const save = async () => {
    try {
      const clean = { ...data };
      for (const k of ['industries', 'lead_sources', 'lost_reasons']) {
        clean[k] = strList(k).map((s) => s.trim()).filter(Boolean);
      }
      await api.put('/settings', { settings: clean });
      toast('success', 'Master data saved.');
      setDraft(null);
      qc.invalidateQueries({ queryKey: ['settings'] });
    } catch (e) {
      toast('error', apiErr(e, 'Could not save master data.'));
    }
  };

  if (settingsQ.isLoading) return <p className="text-sm text-[var(--text-muted)]">Loading…</p>;
  const groups: [string, string][] = [
    ['industries', 'Industries (client form dropdown)'],
    ['lead_sources', 'Lead sources (capture form dropdown)'],
    ['lost_reasons', 'Lost-reason suggestions (win/loss dialog)'],
  ];

  return (
    <div className="card p-6">
      <h2 className="font-semibold">Master data</h2>
      <p className="mb-3 text-xs text-[var(--text-muted)]">Controlled vocabularies used across forms. {canEdit ? 'Stage keys stay fixed for logic; only labels are editable.' : 'Read-only — superadmin only.'}</p>
      {groups.map(([k, label]) => (
        <div key={k} className="mb-3">
          <p className="text-sm font-medium">{label}</p>
          <div className="mt-1 flex flex-wrap gap-1.5">
            {strList(k).map((v, i) => (
              <span key={i} className="flex items-center gap-1 rounded-full border border-[var(--border)] px-2 py-0.5 text-xs">
                {canEdit ? (
                  <>
                    <input value={v} onChange={(e) => editItem(k, i, e.target.value)} className="w-28 bg-transparent outline-none" aria-label={`${label} item ${i + 1}`} />
                    <button onClick={() => delItem(k, i)} className="text-red-500" aria-label={`Remove ${v}`}><X size={12} /></button>
                  </>
                ) : v}
              </span>
            ))}
            {canEdit && <button onClick={() => addItem(k)} className="rounded-full border border-dashed border-[var(--border)] px-2 py-0.5 text-xs">+ Add</button>}
          </div>
        </div>
      ))}
      <div className="mb-3">
        <p className="text-sm font-medium">Pipeline stage labels</p>
        <div className="mt-1 grid gap-1.5 sm:grid-cols-2">
          {stageList().map((s) => (
            <label key={s.key} className="flex items-center gap-2 rounded-lg border border-[var(--border)] px-2 py-1 text-xs">
              <code className="text-[var(--text-muted)]">{s.key}</code>
              {canEdit
                ? <input value={s.label} onChange={(e) => setStageLabel(s.key, e.target.value)} className="w-full bg-transparent text-sm outline-none" aria-label={`Label for ${s.key}`} />
                : <span className="text-sm">{s.label}</span>}
            </label>
          ))}
        </div>
      </div>
      {canEdit && <div className="mt-2 flex justify-end"><button onClick={save} className="rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white"><span className="inline-flex items-center gap-1.5"><Save size={14} /> Save Changes</span></button></div>}
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
      <div className="mt-4 flex justify-end"><button onClick={save} className="rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white"><span className="inline-flex items-center gap-1.5"><Save size={14} /> Save Changes</span></button></div>
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
  otp_enabled?: boolean;
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

  const doDeactivate = async (reassignTo?: number | null) => {
    if (!deactivateId) return;
    try {
      await api.post(`/users/${deactivateId}/deactivate`, reassignTo ? { reassign_to: reassignTo } : {});
      toast('success', reassignTo ? 'Account deactivated — open records handed over.' : 'Account deactivated.');
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
              key: 'o', header: 'OTP', render: (r) => readonly ? (r.otp_enabled ? 'On' : 'Off') : (
                <button
                  onClick={async () => {
                    try {
                      await api.post(`/users/${r.id}/otp`, { otp_enabled: !r.otp_enabled });
                      toast('success', r.otp_enabled ? `OTP off for ${r.name}.` : `OTP on for ${r.name} — their next login asks for a code.`);
                      invalidate();
                    } catch (e) {
                      toast('error', apiErr(e, 'Could not change OTP setting.'));
                    }
                  }}
                  title={r.otp_enabled ? 'Turn OTP off' : 'Turn OTP on — next login asks for a code'}
                  aria-label={`Toggle OTP for ${r.name}`}
                  className={`rounded-full px-2.5 py-0.5 text-xs font-medium ${r.otp_enabled ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'}`}
                >
                  {r.otp_enabled ? 'On' : 'Off'}
                </button>
              ),
            },
            {
              key: 'x', header: 'Actions', render: (r) => r.role === 'superadmin'
                ? <span className="text-xs text-[var(--text-muted)]" title="Seed-managed top account — role and password are fixed, OTP can still be toggled">locked</span>
                : readonly ? <span className="text-xs text-[var(--text-muted)]">—</span> : (
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
      {deactivateId !== null && (
        <DeactivateDialog
          userId={deactivateId}
          onCancel={() => setDeactivateId(null)}
          onConfirm={(reassignTo) => void doDeactivate(reassignTo)}
        />
      )}
    </>
  );
}

/** Deactivate with handover: owned-record counts + successor picker when needed. */
function DeactivateDialog({ userId, onCancel, onConfirm }: {
  userId: number;
  onCancel: () => void;
  onConfirm: (reassignTo: number | null) => void;
}) {
  const [successor, setSuccessor] = useState('');
  const ownedQ = useQuery({
    queryKey: ['users', userId, 'owned'],
    queryFn: async () => (await api.get(`/users/${userId}/owned`)).data.data as {
      open: { companies: number; leads: number; opportunities: number; followups: number; clients: number };
      suggested_successor: { id: number; name: string; role: string } | null;
    },
  });
  const open = ownedQ.data?.open ?? { companies: 0, leads: 0, opportunities: 0, followups: 0, clients: 0 };
  const openTotal = open.companies + open.leads + open.opportunities + open.followups;
  const suggested = ownedQ.data?.suggested_successor ?? null;
  const membersQ = useQuery({
    queryKey: ['users', 'successors'],
    queryFn: async () => (await api.get('/users', { params: { per_page: 100 } })).data.data as U[],
    enabled: openTotal > 0,
  });
  const candidates = (membersQ.data ?? []).filter((u) => u.is_active && u.id !== userId && ['sales_rep', 'manager'].includes(u.role));

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" role="dialog" aria-modal="true">
      <div className="card w-full max-w-md p-6">
        <h2 className="text-lg font-semibold">Deactivate this account?</h2>
        <p className="mt-1 text-sm text-[var(--text-muted)]">They will be signed out and cannot log in until reactivated by an admin.</p>
        {ownedQ.isLoading ? <p className="mt-2 text-sm text-[var(--text-muted)]">Checking their book…</p>
          : openTotal > 0 ? (
            <div className="mt-3 text-sm">
              <p className="font-medium">They still own open records — pick a successor:</p>
              <ul className="mt-1 flex flex-wrap gap-1.5 text-xs text-[var(--text-muted)]">
                {open.companies > 0 && <li className="rounded bg-slate-100 px-2 py-0.5 dark:bg-slate-800">{open.companies} companies</li>}
                {open.leads > 0 && <li className="rounded bg-slate-100 px-2 py-0.5 dark:bg-slate-800">{open.leads} leads</li>}
                {open.opportunities > 0 && <li className="rounded bg-slate-100 px-2 py-0.5 dark:bg-slate-800">{open.opportunities} deals</li>}
                {open.followups > 0 && <li className="rounded bg-slate-100 px-2 py-0.5 dark:bg-slate-800">{open.followups} reminders</li>}
              </ul>
              <label className="mt-2 block">Successor *
                <select required value={successor || String(suggested?.id ?? '')} onChange={(e) => setSuccessor(e.target.value)} className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2">
                  <option value="">Pick a teammate…</option>
                  {candidates.map((u) => <option key={u.id} value={u.id}>{u.name} · {u.role.replace('_', ' ')}{suggested?.id === u.id ? ' (suggested)' : ''}</option>)}
                </select>
              </label>
            </div>
          ) : (
            <p className="mt-2 text-sm text-[var(--text-muted)]">Their book is clear — nothing to hand over.</p>
          )}
        <div className="mt-4 flex justify-end gap-2">
          <button onClick={onCancel} className="rounded-lg border border-[var(--border)] px-4 py-2 text-sm">Cancel</button>
          <button
            disabled={openTotal > 0 && !successor && !suggested}
            onClick={() => onConfirm(openTotal > 0 ? Number(successor || suggested?.id) : null)}
            className="rounded-lg bg-red-600 px-4 py-2 text-sm text-white disabled:opacity-50"
          >
            Deactivate{openTotal > 0 ? ' + hand over' : ''}
          </button>
        </div>
      </div>
    </div>
  );
}

function InviteForm({ onClose, onDone }: { onClose: () => void; onDone: () => void }) {  const toast = useToast();
  const { user: me } = useSession();
  const roles = me?.role === 'superadmin' ? ['admin', 'manager', 'sales_rep'] : ['manager', 'sales_rep'];
  const [f, setF] = useState({ name: '', email: '', password: '', role: 'sales_rep', team_id: '', phone: '' });
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState('');
  const teamsQ = useQuery({ queryKey: ['teams'], queryFn: async () => (await api.get('/teams')).data.data as Team[] });
  const set = (k: keyof typeof f) => (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => setF({ ...f, [k]: e.target.value });
  const salesTeam = teamsQ.data?.find((t) => t.name === 'Primepower Team') ?? teamsQ.data?.[0];
  const needsTeam = f.role === 'manager' || f.role === 'sales_rep';

  const submit = async (ev: React.FormEvent) => {
    ev.preventDefault();
    setBusy(true);
    setErr('');
    try {
      await api.post('/users', {
        ...f,
        team_id: needsTeam ? (f.team_id ? Number(f.team_id) : salesTeam?.id) : undefined,
        phone: f.phone || undefined,
      });
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
              {roles.map((r) => <option key={r} value={r}>{r}</option>)}
            </select></label>
            {needsTeam ? (
              <label className="flex-1">Team<select value={f.team_id || String(salesTeam?.id ?? '')} onChange={set('team_id')} disabled className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2 opacity-70">
                {salesTeam && <option value={salesTeam.id}>{salesTeam.name} (only team)</option>}
              </select></label>
            ) : (
              <p className="flex-1 self-end pb-2 text-xs text-[var(--text-muted)]">Admins aren't on a team.</p>
            )}
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
  const { user: me } = useSession();
  const roles = me?.role === 'superadmin' ? ['admin', 'manager', 'sales_rep'] : ['manager', 'sales_rep'];
  const [role, setRole] = useState(user.role);
  const [teamId, setTeamId] = useState(user.team_id ? String(user.team_id) : '');
  const [busy, setBusy] = useState(false);
  const [stepUp, setStepUp] = useState(false);
  const teamsQ = useQuery({ queryKey: ['teams'], queryFn: async () => (await api.get('/teams')).data.data as Team[] });

  const save = async (headers?: Record<string, string>) => {
    await api.put(`/users/${user.id}`, {
      role,
      team_id: role === 'admin' || role === 'superadmin' ? null : (teamId ? Number(teamId) : null),
    }, { headers });
  };

  const submit = async (ev: React.FormEvent) => {
    ev.preventDefault();
    // Role actually changing → backend demands a fresh OTP grant (specs/16).
    const needsStepUp = role !== user.role;
    setBusy(true);
    try {
      await save();
      toast('success', 'Account updated.');
      onDone();
      onClose();
    } catch (e: unknown) {
      const status = (e as { response?: { status?: number } })?.response?.status;
      if (status === 428 && needsStepUp) {
        setStepUp(true);
        toast('info', 'Role changes need a fresh verification code.');
      } else {
        toast('error', apiErr(e, 'Could not update (last superadmin is protected).'));
      }
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
            {roles.includes(role) ? null : <option value={role}>{role} (current)</option>}
            {roles.map((r) => <option key={r} value={r}>{r}</option>)}
          </select></label>
          {role === 'admin' || role === 'superadmin' ? (
            <p className="text-xs text-[var(--text-muted)]">Admins aren't on a team — the field clears on save.</p>
          ) : (
            <label>Team<select value={teamId} onChange={(e) => setTeamId(e.target.value)} className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2">
              <option value="">—</option>
              {teamsQ.data?.map((t) => <option key={t.id} value={t.id}>{t.name}</option>)}
            </select></label>
          )}
        </div>
        <div className="mt-4 flex justify-end gap-2">
          <button type="button" onClick={onClose} className="rounded-lg border border-[var(--border)] px-4 py-2 text-sm">Cancel</button>
          <button disabled={busy} className="rounded-lg bg-sky-600 px-4 py-2 text-sm text-white disabled:opacity-50">Save</button>
        </div>
      </form>
      {stepUp && (
        <OtpModal
          email=""
          purpose="step_up"
          onVerified={async (payload) => {
            const grant = (payload as { step_up_token: string }).step_up_token;
            try {
              await save({ 'X-StepUp-Token': grant });
              toast('success', 'Account updated.');
              onDone();
              onClose();
            } catch (e) {
              toast('error', apiErr(e, 'Verification expired — try saving again.'));
              setStepUp(false);
            }
          }}
          onClose={() => setStepUp(false)}
        />
      )}
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
  const [revokeTarget, setRevokeTarget] = useState<number | null>(null);

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
    setRevokeTarget(id);
  };

  const doRevoke = async () => {
    if (revokeTarget === null) return;
    const id = revokeTarget;
    setRevokeTarget(null);
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
        <div className="mt-3 flex justify-end"><button className="rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white"><span className="inline-flex items-center gap-1.5"><Save size={14} /> Save Changes</span></button></div>
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
        <ConfirmDialog
          open={revokeTarget !== null}
          title="Revoke this session?"
          body="That device signs out on its next request. Use this if you don't recognize the IP or device."
          confirmLabel="Revoke session"
          onCancel={() => setRevokeTarget(null)}
          onConfirm={() => void doRevoke()}
        />
      </div>

      <TwoFactorCard />

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

/* ------------------------------ Two-factor (OTP) ----------------------------- */

function TwoFactorCard() {
  const { user } = useSession();
  const toast = useToast();
  const qc = useQueryClient();
  const meQ = useQuery({
    queryKey: ['users', 'me-otp'],
    queryFn: async () => (await api.get(`/users/${user?.id}`)).data.data as U,
    enabled: !!user?.id,
  });
  const on = meQ.data?.otp_enabled ?? false;
  const [busy, setBusy] = useState(false);

  const toggle = async () => {
    if (!user?.id) return;
    setBusy(true);
    try {
      await api.post(`/users/${user.id}/otp`, { otp_enabled: !on });
      toast('success', on ? 'OTP turned off for your account.' : 'OTP turned on — your next login asks for a code.');
      qc.invalidateQueries({ queryKey: ['users', 'me-otp'] });
      qc.invalidateQueries({ queryKey: ['users'] });
    } catch (e) {
      toast('error', apiErr(e, 'Could not change OTP setting.'));
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="card p-6">
      <h2 className="font-semibold">Two-factor (OTP)</h2>
      <p className="text-xs text-[var(--text-muted)]">
        When on, login sends a 6-digit code to your email — it expires in 5 minutes and locks for 15 minutes
        after 5 wrong tries. Codes are delivered by email, so keep your address current. Admins can also
        switch OTP on/off per account in Users & Access.
      </p>
      <label className="mt-3 flex items-center justify-between gap-3 text-sm">
        <span className="font-medium">OTP for my account <span className="font-normal text-[var(--text-muted)]">({meQ.isLoading ? '…' : on ? 'on' : 'off'})</span></span>
        <button
          onClick={toggle}
          disabled={busy || meQ.isLoading}
          role="switch"
          aria-checked={on}
          className={`relative h-6 w-11 shrink-0 rounded-full transition-colors disabled:opacity-50 ${on ? 'bg-green-500' : 'bg-slate-300 dark:bg-slate-700'}`}
        >
          <span className={`absolute top-0.5 h-5 w-5 rounded-full bg-white shadow transition-all ${on ? 'left-[22px]' : 'left-0.5'}`} />
        </button>
      </label>
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
      toast('success', `Connection OK: ${JSON.stringify(r.data.data.result).slice(0, 120)}`);
    } catch (e) {
      toast('error', apiErr(e, 'Test connection failed.'));
    }
  };

  if (statusQ.isLoading) return <p className="text-sm text-[var(--text-muted)]">Loading…</p>;
  return (
    <div className="card p-6">
      <h2 className="font-semibold">Connected systems</h2>
      <p className="mb-3 text-xs text-[var(--text-muted)]">Each row is a department system this CRM talks to (HR, Finance, Client Management…). “Test connection” sends a sample request and shows what comes back — use it to confirm the link is alive.</p>
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
  const qc = useQueryClient();
  const settingsQ = useQuery({
    queryKey: ['settings'],
    queryFn: async () => (await api.get('/settings')).data.data as Record<string, unknown>,
  });
  const [retention, setRetention] = useState('');
  const current = settingsQ.data?.retention_days;
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
  const snapshot = async () => {
    try {
      const r = await api.get('/exports/snapshot.json', { responseType: 'blob' });
      const url = URL.createObjectURL(new Blob([r.data], { type: 'application/json' }));
      const a = document.createElement('a');
      a.href = url;
      a.download = `crm-snapshot-${new Date().toISOString().slice(0, 10)}.json`;
      a.click();
      URL.revokeObjectURL(url);
      toast('success', 'Snapshot downloaded — store it somewhere safe.');
    } catch {
      toast('error', 'Snapshot failed.');
    }
  };
  const saveRetention = async () => {
    const days = Number(retention);
    if (!Number.isInteger(days) || days < 7 || days > 3650) {
      toast('error', 'Retention must be 7–3650 days.');
      return;
    }
    try {
      await api.put('/settings', { settings: { retention_days: days } });
      toast('success', `Retention set to ${days} days.`);
      setRetention('');
      qc.invalidateQueries({ queryKey: ['settings'] });
    } catch (e) {
      toast('error', apiErr(e, 'Could not save retention.'));
    }
  };

  return (
    <div className="flex flex-col gap-4">
      <div className="card p-6">
        <h2 className="font-semibold">Export CSV</h2>
        <p className="mb-3 text-xs text-[var(--text-muted)]">Full-table exports for audits and the BI team. Superadmin only.</p>
        <div className="flex flex-wrap gap-2">
          {ENTITIES.map((e) => (
            <button key={e} onClick={() => download(e)} className="rounded-lg border border-[var(--border)] px-3 py-1.5 text-sm capitalize"><span className="inline-flex items-center gap-1"><Download size={12} /> {e}</span></button>
          ))}
        </div>
      </div>
      <div className="card p-6">
        <h2 className="font-semibold">Backup & retention</h2>
        <p className="text-xs text-[var(--text-muted)]">
          Download a full JSON snapshot any time (everything the CSVs cover, in one file — no passwords inside).
          Server-level database snapshots stay on the deployment runbook. Soft-deleted records are kept{' '}
          {typeof current === 'number' ? <strong>{current} days</strong> : 'per retention'} before hard cleanup.
        </p>
        <div className="mt-3 flex flex-wrap items-end gap-2">
          <button onClick={snapshot} className="rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white"><span className="inline-flex items-center gap-1.5"><Download size={14} /> Download snapshot (JSON)</span></button>
          <label className="text-sm">Retention (days)
            <input value={retention} onChange={(e) => setRetention(e.target.value)} inputMode="numeric" placeholder={typeof current === 'number' ? String(current) : '90'} className="ml-2 w-24 rounded-lg border border-[var(--border)] bg-transparent px-3 py-2" />
          </label>
          <button onClick={saveRetention} disabled={!retention} className="rounded-lg border border-[var(--border)] px-4 py-2 text-sm disabled:opacity-50">Save</button>
        </div>
      </div>
    </div>
  );
}

/* ------------------------------ AI & Reports ------------------------------- */

function ReportsPlaceholder() {
  const toast = useToast();
  const qc = useQueryClient();
  const { user } = useSession();
  const canEdit = hasRole(user, 'superadmin');
  const settingsQ = useQuery({
    queryKey: ['settings'],
    queryFn: async () => (await api.get('/settings')).data.data as Record<string, unknown>,
  });
  const [schedule, setSchedule] = useState('');
  const current = typeof settingsQ.data?.report_schedule === 'string' ? settingsQ.data.report_schedule : 'monthly';

  const save = async () => {
    if (!schedule) return;
    try {
      await api.put('/settings', { settings: { report_schedule: schedule } });
      toast('success', `Report pack cadence set to ${schedule}.`);
      setSchedule('');
      qc.invalidateQueries({ queryKey: ['settings'] });
    } catch (e) {
      toast('error', apiErr(e, 'Could not save cadence.'));
    }
  };

  return (
    <div className="flex flex-col gap-4">
      <div className="card p-6">
        <h2 className="font-semibold">Report packs</h2>
        <p className="mb-3 text-xs text-[var(--text-muted)]">
          The Monday-morning pack (churn risks, forecast, follow-up load) is generated from live CRM data on the{' '}
          <Link to="/reports" className="text-sky-700 underline dark:text-sky-300">Reports page</Link>. Set how often the team expects a fresh one.
        </p>
        <label className="flex max-w-sm flex-wrap items-end gap-2 text-sm">Cadence
          <select value={schedule || current} onChange={(e) => setSchedule(e.target.value)} disabled={!canEdit} className="rounded-lg border border-[var(--border)] bg-transparent px-3 py-2 disabled:opacity-60">
            <option value="weekly">Weekly</option>
            <option value="monthly">Monthly</option>
          </select>
          {canEdit && <button onClick={save} disabled={!schedule || schedule === current} className="rounded-lg border border-[var(--border)] px-4 py-2 disabled:opacity-50">Save</button>}
        </label>
        {!canEdit && <p className="mt-1 text-xs text-[var(--text-muted)]">Read-only — superadmin only.</p>}
      </div>
      <div className="card p-6">
        <h2 className="font-semibold">AI insights</h2>
        <p className="text-xs text-[var(--text-muted)]">
          Churn flags, next-best-actions, and the forecast adjustment are computed from live CRM data by a rules
          engine today (labeled <strong>AI</strong> wherever they appear). Rate them with 👍/👎 where shown —
          feedback is reviewed on the <Link to="/reports" className="text-sky-700 underline dark:text-sky-300">Reports page</Link> and trains the next model.
        </p>
      </div>
    </div>
  );
}
