import { useState } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { Phone, Mail, Users, MapPin, StickyNote } from 'lucide-react';
import api from '../lib/apiClient';
import { EmptyState } from '../components/ui/EmptyState';
import { useToast } from '../components/ui/Toaster';

interface Act {
  id: number;
  client_id: number;
  client_name?: string;
  opportunity_id: number | null;
  type: 'call' | 'email' | 'meeting' | 'site_visit' | 'note';
  subject: string | null;
  body: string | null;
  outcome: string | null;
  occurred_at: string;
  attachments: { name: string; size: number; mime: string }[];
}

interface Tpl {
  id: string;
  name: string;
  type: string;
  subject: string;
  body: string;
}

const TYPES = ['call', 'email', 'meeting', 'site_visit', 'note'] as const;
const ICONS: Record<string, React.ReactNode> = {
  call: <Phone size={16} />,
  email: <Mail size={16} />,
  meeting: <Users size={16} />,
  site_visit: <MapPin size={16} />,
  note: <StickyNote size={16} />,
};

const rel = (iso: string) => {
  const mins = Math.round((Date.now() - new Date(iso).getTime()) / 60000);
  if (mins < 1) return 'just now';
  if (mins < 60) return `${mins}m ago`;
  const h = Math.round(mins / 60);
  if (h < 24) return `${h}h ago`;
  return `${Math.round(h / 24)}d ago`;
};

