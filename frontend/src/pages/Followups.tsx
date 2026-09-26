import { useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import api from '../lib/apiClient';
import { DataTable } from '../components/ui/DataTable';
import { EmptyState } from '../components/ui/EmptyState';
import { ConfirmDialog } from '../components/ui/ConfirmDialog';
import { StatusBadge } from '../components/ui/StatusBadge';
import { useToast } from '../components/ui/Toaster';
import { hasRole, useSession } from '../store/session';
import { Check, Clock, ArrowUpRight } from 'lucide-react';

interface Fup {
  id: string;
  owner_id: number;
  client_id: string;
  client_name?: string;
  opportunity_id: string | null;
  title: string;
  due_at: string;
  priority: 'low' | 'medium' | 'high';
  status: string;
  snoozed_until: string | null;
  is_overdue: boolean;
}

const fmtDT = (iso: string) =>
  new Date(iso).toLocaleString('en-PH', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });

/** One hierarchy for all reminder actions: Done (primary) · Snooze · Escalate (danger, when relevant). */
function FupActions({ r, onDone, onSnooze, onEscalate }: {
  r: Fup;
  onDone: (id: string) => void;
  onSnooze: (id: string) => void;
  onEscalate: (id: string) => void;
}) {
  if (r.status === 'done') return <span className="text-[var(--text-muted)]"><Check size={14} /></span>;
  return (
    <span className="flex flex-wrap gap-1">
      <button onClick={() => onDone(r.id)} title="Mark done"
        className="inline-flex items-center gap-1 rounded-lg bg-green-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-green-700">
        <Check size={12} /> Done
      </button>
      <button onClick={() => onSnooze(r.id)} title="Snooze to later"
        className="inline-flex items-center gap-1 rounded-lg border border-[var(--border)] px-2.5 py-1 text-xs font-medium hover:bg-slate-100 dark:hover:bg-slate-800">
        <Clock size={12} /> Snooze
      </button>
      {(r.status === 'overdue' || r.status === 'escalated') && (
        <button onClick={() => onEscalate(r.id)} title="Escalate to your manager"
          className="inline-flex items-center gap-1 rounded-lg border border-red-300 px-2.5 py-1 text-xs font-medium text-red-600 hover:bg-red-50 dark:hover:bg-red-950/30">
          <ArrowUpRight size={12} /> Escalate
        </button>
      )}
    </span>
  );
}

