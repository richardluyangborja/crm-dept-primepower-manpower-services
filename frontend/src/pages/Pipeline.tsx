import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import api from '../lib/apiClient';
import { formatPHP } from '../lib/format';
import { EmptyState } from '../components/ui/EmptyState';
import { useToast } from '../components/ui/Toaster';

interface Opp {
  id: number;
  client_id: number;
  client_name?: string;
  owner_id: number;
  title: string;
  stage: string;
  value_centavos: number;
  probability: number;
  weighted_centavos: number;
  expected_close_date: string | null;
  lost_reason: string | null;
  days_in_stage: number | null;
}

const STAGES = ['new', 'contacted', 'qualified', 'proposal', 'negotiation', 'won', 'lost'] as const;

function pesoToCentavos(v: string): number {
  return Math.round((parseFloat(v) || 0) * 100);
}

export function PipelinePage() {
  const [q, setQ] = useState('');
  const [detailId, setDetailId] = useState<number | null>(null);
  const [showNew, setShowNew] = useState(false);
  const [lostId, setLostId] = useState<number | null>(null);
  const [dragId, setDragId] = useState<number | null>(null);
  const toast = useToast();
  const qc = useQueryClient();

  const oppsQ = useQuery({
    queryKey: ['opportunities', q],
    queryFn: async () => (await api.get('/opportunities', { params: { q: q || undefined, per_page: 100 } })).data.data as Opp[],
  });
  const dashQ = useQuery({
    queryKey: ['dashboard'],
    queryFn: async () => (await api.get('/dashboard/summary')).data.data,
  });

  const rows = oppsQ.data ?? [];
  const byStage = (s: string) => rows.filter((r) => r.stage === s);
  const openVal = rows.filter((r) => !['won', 'lost'].includes(r.stage)).reduce((a, r) => a + r.value_centavos, 0);

  const moveMut = useMutation({
    mutationFn: async ({ id, stage, lost_reason }: { id: number; stage: string; lost_reason?: string }) =>
      (await api.post(`/opportunities/${id}/move`, { stage, lost_reason })).data,
    onMutate: async ({ id, stage }) => {
      await qc.cancelQueries({ queryKey: ['opportunities'] });
      const prev = qc.getQueryData<Opp[]>(['opportunities', q]);
      qc.setQueryData<Opp[]>(['opportunities', q], (old) => old?.map((o) => (o.id === id ? { ...o, stage } : o)) ?? old);
      return { prev };
    },
    onError: (e, _v, ctx) => {
      if (ctx?.prev) qc.setQueryData(['opportunities', q], ctx.prev);
      toast('error', apiErr(e, 'Move failed — reverted.'));
    },
    onSuccess: (d) => toast('success', d.message ?? 'Moved.'),
    onSettled: () => {
      qc.invalidateQueries({ queryKey: ['opportunities'] });
      qc.invalidateQueries({ queryKey: ['dashboard'] });
    },
  });

  const [wonInfo, setWonInfo] = useState<{ ref: string; clientId: number } | null>(null);
  const winMut = useMutation({
    mutationFn: async (id: number) => (await api.post(`/opportunities/${id}/win`)).data,
    onSuccess: async (d, id) => {
      toast('success', d.message ?? 'Won!');
      qc.invalidateQueries({ queryKey: ['opportunities'] });
      qc.invalidateQueries({ queryKey: ['dashboard'] });
      // Narrate the handoff: fetch the freshly persisted mock job order.
      try {
        const opp = rows.find((o) => o.id === id);
        if (opp) {
          const jobs = (await api.get('/job-orders', { params: { client_id: opp.client_id, per_page: 50 } })).data.data as { ref: string; opportunity_id: number | null }[];
          const mine = jobs.find((j) => j.opportunity_id === id) ?? jobs[0];
          if (mine) setWonInfo({ ref: mine.ref, clientId: opp.client_id });
        }
      } catch {
        // Narration is best-effort; the win itself succeeded.
      }
    },
    onError: (e) => toast('error', apiErr(e, 'Could not mark as won.')),
  });

  const detail = rows.find((r) => r.id === detailId) ?? null;

  return (
    <div className="flex flex-col gap-4">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-xl font-bold">Opportunity Pipeline</h1>
          <p className="text-sm text-[var(--text-muted)]">Drag cards between stages. Terminal moves ask for win/loss details.</p>
        </div>
        <button onClick={() => setShowNew(true)} className="rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white">+ New deal</button>
      </div>

      {wonInfo && (
        <div className="card border-l-4 border-l-green-500 p-4">
          <p className="font-semibold text-green-700 dark:text-green-400">
            🎉 Won! Job Order {wonInfo.ref} created — staffing starts <span className="text-xs font-normal">(mock)</span>.
          </p>
          <div className="mt-2 flex gap-2">
            <Link to={`/leads?client=${wonInfo.clientId}`} className="rounded-lg bg-green-600 px-3 py-1.5 text-xs text-white">
              View client timeline →
            </Link>
            <button onClick={() => setWonInfo(null)} className="rounded-lg border border-[var(--border)] px-3 py-1.5 text-xs">Dismiss</button>
          </div>
        </div>
      )}

      <div className="grid grid-cols-2 gap-3 xl:grid-cols-4">
        <div className="card p-4"><p className="text-xs uppercase text-[var(--text-muted)]">Open pipeline</p><p className="text-2xl font-bold tabular-nums">{formatPHP(openVal)}</p></div>
        <div className="card p-4"><p className="text-xs uppercase text-[var(--text-muted)]">Weighted forecast</p><p className="text-2xl font-bold tabular-nums">{dashQ.data ? formatPHP(dashQ.data.forecast.weighted_centavos) : '…'}</p></div>
        <div className="card p-4"><p className="text-xs uppercase text-[var(--text-muted)]">Won</p><p className="text-2xl font-bold tabular-nums text-green-600">{byStage('won').length}</p></div>
        <div className="card p-4"><p className="text-xs uppercase text-[var(--text-muted)]">Lost</p><p className="text-2xl font-bold tabular-nums text-slate-500">{byStage('lost').length}</p></div>
      </div>

      <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Search deals…" className="card max-w-md px-3 py-2 text-sm outline-none" />

      {oppsQ.isLoading ? <p className="text-sm text-[var(--text-muted)]">Loading pipeline…</p>
        : oppsQ.isError ? <div className="card p-6 text-sm">Couldn't load pipeline. <button className="text-sky-600 underline" onClick={() => oppsQ.refetch()}>Retry</button></div>
        : rows.length === 0 ? <EmptyState title="Pipeline is empty" hint="Create your first deal — pick a client, name it, set a peso value." action={<button onClick={() => setShowNew(true)} className="mt-2 rounded-lg bg-sky-600 px-4 py-2 text-sm text-white">+ New deal</button>} />
        : (
          <div className="flex gap-3 overflow-x-auto pb-2">
            {STAGES.map((s) => {
              const col = byStage(s);
              const sum = col.reduce((a, r) => a + r.value_centavos, 0);
              return (
                <div key={s} onDragOver={(e) => e.preventDefault()} onDrop={() => { if (dragId !== null) {
                  if (s === 'lost') { setLostId(dragId); setDragId(null); }
                  else moveMut.mutate({ id: dragId, stage: s });
                  setDragId(null);
                } }} className="w-64 shrink-0 rounded-xl border border-[var(--border)] bg-[var(--bg-card)] p-2">
                  <div className="flex items-center justify-between px-1 py-1">
                    <p className="text-xs font-bold uppercase">{s} <span className="text-[var(--text-muted)]">{col.length}</span></p>
                    <p className="text-[11px] tabular-nums text-[var(--text-muted)]">{formatPHP(sum)}</p>
                  </div>
                  <div className="flex flex-col gap-2">
                    {col.map((o) => <OppCard key={o.id} o={o} onOpen={() => setDetailId(o.id)} onDrag={() => setDragId(o.id)} />)}
                    {col.length === 0 && <p className="px-1 py-4 text-center text-xs text-[var(--text-muted)]">Drop here</p>}
                  </div>
                </div>
              );
            })}
          </div>
        )}

      {detail && (
        <div className="card p-4">
          <div className="flex items-center justify-between">
            <h2 className="font-semibold">{detail.title}</h2>
            <button onClick={() => setDetailId(null)} className="text-sm text-[var(--text-muted)]">Close ✕</button>
          </div>
          <StageStepper stage={detail.stage} />
          <div className="mt-2 grid grid-cols-2 gap-2 text-sm md:grid-cols-4">
            <div><p className="text-xs text-[var(--text-muted)]">Client</p><p>{detail.client_name ?? `#${detail.client_id}`}</p></div>
            <div><p className="text-xs text-[var(--text-muted)]">Value</p><p className="tabular-nums">{formatPHP(detail.value_centavos)} × {detail.probability}%</p></div>
            <div><p className="text-xs text-[var(--text-muted)]">Expected close</p><p>{detail.expected_close_date ?? '—'}</p></div>
            <div><p className="text-xs text-[var(--text-muted)]">Lost reason</p><p>{detail.lost_reason ?? '—'}</p></div>
          </div>
          <div className="mt-3 flex gap-2">
            {detail.stage !== 'won' && <button onClick={() => { winMut.mutate(detail.id); }} className="rounded-lg bg-green-600 px-4 py-1.5 text-sm text-white">Mark won</button>}
            {detail.stage !== 'lost' && <button onClick={() => { setDetailId(null); setLostId(detail.id); }} className="rounded-lg border border-[var(--border)] px-4 py-1.5 text-sm">Mark lost…</button>}
          </div>
        </div>
      )}

      {lostId !== null && <LostModal onClose={() => setLostId(null)} onDone={(reason) => { moveMut.mutate({ id: lostId, stage: 'lost', lost_reason: reason }); setLostId(null); }} />}
      {showNew && <NewOppForm onClose={() => setShowNew(false)} onDone={() => { qc.invalidateQueries({ queryKey: ['opportunities'] }); qc.invalidateQueries({ queryKey: ['dashboard'] }); }} />}
    </div>
  );
}