export function CommsPage() {
  const [q, setQ] = useState('');
  const [type, setType] = useState('');
  const [clientId, setClientId] = useState('');
  const [showNew, setShowNew] = useState(false);
  const toast = useToast();
  const qc = useQueryClient();

  const actsQ = useQuery({
    queryKey: ['activities', q, type, clientId],
    queryFn: async () =>
      (await api.get('/activities', { params: { q: q || undefined, type: type || undefined, client_id: clientId || undefined, per_page: 50 } }))
        .data.data as Act[],
  });
  const clientsQ = useQuery({
    queryKey: ['clients-mini'],
    queryFn: async () => (await api.get('/clients', { params: { per_page: 100 } })).data.data as { id: number; name: string }[],
  });
  const rows = actsQ.data ?? [];

  return (
    <div className="flex flex-col gap-4">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-xl font-bold">Communications</h1>
          <p className="text-sm text-[var(--text-muted)]">Every touchpoint, one timeline. Mock mode — no real emails leave the system.</p>
        </div>
        <button onClick={() => setShowNew(true)} className="rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white">+ Log activity</button>
      </div>

      <div className="flex flex-wrap gap-2">
        <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Search subject, notes… (try “quotation”)" className="card min-w-52 flex-1 px-3 py-2 text-sm outline-none" />
        <select value={type} onChange={(e) => setType(e.target.value)} className="card px-3 py-2 text-sm" aria-label="Filter by type">
          <option value="">All types</option>
          {TYPES.map((t) => <option key={t} value={t}>{t}</option>)}
        </select>
        <select value={clientId} onChange={(e) => setClientId(e.target.value)} className="card px-3 py-2 text-sm" aria-label="Filter by client">
          <option value="">All clients</option>
          {clientsQ.data?.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
        </select>
      </div>

      {actsQ.isLoading ? <p className="text-sm text-[var(--text-muted)]">Loading timeline…</p>
        : actsQ.isError ? <div className="card p-6 text-sm">Couldn't load the timeline. <button className="text-sky-600 underline" onClick={() => actsQ.refetch()}>Retry</button></div>
        : rows.length === 0 ? <EmptyState title="No communications yet" hint="Log the first call — pick a client, a type, and what happened." action={<button onClick={() => setShowNew(true)} className="mt-2 rounded-lg bg-sky-600 px-4 py-2 text-sm text-white">+ Log activity</button>} />
        : (
          <div className="card divide-y divide-[var(--border)]">
            {rows.map((a) => (
              <div key={a.id} className="flex gap-3 p-4">
                <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-200">
                  {ICONS[a.type]}
                </span>
                <div className="min-w-0 flex-1">
                  <div className="flex flex-wrap items-center gap-2">
                    <p className="text-sm font-semibold">{a.subject || a.type}</p>
                    {a.outcome && <span className="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] text-slate-700 dark:bg-slate-700 dark:text-slate-200">{a.outcome}</span>}
                  </div>
                  <p className="text-xs text-[var(--text-muted)]">{a.client_name} · {rel(a.occurred_at)}</p>
                  {a.body && <p className="mt-1 whitespace-pre-wrap text-sm">{highlight(a.body, q)}</p>}
                  {a.attachments.length > 0 && (
                    <p className="mt-1 text-xs text-[var(--text-muted)]">📎 {a.attachments.map((f) => f.name).join(', ')}</p>
                  )}
                </div>
              </div>
            ))}
          </div>
        )}

      {showNew && (
        <Composer
          clients={clientsQ.data ?? []}
          onClose={() => setShowNew(false)}
          onDone={() => {
            qc.invalidateQueries({ queryKey: ['activities'] });
            qc.invalidateQueries({ queryKey: ['dashboard'] });
            qc.invalidateQueries({ queryKey: ['followups'] });
            toast('success', 'Logged — timeline updated.');
          }}
        />
      )}
    </div>
  );
}

function highlight(body: string, q: string): React.ReactNode {
  if (!q.trim()) return body;
  const i = body.toLowerCase().indexOf(q.toLowerCase());
  if (i < 0) return body;
  return (
    <>
      {body.slice(0, i)}
      <mark className="rounded bg-yellow-200 px-0.5 dark:bg-yellow-800">{body.slice(i, i + q.length)}</mark>
      {body.slice(i + q.length)}
    </>
  );
}

function Composer({ clients, onClose, onDone }: { clients: { id: number; name: string }[]; onClose: () => void; onDone: () => void }) {
  const [tab, setTab] = useState<(typeof TYPES)[number]>('call');
  const [f, setF] = useState({ client_id: '', subject: '', body: '', outcome: 'connected', occurred: '', wantFollowup: false, followup_title: '', followup_due: '' });
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState('');
  const [showTpls, setShowTpls] = useState(false);
  const tplsQ = useQuery({
    queryKey: ['message-templates'],
    queryFn: async () => (await api.get('/message-templates')).data.data as Tpl[],
    enabled: showTpls,
  });

  const set = (k: keyof typeof f) => (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>) =>
    setF({ ...f, [k]: e.target.type === 'checkbox' ? (e.target as HTMLInputElement).checked : e.target.value });

  const submit = async (ev: React.FormEvent) => {
    ev.preventDefault();
    setBusy(true);
    setErr('');
    try {
      await api.post('/activities', {
        client_id: Number(f.client_id),
        type: tab,
        subject: f.subject || undefined,
        body: f.body || undefined,
        outcome: f.outcome || undefined,
        occurred_at: f.occurred ? new Date(f.occurred).toISOString() : undefined,
        create_followup: f.wantFollowup || undefined,
        followup_title: f.followup_title || undefined,
        followup_due_at: f.followup_due ? new Date(f.followup_due).toISOString() : undefined,
      });
      onDone();
      onClose();
    } catch (e) {
      setErr(apiErr(e, 'Could not log activity.'));
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-black/40 p-4" role="dialog" aria-modal="true">
      <form onSubmit={submit} className="card my-8 w-full max-w-lg p-6">
        <h2 className="text-lg font-semibold">Log activity</h2>
        <div className="mt-2 flex gap-1">
          {TYPES.map((t) => (
            <button type="button" key={t} onClick={() => setTab(t)}
              className={`flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs ${tab === t ? 'bg-sky-600 text-white' : 'border border-[var(--border)]'}`}>
              {ICONS[t]}{t}
            </button>
          ))}
        </div>
        <div className="mt-3 flex flex-col gap-2 text-sm">
          <label>Client *<select required value={f.client_id} onChange={set('client_id')} className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2">
            <option value="">Pick a client…</option>
            {clients.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
          </select></label>
          <div className="flex gap-2">
            <label className="flex-1">Subject{(tab === 'email' || tab === 'meeting') && ' *'}<input value={f.subject} onChange={set('subject')} placeholder={tab === 'email' ? 'Quotation sent — 120 janitors' : 'What was this about?'} className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2" /></label>
            <label>Outcome<select value={f.outcome} onChange={set('outcome')} className="mt-1 rounded-lg border border-[var(--border)] bg-transparent px-3 py-2">
              {['connected', 'no-answer', 'callback', 'sent', 'done'].map((o) => <option key={o} value={o}>{o}</option>)}
            </select></label>
          </div>
          <label>Notes<textarea value={f.body} onChange={set('body')} rows={3} placeholder="What happened, what was agreed…" className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2" /></label>
          <div className="flex items-center justify-between">
            <button type="button" onClick={() => setShowTpls((v) => !v)} className="text-xs text-sky-600 underline">Use a template</button>
            <label className="text-xs text-[var(--text-muted)]">When <input type="datetime-local" value={f.occurred} onChange={set('occurred')} className="rounded border border-[var(--border)] bg-transparent px-1 py-0.5" /> <span title="Defaults to now">(default now)</span></label>
          </div>
          {showTpls && (
            <div className="rounded-lg border border-[var(--border)] p-2">
              {tplsQ.isLoading ? <p className="text-xs">Loading templates…</p> : tplsQ.data?.map((t) => (
                <button type="button" key={t.id} onClick={() => { setF({ ...f, subject: t.subject, body: t.body }); setShowTpls(false); }} className="block w-full rounded px-2 py-1.5 text-left text-xs hover:bg-slate-100 dark:hover:bg-slate-800">
                  <span className="font-medium">{t.name}</span> <span className="text-[var(--text-muted)]">({t.type})</span>
                </button>
              ))}
            </div>
          )}
          <label className="flex items-center gap-2 text-sm">
            <input type="checkbox" checked={f.wantFollowup} onChange={set('wantFollowup')} /> Create a follow-up from this
          </label>
          {f.wantFollowup && (
            <div className="flex gap-2">
              <input value={f.followup_title} onChange={set('followup_title')} placeholder="Follow-up title" className="flex-1 rounded-lg border border-[var(--border)] bg-transparent px-3 py-2 text-sm" />
              <input type="datetime-local" value={f.followup_due} onChange={set('followup_due')} className="rounded-lg border border-[var(--border)] bg-transparent px-3 py-2 text-sm" />
            </div>
          )}
        </div>
        {err && <p className="mt-2 text-sm text-red-600">{err}</p>}
        <div className="mt-4 flex justify-end gap-2">
          <button type="button" onClick={onClose} className="rounded-lg border border-[var(--border)] px-4 py-2 text-sm">Cancel</button>
          <button disabled={busy} className="rounded-lg bg-sky-600 px-4 py-2 text-sm text-white disabled:opacity-50">{busy ? 'Logging…' : 'Log it'}</button>
        </div>
      </form>
    </div>
  );

  function apiErr(e: unknown, fallback: string): string {
    if (typeof e === 'object' && e !== null && 'response' in e) {
      const r = (e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }).response;
      if (r?.data?.errors) return Object.values(r.data.errors).flat().join(' ');
      if (r?.data?.message) return r.data.message;
    }
    return fallback;
  }
}