export function FollowupsPage() {
  const [view, setView] = useState<'queue' | 'calendar'>('queue');
  const [status, setStatus] = useState('');
  const [showNew, setShowNew] = useState(false);
  const [snoozeTarget, setSnoozeTarget] = useState<string | null>(null);
  const [day, setDay] = useState<string>(() => new Date().toISOString().slice(0, 10));
  const [page, setPage] = useState(1);
  const PER_PAGE = 15;
  const toast = useToast();
  const qc = useQueryClient();

  const fupsQ = useQuery({
    queryKey: ['followups', status, page],
    queryFn: async () => (await api.get('/followups', { params: { status: status || undefined, page, per_page: PER_PAGE } })).data,
  });
  const rows: Fup[] = fupsQ.data?.data ?? [];
  // Full-scope rows for the overdue banner, due-today strip, and calendar (not just the page).
  const scopeQ = useQuery({
    queryKey: ['followups', status, 'scope'],
    queryFn: async () => (await api.get('/followups', { params: { status: status || undefined, per_page: 100 } })).data.data as Fup[],
  });
  const scopeRows = scopeQ.data ?? [];
  const overdue = scopeRows.filter((r) => r.status === 'overdue' || r.status === 'escalated');

  const invalidate = () => {
    qc.invalidateQueries({ queryKey: ['followups'] });
    qc.invalidateQueries({ queryKey: ['dashboard'] });
    qc.invalidateQueries({ queryKey: ['notifications-unread'] });
  };

  // Called unconditionally in stable order — safe hook usage.
  const mutateAction = (
    fn: (id: string) => Promise<unknown>,
    ok: string,
  ) =>
    useMutation({
      mutationFn: fn,
      onSuccess: () => {
        toast('success', ok);
        invalidate();
      },
      onError: (e) => toast('error', apiErr(e, 'Action failed.')),
    });
  const doneMut = mutateAction((id) => api.post(`/followups/${id}/done`), 'Done — nice.');
  const escMut = mutateAction((id) => api.post(`/followups/${id}/escalate`), 'Escalated to your manager.');
  const snoozeMut = useMutation({
    mutationFn: async ({ id, until }: { id: string; until: string }) => api.post(`/followups/${id}/snooze`, { snoozed_until: until }),
    onSuccess: () => {
      toast('success', 'Snoozed.');
      invalidate();
    },
    onError: (e) => toast('error', apiErr(e, 'Could not snooze.')),
  });
  const snooze = (id: string) => {
    setSnoozeTarget(id);
  };
  const confirmSnooze = (pick: string | undefined) => {
    if (snoozeTarget === null || !pick) {
      setSnoozeTarget(null);
      return;
    }
    const until = parseSnooze(pick);
    if (!until) {
      toast('error', 'Use 1d / 3d / 1w or YYYY-MM-DD HH:mm.');
      return;
    }
    snoozeMut.mutate({ id: snoozeTarget, until });
    setSnoozeTarget(null);
  };

  return (
    <div className="flex flex-col gap-4">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-xl font-bold">Follow-ups</h1>
          <p className="text-sm text-[var(--text-muted)]">Clear overdue first — they auto-escalate after 72 hours.</p>
        </div>
        <button onClick={() => setShowNew(true)} className="rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white">+ Reminder</button>
      </div>

      {overdue.length > 0 && (
        <div className="card border-l-4 border-l-red-500 p-4">
          <p className="font-semibold text-red-700 dark:text-red-400">{overdue.length} overdue — clear them to keep the pipeline healthy</p>
          <ul className="mt-2 flex flex-col gap-1 text-sm">
            {overdue.slice(0, 5).map((r) => (
              <li key={r.id} className="flex items-center justify-between gap-2">
                <span className="truncate">{r.title} <span className="text-xs text-[var(--text-muted)]">· due {fmtDT(r.due_at)}</span></span>
                <FupActions r={r} onDone={(id) => doneMut.mutate(id)} onSnooze={snooze} onEscalate={(id) => escMut.mutate(id)} />
              </li>
            ))}
          </ul>
        </div>
      )}

      <div className="flex gap-2">
        {(['queue', 'calendar'] as const).map((v) => (
          <button key={v} onClick={() => setView(v)} className={`rounded-lg px-4 py-1.5 text-sm font-medium ${view === v ? 'bg-sky-100 text-sky-900 dark:bg-sky-900/40 dark:text-sky-100' : 'border border-[var(--border)]'}`}>
            {v === 'queue' ? 'My tasks' : 'Calendar'}
          </button>
        ))}
        <select value={status} onChange={(e) => { setStatus(e.target.value); setPage(1); }} className="card px-3 py-1.5 text-sm" aria-label="Filter by status">
          <option value="">All open-ish</option>
          {['open', 'snoozed', 'overdue', 'escalated', 'done'].map((s) => <option key={s} value={s}>{s}</option>)}
        </select>
      </div>

      {fupsQ.isLoading ? <p className="text-sm text-[var(--text-muted)]">Loading reminders…</p>
        : fupsQ.isError ? <div className="card p-6 text-sm">Couldn't load reminders. <button className="text-sky-600 underline" onClick={() => fupsQ.refetch()}>Retry</button></div>
        : view === 'queue' ? (
          scopeRows.length === 0 && rows.length === 0
            ? <EmptyState title="No follow-ups due" hint="Set a reminder on any client and it will appear here with a nudge before it's due." action={<button onClick={() => setShowNew(true)} className="mt-2 rounded-lg bg-sky-600 px-4 py-2 text-sm text-white">+ Reminder</button>} />
            : <DataTable<Fup>
              rows={rows}
              pagination={{ page, perPage: PER_PAGE, total: fupsQ.data?.meta?.total ?? rows.length, onPage: setPage }}
              columns={[
                { key: 't', header: 'Reminder', render: (r) => <span className="font-medium">{r.title}<br /><span className="text-xs font-normal text-[var(--text-muted)]">{r.client_name ?? ''}</span></span> },
                { key: 'd', header: 'Due', render: (r) => <span className={r.is_overdue ? 'font-semibold text-red-600' : ''}>{fmtDT(r.due_at)}</span> },
                { key: 'p', header: 'Priority', render: (r) => <StatusBadge value={r.priority} /> },
                { key: 's', header: 'Status', render: (r) => <StatusBadge value={r.status} /> },
                { key: 'a', header: 'Actions', render: (r) => <FupActions r={r} onDone={(id) => doneMut.mutate(id)} onSnooze={snooze} onEscalate={(id) => escMut.mutate(id)} /> },
              ]}
              empty={<EmptyState title="Nothing here" hint="Try a different status filter." />}
            />
        ) : (
          <CalendarSection rows={scopeRows} day={day} onDay={setDay} />
        )}

      {showNew && <NewReminderForm onClose={() => setShowNew(false)} onDone={invalidate} />}
      <ConfirmDialog
        open={snoozeTarget !== null}
        tone="info"
        title="Snooze reminder?"
        body="It leaves the queue until the new due time — overdue escalation pauses while snoozed."
        confirmLabel="Snooze"
        input={{ label: 'Snooze until', placeholder: '1d, 3d, 1w or YYYY-MM-DD HH:mm', required: true, initial: '1d' }}
        onCancel={() => setSnoozeTarget(null)}
        onConfirm={(pick) => confirmSnooze(pick)}
      />
    </div>
  );

  function parseSnooze(pick: string): string | null {
    const m = pick.match(/^(\d+)\s*(d|w)$/i);
    const d = new Date();
    if (m) {
      d.setDate(d.getDate() + Number(m[1]) * (m[2].toLowerCase() === 'w' ? 7 : 1));
      return d.toISOString();
    }
    const custom = new Date(pick.replace(' ', 'T'));
    return isNaN(custom.getTime()) || custom <= new Date() ? null : custom.toISOString();
  }
}