function OppCard({ o, onOpen, onDrag }: { o: Opp; onOpen: () => void; onDrag: () => void }) {
  const stale = (o.days_in_stage ?? 0) > 60 ? 'text-red-600' : (o.days_in_stage ?? 0) > 30 ? 'text-amber-600' : 'text-[var(--text-muted)]';
  return (
    <div draggable onDragStart={onDrag} onClick={onOpen} className="cursor-grab rounded-lg border border-[var(--border)] bg-[var(--bg-app)] p-2.5 active:cursor-grabbing">
      <p className="text-sm font-medium">{o.title}</p>
      <p className="truncate text-xs text-[var(--text-muted)]">{o.client_name ?? ''}</p>
      <div className="mt-1 flex items-center justify-between text-xs">
        <span className="font-semibold tabular-nums">{formatPHP(o.value_centavos)}</span>
        <span className="rounded-full bg-sky-100 px-1.5 text-[11px] text-sky-800">{o.probability}%</span>
      </div>
      <p className={`mt-0.5 text-[11px] ${stale}`}>{o.days_in_stage ?? 0}d in stage</p>
    </div>
  );
}

function StageStepper({ stage }: { stage: string }) {
  const open = ['new', 'contacted', 'qualified', 'proposal', 'negotiation'];
  if (stage === 'won' || stage === 'lost') return <p className="mt-2 text-sm font-semibold">{stage === 'won' ? '🎉 Won' : 'Lost'}</p>;
  const idx = open.indexOf(stage);
  return (
    <div className="mt-2 flex items-center gap-1" aria-label={`Stage ${stage}`}>
      {open.map((s, i) => (
        <span key={s} title={s} className={`h-1.5 flex-1 rounded ${i <= idx ? 'bg-sky-500' : 'bg-slate-200 dark:bg-slate-700'}`} />
      ))}
    </div>
  );
}