function CalendarSection({ rows, day, onDay }: { rows: Fup[]; day: string; onDay: (d: string) => void }) {
  const toast = useToast();
  const qc = useQueryClient();
  const [mode, setMode] = useState<'month' | 'week' | 'day'>('month');

  const reschedMut = useMutation({
    mutationFn: async ({ id, due_at }: { id: string; due_at: string }) => api.put(`/followups/${id}`, { due_at }),
    onSuccess: () => {
      toast('success', 'Rescheduled.');
      qc.invalidateQueries({ queryKey: ['followups'] });
      qc.invalidateQueries({ queryKey: ['dashboard'] });
    },
    onError: (e) => toast('error', apiErr(e, 'Only the owner or a manager can reschedule.')),
  });

  const dropTo = (dateKey: string) => (e: React.DragEvent) => {
    e.preventDefault();
    const id = e.dataTransfer.getData('text/followup-id');
    if (!id) return;
    const [y, m, d] = dateKey.split('-').map(Number);
    reschedMut.mutate({ id, due_at: new Date(y, m - 1, d, 12).toISOString() });
  };

  return (
    <div className="flex flex-col gap-2">
      <div className="flex gap-1.5">
        {(['month', 'week', 'day'] as const).map((v) => (
          <button key={v} onClick={() => setMode(v)}
            className={`rounded-lg px-3 py-1.5 text-xs font-medium capitalize ${mode === v ? 'bg-sky-100 text-sky-900 dark:bg-sky-900/40 dark:text-sky-100' : 'border border-[var(--border)]'}`}>
            {v}
          </button>
        ))}
        <span className="self-center text-[11px] text-[var(--text-muted)]">Drag items onto a day to reschedule</span>
      </div>
      {mode === 'month' && <MonthCalendar rows={rows} day={day} onDay={onDay} onDropDay={dropTo} />}
      {mode === 'week' && <WeekView rows={rows} day={day} onDay={onDay} onDropDay={dropTo} />}
      {mode === 'day' && <DayView rows={rows} day={day} onDay={onDay} />}
    </div>
  );
}