function LostModal({ onClose, onDone }: { onClose: () => void; onDone: (reason: string) => void }) {
  const [reason, setReason] = useState('');
  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" role="dialog" aria-modal="true">
      <div className="card w-full max-w-md p-6">
        <h2 className="text-lg font-semibold">Why was this lost?</h2>
        <p className="mb-2 text-xs text-[var(--text-muted)]">Required — it powers win/loss analytics.</p>
        <textarea value={reason} onChange={(e) => setReason(e.target.value)} rows={3} placeholder="e.g. Chose competitor pricing" className="w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2 text-sm" />
        <div className="mt-3 flex justify-end gap-2">
          <button onClick={onClose} className="rounded-lg border border-[var(--border)] px-4 py-2 text-sm">Cancel</button>
          <button disabled={!reason.trim()} onClick={() => onDone(reason.trim())} className="rounded-lg bg-sky-600 px-4 py-2 text-sm text-white disabled:opacity-50">Mark lost</button>
        </div>
      </div>
    </div>
  );
}

function NewOppForm({ onClose, onDone }: { onClose: () => void; onDone: () => void }) {
  const toast = useToast();
  const clientsQ = useQuery({
    queryKey: ['clients-mini'],
    queryFn: async () => (await api.get('/clients', { params: { per_page: 100 } })).data.data as { id: number; name: string }[],
  });
  const [f, setF] = useState({ client_id: '', title: '', value: '', expected_close_date: '' });
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState('');
  const set = (k: keyof typeof f) => (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => setF({ ...f, [k]: e.target.value });

  const submit = async (ev: React.FormEvent) => {
    ev.preventDefault();
    setBusy(true);
    setErr('');
    try {
      await api.post('/opportunities', {
        client_id: Number(f.client_id), title: f.title,
        value_centavos: pesoToCentavos(f.value),
        expected_close_date: f.expected_close_date || undefined,
      });
      toast('success', 'Deal created on the board.');
      onDone();
      onClose();
    } catch (e) {
      setErr(apiErr(e, 'Could not create deal.'));
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" role="dialog" aria-modal="true">
      <form onSubmit={submit} className="card w-full max-w-md p-6">
        <h2 className="text-lg font-semibold">New deal</h2>
        <div className="mt-2 flex flex-col gap-2 text-sm">
          <label>Client *<select required value={f.client_id} onChange={set('client_id')} className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2">
            <option value="">Pick a client…</option>
            {clientsQ.data?.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
          </select></label>
          <label>Title *<input required value={f.title} onChange={set('title')} placeholder="e.g. 80 guards — Davao Prime" className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2" /></label>
          <label>Value (₱)<input value={f.value} onChange={set('value')} inputMode="decimal" placeholder="2400000" className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2" /></label>
          <label>Expected close<input type="date" value={f.expected_close_date} onChange={set('expected_close_date')} className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2" /></label>
        </div>
        {err && <p className="mt-2 text-sm text-red-600">{err}</p>}
        <div className="mt-4 flex justify-end gap-2">
          <button type="button" onClick={onClose} className="rounded-lg border border-[var(--border)] px-4 py-2 text-sm">Cancel</button>
          <button disabled={busy} className="rounded-lg bg-sky-600 px-4 py-2 text-sm text-white disabled:opacity-50">{busy ? 'Saving…' : 'Create deal'}</button>
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