function DraggableItem({ r }: { r: Fup }) {
  return (
    <span draggable onDragStart={(e) => e.dataTransfer.setData('text/followup-id', String(r.id))}
      title="Drag onto a calendar day to reschedule"
      className="cursor-grab active:cursor-grabbing">
      <StatusBadge value={r.priority} /> {r.title}
    </span>
  );
}

const dayKey = (d: Date) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
const shiftDay = (key: string, delta: number) => {
  const [y, m, d] = key.split('-').map(Number);
  const t = new Date(y, m - 1, d);
  t.setDate(t.getDate() + delta);
  return dayKey(t);
};

const shiftMonth = (key: string, delta: number) => {
  const [y, m] = key.split('-').map(Number);
  const t = new Date(y, m - 1 + delta, 1);
  const p = (n: number) => String(n).padStart(2, '0');
  return `${t.getFullYear()}-${p(t.getMonth() + 1)}-01`;
};

function WeekView({ rows, day, onDay, onDropDay }: { rows: Fup[]; day: string; onDay: (d: string) => void; onDropDay: (k: string) => (e: React.DragEvent) => void }) {
  const [y, m, d] = day.split('-').map(Number);
  const base = new Date(y, m - 1, d);
  const start = new Date(base);
  start.setDate(base.getDate() - base.getDay());
  const days = Array.from({ length: 7 }, (_, i) => {
    const t = new Date(start);
    t.setDate(start.getDate() + i);
    return t;
  });
  const forDay = (k: string) => rows.filter((r) => (r.due_at ?? '').slice(0, 10) === k && r.status !== 'done');
  return (
    <div className="card p-3">
      <div className="mb-2 flex items-center justify-between">
        <button onClick={() => onDay(shiftDay(day, -7))} className="rounded-lg border border-[var(--border)] px-2 py-1 text-xs">← Prev</button>
        <p className="font-semibold">{days[0].toLocaleDateString('en-PH', { month: 'short', day: 'numeric' })} – {days[6].toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' })}</p>
        <button onClick={() => onDay(shiftDay(day, 7))} className="rounded-lg border border-[var(--border)] px-2 py-1 text-xs">Next →</button>
      </div>
      <div className="grid grid-cols-7 gap-1">
        {days.map((dt) => {
          const k = dayKey(dt);
          const items = forDay(k);
          return (
            <div key={k} onDragOver={(e) => e.preventDefault()} onDrop={onDropDay(k)}
              onClick={() => onDay(k)} className={`min-h-24 cursor-pointer rounded-lg border p-1.5 text-xs ${k === day ? 'border-sky-500' : 'border-[var(--border)]'}`}>
              <p className={`font-semibold ${k === day ? 'text-sky-600' : ''}`}>{dt.getDate()}</p>
              {items.map((r) => <div key={r.id} className="mt-1 truncate"><DraggableItem r={r} /></div>)}
            </div>
          );
        })}
      </div>
    </div>
  );
}

function DayView({ rows, day, onDay }: { rows: Fup[]; day: string; onDay: (d: string) => void }) {
  const items = rows.filter((r) => (r.due_at ?? '').slice(0, 10) === day && r.status !== 'done');
  return (
    <div className="card p-3">
      <div className="mb-2 flex items-center justify-between">
        <button onClick={() => onDay(shiftDay(day, -1))} className="rounded-lg border border-[var(--border)] px-2 py-1 text-xs">← Prev</button>
        <p className="font-semibold">{new Date(day + 'T00:00:00').toLocaleDateString('en-PH', { weekday: 'long', month: 'long', day: 'numeric' })}</p>
        <button onClick={() => onDay(shiftDay(day, 1))} className="rounded-lg border border-[var(--border)] px-2 py-1 text-xs">Next →</button>
      </div>
      {items.length === 0 ? <p className="text-sm text-[var(--text-muted)]">Nothing due — enjoy the quiet.</p> : (
        <ul className="flex flex-col gap-2 text-sm">
          {items.map((r) => (
            <li key={r.id} className="border-b border-[var(--border)] pb-1 last:border-0">
              <DraggableItem r={r} />
              <br /><span className="text-xs text-[var(--text-muted)]">{fmtDT(r.due_at)} · {r.status}</span>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}

function MonthCalendar({ rows, day, onDay, onDropDay }: { rows: Fup[]; day: string; onDay: (d: string) => void; onDropDay: (k: string) => (e: React.DragEvent) => void }) {
  const base = useMemo(() => new Date(day.slice(0, 7) + '-01T00:00:00'), [day]);
  const cells = useMemo(() => {
    const y = base.getFullYear();
    const m = base.getMonth();
    const first = new Date(y, m, 1).getDay();
    const days = new Date(y, m + 1, 0).getDate();
    const out: (Date | null)[] = [...Array(first).fill(null)];
    for (let d = 1; d <= days; d++) out.push(new Date(y, m, d));
    return out;
  }, [base]);
  const key = (d: Date) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
  const forDay = (k: string) => rows.filter((r) => (r.due_at ?? '').slice(0, 10) === k && r.status !== 'done');
  const selected = forDay(day);

  return (
    <div className="flex flex-col gap-3">
      <div className="card p-3">
        <div className="mb-2 flex items-center justify-between">
          <button onClick={() => onDay(shiftMonth(day, -1))} className="rounded-lg border border-[var(--border)] px-2 py-1 text-xs" aria-label="Previous month">← Prev</button>
          <p className="font-semibold">{base.toLocaleString('en-PH', { month: 'long', year: 'numeric' })}</p>
          <div className="flex gap-1.5">
            <button onClick={() => onDay(dayKey(new Date()))} className="rounded-lg border border-[var(--border)] px-2 py-1 text-xs">Today</button>
            <button onClick={() => onDay(shiftMonth(day, 1))} className="rounded-lg border border-[var(--border)] px-2 py-1 text-xs" aria-label="Next month">Next →</button>
          </div>
        </div>
        <div className="grid grid-cols-7 gap-1 text-center text-[11px] text-[var(--text-muted)]">
          {['S', 'M', 'T', 'W', 'T', 'F', 'S'].map((d, i) => <span key={i}>{d}</span>)}
          {cells.map((d, i) =>
            d === null ? <span key={i} /> : (
              <button key={i} onClick={() => onDay(key(d))} onDragOver={(e) => e.preventDefault()} onDrop={onDropDay(key(d))}
                className={`rounded-lg py-1.5 ${key(d) === day ? 'bg-sky-600 text-white' : 'hover:bg-slate-100 dark:hover:bg-slate-800'}`}>
                {d.getDate()}
                {forDay(key(d)).length > 0 && <span className="mx-auto mt-0.5 block h-1 w-1 rounded-full bg-amber-500" />}
              </button>
            )
          )}
        </div>
      </div>
      <section aria-label={`Due on ${day}`} className="card p-4">
        <h2 className="font-semibold">
          {new Date(day + 'T00:00:00').toLocaleDateString('en-PH', { weekday: 'long', month: 'long', day: 'numeric' })}
          <span className="ml-2 text-xs font-normal text-[var(--text-muted)]">{selected.length} due</span>
        </h2>
        {selected.length === 0 ? <p className="mt-2 text-sm text-[var(--text-muted)]">Nothing due — enjoy the quiet.</p> : (
          <ul className="mt-2 grid gap-2 text-sm md:grid-cols-2">
            {selected.map((r) => (
              <li key={r.id} className="rounded-lg border border-[var(--border)] p-2.5">
                <p className="font-medium">{r.title}</p>
                <p className="mt-0.5 text-xs text-[var(--text-muted)]">{r.client_name ?? ''} · {fmtDT(r.due_at)} · {r.status} · {r.priority} priority</p>
              </li>
            ))}
          </ul>
        )}
      </section>
    </div>
  );
}

function NewReminderForm({ onClose, onDone }: { onClose: () => void; onDone: () => void }) {
  const toast = useToast();
  const { user } = useSession();
  const canAssign = hasRole(user, 'admin', 'manager');
  const clientsQ = useQuery({
    queryKey: ['clients-mini'],
    queryFn: async () => (await api.get('/clients', { params: { per_page: 100 } })).data.data as { id: string; name: string }[],
  });
  const repsQ = useQuery({
    queryKey: ['users', 'sales-reps'],
    queryFn: async () => (await api.get('/users', { params: { role: 'sales_rep', per_page: 100 } })).data.data as { id: number; name: string }[],
    enabled: canAssign,
  });
  const [f, setF] = useState({ client_id: '', title: '', due: '', priority: 'medium', owner_id: '' });
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState('');
  const set = (k: keyof typeof f) => (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => setF({ ...f, [k]: e.target.value });

  const submit = async (ev: React.FormEvent) => {
    ev.preventDefault();
    setBusy(true);
    setErr('');
    try {
      await api.post('/followups', {
        client_id: f.client_id, title: f.title, due_at: new Date(f.due).toISOString(), priority: f.priority,
        ...(canAssign && f.owner_id ? { owner_id: Number(f.owner_id) } : {}),
      });
      toast('success', "Reminder set — we'll notify you.");
      onDone();
      onClose();
    } catch (e) {
      setErr(apiErr(e, 'Could not set reminder (must be in the future).'));
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" role="dialog" aria-modal="true">
      <form onSubmit={submit} className="card w-full max-w-md p-6">
        <h2 className="text-lg font-semibold">New reminder</h2>
        <p className="mb-2 text-xs text-[var(--text-muted)]">Due alerts fire within the hour; overdue escalates after 72h.</p>
        <div className="flex flex-col gap-2 text-sm">
          <label>Client *<select required value={f.client_id} onChange={set('client_id')} className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2">
            <option value="">Pick a client…</option>
            {clientsQ.data?.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
          </select></label>
          <label>What to do *<input required value={f.title} onChange={set('title')} placeholder="e.g. Call back about quotation" className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2" /></label>
          <label>Due *<input required type="datetime-local" value={f.due} onChange={set('due')} className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2" /></label>
          <label>Priority<select value={f.priority} onChange={set('priority')} className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2">
            <option value="low">Low</option><option value="medium">Medium</option><option value="high">High</option>
          </select></label>
          {canAssign && (
            <label>Assign to<select value={f.owner_id} onChange={set('owner_id')} className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2">
              <option value="">Account owner (default)</option>
              {(repsQ.data ?? []).map((r) => <option key={r.id} value={r.id}>{r.name}</option>)}
            </select></label>
          )}
        </div>
        {err && <p className="mt-2 text-sm text-red-600">{err}</p>}
        <div className="mt-4 flex justify-end gap-2">
          <button type="button" onClick={onClose} className="rounded-lg border border-[var(--border)] px-4 py-2 text-sm">Cancel</button>
          <button disabled={busy} className="rounded-lg bg-sky-600 px-4 py-2 text-sm text-white disabled:opacity-50">{busy ? 'Saving…' : 'Set reminder'}</button>
        </div>
      </form>
    </div>
  );
}

function apiErr(e: unknown, fallback: string): string {
  if (typeof e === 'object' && e !== null && 'response' in e) {
    const r = (e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }).response;
    if (r?.data?.errors) return Object.values(r.data.errors).flat().join(' ');
    if (r?.data?.message) return r.data.message;
  }
  return fallback;
}
