import { useEffect, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import api from '../lib/apiClient';
import { formatPHP } from '../lib/format';
import { EmptyState } from '../components/ui/EmptyState';
import { useToast } from '../components/ui/Toaster';
import { StageUpModal, defaultDue, type RitualOpp, type RitualPayload } from '../components/crm/StageUpModal';

interface Opp {
  id: string;
  client_id: string;
  client_name?: string;
  owner_id: number;
  title: string;
  stage: string;
  value_centavos: number;
  headcount: number | null;
  rate_per_head_centavos: number | null;
  contract_months: number | null;
  monthly_billing_centavos: number | null;
  contract_total_centavos: number | null;
  probability: number;
  weighted_centavos: number;
  expected_close_date: string | null;
  lost_reason: string | null;
  days_in_stage: number | null;
}

const STAGES = ['new', 'contacted', 'qualified', 'proposal', 'negotiation', 'contract', 'won', 'lost'] as const;

/** Admin-configurable stage labels (Settings → Master data); keys stay fixed for logic. */
function useStageLabels(): Record<string, string> {
  const q = useQuery({
    queryKey: ['settings'],
    queryFn: async () => (await api.get('/settings')).data.data as Record<string, unknown>,
    staleTime: 60000,
  });
  const list = Array.isArray(q.data?.pipeline_stages) ? (q.data.pipeline_stages as { key: string; label: string }[]) : [];
  const map: Record<string, string> = {};
  for (const s of STAGES) map[s] = s;
  for (const s of list) {
    if (s.key && s.label) map[s.key] = s.label;
  }
  return map;
}

function useLostReasons(): string[] {
  const q = useQuery({
    queryKey: ['settings'],
    queryFn: async () => (await api.get('/settings')).data.data as Record<string, unknown>,
    staleTime: 60000,
  });
  const list = q.data?.lost_reasons;
  return Array.isArray(list) ? list.filter((x): x is string => typeof x === 'string') : [];
}

function pesoToCentavos(v: string): number {
  return Math.round((parseFloat(v) || 0) * 100);
}

export function PipelinePage() {
  const [q, setQ] = useState('');
  const [detailId, setDetailId] = useState<string | null>(null);
  const [lostId, setLostId] = useState<string | null>(null);
  const [dragId, setDragId] = useState<string | null>(null);
  const toast = useToast();
  const qc = useQueryClient();
  const [params] = useSearchParams();
  // Deep link from a client timeline: /pipeline?client=<id> opens the form prefilled.
  const preselectClient = params.get('client') ?? '';
  const [showNew, setShowNew] = useState(preselectClient !== '');

  const oppsQ = useQuery({
    queryKey: ['opportunities', q],
    queryFn: async () => (await api.get('/opportunities', { params: { q: q || undefined, per_page: 100 } })).data.data as Opp[],
  });
  const dashQ = useQuery({
    queryKey: ['dashboard'],
    queryFn: async () => (await api.get('/dashboard/summary')).data.data,
  });

  const rows = oppsQ.data ?? [];
  const labels = useStageLabels();
  const byStage = (s: string) => rows.filter((r) => r.stage === s);
  const openVal = rows.filter((r) => !['won', 'lost'].includes(r.stage)).reduce((a, r) => a + r.value_centavos, 0);

  const [contractId, setContractId] = useState<string | null>(null);
  const [ritual, setRitual] = useState<{ id: string; stage: string } | null>(null);
  const moveMut = useMutation({
    mutationFn: async ({ id, stage, lost_reason, effective_date, headcount, rate_per_head_centavos, contract_months, start_date, reopen_note, probability }: {
      id: string; stage: string; lost_reason?: string; effective_date?: string;
      headcount?: number; rate_per_head_centavos?: number; contract_months?: number; start_date?: string;
      reopen_note?: string; probability?: number;
    }) =>
      (await api.post(`/opportunities/${id}/move`, { stage, lost_reason, effective_date, headcount, rate_per_head_centavos, contract_months, start_date, reopen_note, probability })).data,
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
    onSuccess: (d, vars) => {
      const moved = (qc.getQueryData<Opp[]>(['opportunities', q]) ?? rows).find((o) => o.id === vars.id);
      toast('success', {
        title: d.message ?? 'Moved.',
        ...(moved ? { action: { label: 'Open client', href: `/clients/${moved.client_id}` } } : {}),
      });
    },
    onSettled: () => {
      qc.invalidateQueries({ queryKey: ['opportunities'] });
      qc.invalidateQueries({ queryKey: ['dashboard'] });
    },
  });

  /** Fire the ritual side-effects: touchpoint → history, reminder → follow-ups. */
  const fireRitual = async (opp: Opp, payload: RitualPayload) => {
    if (payload.touch) {
      const label = labels[ritual?.stage ?? ''] ?? ritual?.stage ?? '';
      await api.post('/activities', {
        client_id: opp.client_id,
        opportunity_id: opp.id,
        type: payload.touch.type,
        subject: `${label} touch — ${opp.title}`,
        body: payload.touch.notes,
        outcome: payload.touch.outcome,
      });
    }
    if (payload.followup) {
      await api.post('/followups', {
        client_id: opp.client_id,
        opportunity_id: opp.id,
        title: payload.followup.title,
        due_at: new Date(payload.followup.due).toISOString(),
      });
    }
  };

  const [wonInfo, setWonInfo] = useState<{ ref: string; clientId: string; monthly: number | null; total: number | null } | null>(null);
  const [wonId, setWonId] = useState<string | null>(null);
  const winMut = useMutation({
    mutationFn: async ({ id, effective_date }: { id: string; effective_date?: string }) =>
      (await api.post(`/opportunities/${id}/win`, effective_date ? { effective_date } : {})).data,
    onSuccess: async (d, vars) => {
      toast('success', d.message ?? 'Won!');
      qc.invalidateQueries({ queryKey: ['opportunities'] });
      qc.invalidateQueries({ queryKey: ['dashboard'] });
      // Narrate the handoff: fetch the freshly persisted mock job order + terms.
      try {
        const opp = rows.find((o) => o.id === vars.id);
        if (opp) {
          const jobs = (await api.get('/job-orders', { params: { client_id: opp.client_id, per_page: 50 } })).data.data as { ref: string; opportunity_id: string | null }[];
          const mine = jobs.find((j) => j.opportunity_id === vars.id) ?? jobs[0];
          const fresh = (await api.get(`/opportunities/${vars.id}`)).data.data as { monthly_billing_centavos: number | null; contract_total_centavos: number | null };
          if (mine) setWonInfo({ ref: mine.ref, clientId: opp.client_id, monthly: fresh.monthly_billing_centavos, total: fresh.contract_total_centavos });
        }
      } catch {
        // Narration is best-effort; the win itself succeeded.
      }
    },
    onError: (e) => toast('error', apiErr(e, 'Could not mark as won.')),
  });

  const detail = rows.find((r) => r.id === detailId) ?? null;

  /** P6 · contract-first: winning without a signed contract reroutes to signing. */
  const beginWin = async (oppId: string) => {
    const opp = rows.find((r) => r.id === oppId);
    if (!opp) return;
    try {
      const contracts = (await api.get('/contracts', { params: { client_id: opp.client_id, per_page: 100 } })).data.data as
        { opportunity_id: string | null; status: string }[];
      const signed = contracts.some((c) => c.opportunity_id === oppId && c.status === 'active');
      if (!signed) {
        toast('info', {
          title: 'Sign the contract first.',
          body: 'Winning needs agreed terms on record — signing takes seconds, then mark won.',
        });
        setDetailId(null);
        setContractId(oppId);
        return;
      }
    } catch {
      // Contract check failed — let the server guard decide on win.
    }
    setWonId(oppId);
  };

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
            Won! Job Order {wonInfo.ref} created — staffing starts.
            {wonInfo.monthly !== null && (
              <span className="block text-sm font-normal tabular-nums">
                First invoice {formatPHP(wonInfo.monthly)}/mo{wonInfo.total !== null ? ` · ${formatPHP(wonInfo.total)} contract total` : ''}
              </span>
            )}
          </p>
          <div className="mt-2 flex flex-wrap gap-2">
            <Link to={`/clients/${wonInfo.clientId}`} className="rounded-lg bg-green-600 px-3 py-1.5 text-xs text-white">
              View client timeline →
            </Link>
            <Link to="/surveys" className="rounded-lg border border-[var(--border)] px-3 py-1.5 text-xs">
              Send satisfaction survey →
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
              const monthly = col.reduce((a, r) => a + (r.monthly_billing_centavos ?? 0), 0);
              return (
                <div key={s} onDragOver={(e) => e.preventDefault()} onDrop={() => { if (dragId !== null) {
                  const from = rows.find((r) => r.id === dragId)?.stage;
                  if (s === 'lost') { setLostId(dragId); setDragId(null); }
                  else if (s === 'contract') { setContractId(dragId); setDragId(null); }
                  else if (s === 'won') { beginWin(dragId); setDragId(null); }
                  else if (from && s !== from) { setRitual({ id: dragId, stage: s }); setDragId(null); }
                  else setDragId(null);
                } }} className="w-64 shrink-0 rounded-xl border border-[var(--border)] bg-[var(--bg-card)] p-2">
                  <div className="flex items-center justify-between px-1 py-1">
                    <p className="text-xs font-bold uppercase">{labels[s]} <span className="text-[var(--text-muted)]">{col.length}</span></p>
                    <p className="text-[11px] tabular-nums text-[var(--text-muted)]" title={monthly > 0 ? `${formatPHP(monthly)}/mo expected billing` : undefined}>{formatPHP(sum)}{monthly > 0 ? ` · ${formatPHP(monthly)}/mo` : ''}</p>
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
            <button onClick={() => setDetailId(null)} className="text-sm text-[var(--text-muted)]"aria-label="Close">Close</button>
          </div>
          <StageStepper stage={detail.stage} />
          <div className="mt-2 grid grid-cols-2 gap-2 text-sm md:grid-cols-4">
            <div><p className="text-xs text-[var(--text-muted)]">Client</p><p>{detail.client_name ?? `#${detail.client_id}`}</p></div>
            <div><p className="text-xs text-[var(--text-muted)]">Value</p><p className="tabular-nums">{formatPHP(detail.value_centavos)} × {detail.probability}%</p></div>
            <div><p className="text-xs text-[var(--text-muted)]">Billing</p><p className="tabular-nums">{detail.monthly_billing_centavos ? `${formatPHP(detail.monthly_billing_centavos)}/mo × ${detail.contract_months ?? '?'} mo` : 'Terms not set'}</p></div>
            <div><p className="text-xs text-[var(--text-muted)]">Expected close</p><p>{detail.expected_close_date ?? '—'}</p></div>
          </div>
          {(detail.headcount !== null || detail.stage === 'contract') && (
            <p className="mt-1 text-xs text-[var(--text-muted)]">
              Terms: {detail.headcount ?? '?'} heads
              {detail.rate_per_head_centavos !== null ? ` × ${formatPHP(detail.rate_per_head_centavos)}/mo` : ''}
              {detail.contract_months ? ` × ${detail.contract_months} mo` : ''}
              {detail.contract_total_centavos ? ` = ${formatPHP(detail.contract_total_centavos)} total` : ''}
            </p>
          )}
          <div className="mt-3 flex gap-2">
            {detail.stage !== 'contract' && detail.stage !== 'won' && detail.stage !== 'lost' && (
              <button onClick={() => { setDetailId(null); setContractId(detail.id); }} className="rounded-lg border border-sky-600 px-4 py-1.5 text-sm text-sky-700 dark:text-sky-300">Sign contract…</button>
            )}
            {detail.stage !== 'won' && <button onClick={() => { beginWin(detail.id); }} className="rounded-lg bg-green-600 px-4 py-1.5 text-sm text-white">Mark won…</button>}
            {detail.stage !== 'lost' && <button onClick={() => { setDetailId(null); setLostId(detail.id); }} className="rounded-lg border border-[var(--border)] px-4 py-1.5 text-sm">Mark lost…</button>}
          </div>
        </div>
      )}

      {lostId !== null && (() => {
        const lost = rows.find((r) => r.id === lostId) ?? null;
        return (
          <LostModal
            lostValue={lost ? formatPHP(lost.value_centavos) : null}
            onClose={() => setLostId(null)}
            onDone={(reason, effectiveDate) => { moveMut.mutate({ id: lostId, stage: 'lost', lost_reason: reason, effective_date: effectiveDate }); setLostId(null); }}
          />
        );
      })()}
      {wonId !== null && <WinModal onClose={() => setWonId(null)} onDone={(effectiveDate) => { winMut.mutate({ id: wonId, effective_date: effectiveDate }); setWonId(null); }} />}
      {ritual !== null && (() => {
        const opp = rows.find((r) => r.id === ritual.id) ?? null;
        if (!opp) return null;
        return (
          <RitualDialog
            opp={opp}
            to={ritual.stage}
            labels={labels}
            onClose={() => setRitual(null)}
            onDone={async (extra, payload) => {
              const runMove = () => {
                moveMut.mutate({ id: opp.id, stage: ritual.stage, probability: extra.probability, reopen_note: extra.reopen_note });
                setRitual(null);
              };
              // P3 · quotation always logs the proposal as history.
              if (extra.proposal) {
                try {
                  await api.post('/activities', {
                    client_id: opp.client_id,
                    opportunity_id: opp.id,
                    type: 'email',
                    subject: `Proposal sent — ${opp.title}`,
                    body: `Quoted ${formatPHP(extra.proposal.value_centavos)} on ${extra.proposal.sent_date}.`,
                    outcome: 'sent',
                  });
                } catch {
                  toast('error', 'Proposal log failed — add it to history manually.');
                }
              }
              // P4 · discussion note becomes history when no touch was logged.
              if (extra.negNote && !payload.touch) {
                payload = { ...payload, touch: { type: 'meeting', outcome: 'follow_up_needed', notes: extra.negNote } };
              }
              try {
                await fireRitual(opp, payload);
              } catch {
                toast('error', 'Move saved, but the log/follow-up failed — add it manually.');
              }
              if (extra.put) {
                try {
                  await api.put(`/opportunities/${opp.id}`, extra.put);
                } catch (e) {
                  toast('error', apiErr(e, 'Could not save terms — move cancelled.'));
                  return;
                }
              }
              runMove();
            }}
          />
        );
      })()}
      {contractId !== null && <ContractModal dealId={contractId} onClose={() => setContractId(null)} onDone={(terms) => { moveMut.mutate({ id: contractId, stage: 'contract', ...terms }); setContractId(null); }} />}
      {showNew && <NewOppForm initialClientId={preselectClient} onClose={() => setShowNew(false)} onDone={() => { qc.invalidateQueries({ queryKey: ['opportunities'] }); qc.invalidateQueries({ queryKey: ['dashboard'] }); }} />}
    </div>
  );
}

function OppCard({ o, onOpen, onDrag }: { o: Opp; onOpen: () => void; onDrag: () => void }) {
  const stale = (o.days_in_stage ?? 0) > 60 ? 'text-red-600' : (o.days_in_stage ?? 0) > 30 ? 'amber-600' : 'text-[var(--text-muted)]';
  const stop = (e: React.MouseEvent) => e.stopPropagation();
  return (
    <div draggable onDragStart={onDrag} onClick={onOpen} className="cursor-grab rounded-lg border border-[var(--border)] bg-[var(--bg-app)] p-2.5 active:cursor-grabbing">
      <p className="text-sm font-medium">{o.title}</p>
      <p className="truncate text-xs text-[var(--text-muted)]">{o.client_name ?? ''}</p>
      <div className="mt-1 flex items-center justify-between text-xs">
        <span className="font-semibold tabular-nums">{o.monthly_billing_centavos ? `${formatPHP(o.monthly_billing_centavos)}/mo` : formatPHP(o.value_centavos)}</span>
        <span className="rounded-full bg-sky-100 px-1.5 text-[11px] text-sky-800">{o.probability}%</span>
      </div>
      <p className={`mt-0.5 text-[11px] ${stale}`}>{o.days_in_stage ?? 0}d in stage</p>
      <p className="mt-1 flex gap-2 text-[11px]" onClick={stop}>
        <Link to={`/pipeline/finance?client=${o.client_id}`} className="text-sky-700 hover:underline dark:text-sky-300">Billing</Link>
        <Link to={`/pipeline/staffing?client=${o.client_id}`} className="text-sky-700 hover:underline dark:text-sky-300">Deployed staff</Link>
        <Link to={`/pipeline/contracts?client=${o.client_id}`} className="text-sky-700 hover:underline dark:text-sky-300">Contracts</Link>
      </p>
    </div>
  );
}

function StageStepper({ stage }: { stage: string }) {
  const labels = useStageLabels();
  const open = ['new', 'contacted', 'qualified', 'proposal', 'negotiation', 'contract'];
  if (stage === 'won' || stage === 'lost') return <p className="mt-2 text-sm font-semibold">{stage === 'won' ? 'Won' : 'Lost'}</p>;
  const idx = open.indexOf(stage);
  return (
    <div className="mt-2 flex items-center gap-1" aria-label={`Stage ${labels[stage] ?? stage}`}>
      {open.map((s, i) => (
        <span key={s} title={labels[s] ?? s} className={`h-1.5 flex-1 rounded ${i <= idx ? 'bg-sky-500' : 'bg-slate-200 dark:bg-slate-700'}`} />
      ))}
    </div>
  );
}

const OPEN_FLOW = ['new', 'contacted', 'qualified', 'proposal', 'negotiation', 'contract'];

/** Per-stage level-up dialog (specs/05 rituals). Stage extras plug in per phase. */
interface RitualExtra {
  put?: { headcount?: number; rate_per_head_centavos?: number; contract_months?: number; value_centavos?: number; expected_close_date?: string; probability?: number };
  probability?: number;
  reopen_note?: string;
  proposal?: { value_centavos: number; sent_date: string };
  negNote?: string;
}

function RitualDialog({ opp, to, labels, onClose, onDone }: {
  opp: Opp;
  to: string;
  labels: Record<string, string>;
  onClose: () => void;
  onDone: (extra: RitualExtra, payload: RitualPayload) => void;
}) {
  const fromIdx = OPEN_FLOW.indexOf(opp.stage);
  const toIdx = OPEN_FLOW.indexOf(to);
  const backward = (opp.stage === 'won' || opp.stage === 'lost') || (fromIdx >= 0 && toIdx >= 0 && toIdx < fromIdx);
  const fromTerminal = opp.stage === 'won' || opp.stage === 'lost';
  const [reopenNote, setReopenNote] = useState('');
  const [reopenErr, setReopenErr] = useState('');

  // Fresh detail (Phase B): prefill from the server record, not the possibly
  // stale board row (optimistic updates only patch `stage`).
  const detailQ = useQuery({
    queryKey: ['opportunity', opp.id],
    queryFn: async () => (await api.get(`/opportunities/${opp.id}`)).data.data as Opp,
    staleTime: 30000,
  });
  const src = detailQ.data ?? opp;
  // P2 · qualified: terms editor (heads/rate/months + value + expected close).
  const [heads, setHeads] = useState('');
  const [rate, setRate] = useState('');
  const [months, setMonths] = useState('12');
  const [value, setValue] = useState('');
  const [closeDate, setCloseDate] = useState('');
  useEffect(() => {
    if (src.headcount) setHeads(String(src.headcount));
    if (src.rate_per_head_centavos) setRate(String(src.rate_per_head_centavos / 100));
    if (src.contract_months) setMonths(String(src.contract_months));
    if (src.value_centavos) setValue(String(src.value_centavos / 100));
    if (src.expected_close_date) setCloseDate(src.expected_close_date);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [src.id, detailQ.dataUpdatedAt]);
  // P3 · proposal: quoted value + sent date. P4 · negotiation: terms tweak + note.
  const [sentDate, setSentDate] = useState(new Date().toISOString().slice(0, 10));
  const [negNote, setNegNote] = useState('');
  const NEG_SUGGESTIONS = ['Pushing on rate', 'Decision on Friday', 'Needs HO approval', 'Competitor in play', 'Waiting on budget release'];
  const monthlyPreview = (Number(heads) || 0) * pesoToCentavos(rate || '0');
  const termsValid = to !== 'qualified' || (
    pesoToCentavos(value || '0') > 0 && Number(heads) > 0 && pesoToCentavos(rate || '0') > 0 && Number(months) > 0
  );
  const proposalValid = to !== 'proposal' || (pesoToCentavos(value || '0') > 0 && !!sentDate);

  const confirm = (payload: RitualPayload) => {
    if (fromTerminal && !reopenNote.trim()) {
      setReopenErr('Reopening a closed deal needs a note — it stays on the record.');
      return;
    }
    if (!termsValid) return;
    const extra: RitualExtra = fromTerminal ? { reopen_note: reopenNote.trim() } : {};
    if (to === 'qualified') {
      extra.put = {
        headcount: Number(heads),
        rate_per_head_centavos: pesoToCentavos(rate),
        contract_months: Number(months),
        value_centavos: pesoToCentavos(value),
        expected_close_date: closeDate || undefined,
      };
    }
    if (to === 'proposal') {
      extra.put = { value_centavos: pesoToCentavos(value) };
      extra.proposal = { value_centavos: pesoToCentavos(value), sent_date: sentDate };
    }
    if (to === 'negotiation') {
      // Win chance is automatic (stage default) — no manual slider.
      if (Number(heads) > 0 && pesoToCentavos(rate || '0') > 0 && Number(months) > 0) {
        extra.put = {
          headcount: Number(heads),
          rate_per_head_centavos: pesoToCentavos(rate),
          contract_months: Number(months),
        };
      }
      if (negNote.trim()) extra.negNote = negNote.trim();
    }
    onDone(extra, payload);
  };

  return (
    <StageUpModal
      opp={(detailQ.data ?? opp) as RitualOpp}
      fromLabel={labels[opp.stage] ?? opp.stage}
      toLabel={labels[to] ?? to}
      backward={backward}
      confirmLabel={backward ? 'Move back' : `Move to ${labels[to] ?? to}`}
      touchDefault={to === 'contacted'}
      followupDefault={
        to === 'contacted' ? { title: `Follow up with ${opp.client_name ?? 'client'}`, due: defaultDue(3) }
        : to === 'proposal' ? { title: `Follow up on proposal — ${opp.title}`, due: defaultDue(3) }
        : undefined
      }
      extraValid={termsValid && proposalValid}
      onClose={onClose}
      onConfirm={confirm}
    >
      {to === 'qualified' && (
        <div className="mb-2 flex flex-col gap-2 text-sm">
          <p className="text-xs text-[var(--text-muted)]">Confirm the requirement — qualifying locks the money story. Value and terms are required from here on.</p>
          <div className="grid grid-cols-3 gap-2">
            <label>Heads *<input value={heads} onChange={(e) => setHeads(e.target.value)} inputMode="numeric" placeholder="40" className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2" /></label>
            <label>Rate/head/mo (₱) *<input value={rate} onChange={(e) => setRate(e.target.value)} inputMode="decimal" placeholder="15000" className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2" /></label>
            <label>Months *<input value={months} onChange={(e) => setMonths(e.target.value)} inputMode="numeric" placeholder="12" className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2" /></label>
          </div>
          <div className="grid grid-cols-2 gap-2">
            <label>Deal value (₱) *<input value={value} onChange={(e) => setValue(e.target.value)} inputMode="decimal" placeholder="2400000" className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2" /></label>
            <label>Expected close<input type="date" value={closeDate} onChange={(e) => setCloseDate(e.target.value)} className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2" /></label>
          </div>
          <p className="rounded-lg bg-slate-100 px-3 py-2 text-sm tabular-nums dark:bg-slate-800">
            {monthlyPreview > 0 ? `${formatPHP(monthlyPreview)}/mo` : 'Set heads + rate to preview monthly billing'}
          </p>
        </div>
      )}
      {to === 'proposal' && (
        <div className="mb-2 flex flex-col gap-2 text-sm">
          <p className="text-xs text-[var(--text-muted)]">Record the quotation — value syncs to the deal and a follow-up is booked automatically.</p>
          <div className="grid grid-cols-2 gap-2">
            <label>Quoted value (₱) *<input value={value} onChange={(e) => setValue(e.target.value)} inputMode="decimal" placeholder="2400000" className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2" /></label>
            <label>Sent date *<input type="date" value={sentDate} max={new Date().toISOString().slice(0, 10)} onChange={(e) => setSentDate(e.target.value)} className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2" /></label>
          </div>
          {opp.monthly_billing_centavos || monthlyPreview > 0 ? (
            <p className="rounded-lg bg-slate-100 px-3 py-2 text-sm tabular-nums dark:bg-slate-800">
              ≈ {formatPHP(opp.monthly_billing_centavos ?? monthlyPreview)}/mo in per-head terms
            </p>
          ) : null}
        </div>
      )}
      {to === 'negotiation' && (
        <div className="mb-2 flex flex-col gap-2 text-sm">
          <p className="text-xs text-[var(--text-muted)]">Take the temperature — the win chance updates automatically on Approval (currently {src.probability}%). Note what the client is pushing on.</p>
          <div className="grid grid-cols-3 gap-2">
            <label>Heads<input value={heads} onChange={(e) => setHeads(e.target.value)} inputMode="numeric" placeholder={src.headcount ? String(src.headcount) : '40'} className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2" /></label>
            <label>Rate/head/mo (₱)<input value={rate} onChange={(e) => setRate(e.target.value)} inputMode="decimal" placeholder={src.rate_per_head_centavos ? String(src.rate_per_head_centavos / 100) : '15000'} className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2" /></label>
            <label>Months<input value={months} onChange={(e) => setMonths(e.target.value)} inputMode="numeric" placeholder={src.contract_months ? String(src.contract_months) : '12'} className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2" /></label>
          </div>
          <div>
            <div className="mb-1.5 flex flex-wrap gap-1.5">
              {NEG_SUGGESTIONS.map((s) => (
                <button key={s} type="button" onClick={() => setNegNote((v) => (v ? (v.endsWith('.') || v.endsWith(',') ? `${v} ` : `${v}, `) : '') + s.toLowerCase())} className="rounded-full border border-[var(--border)] px-2.5 py-1 text-xs hover:bg-slate-100 dark:hover:bg-slate-800">
                  {s}
                </button>
              ))}
            </div>
            <label>Discussion note<textarea value={negNote} onChange={(e) => setNegNote(e.target.value)} rows={2} placeholder="e.g. Pushing on rate — wants ₱14k/head, decision Friday" className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2" /></label>
          </div>
        </div>
      )}
      {fromTerminal && (
        <label className="mb-2 block text-sm">Reopen note *
          <textarea value={reopenNote} onChange={(e) => setReopenNote(e.target.value)} rows={2} placeholder="Why is this deal back in play?" className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2" />
        </label>
      )}
      {reopenErr && <p className="mb-2 text-sm text-red-600">{reopenErr}</p>}
    </StageUpModal>
  );
}

function LostModal({ lostValue, onClose, onDone }: { lostValue: string | null; onClose: () => void; onDone: (reason: string, effectiveDate?: string) => void }) {
  const [reason, setReason] = useState('');
  const [date, setDate] = useState('');
  const suggestions = useLostReasons();
  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" role="dialog" aria-modal="true">
      <div className="card w-full max-w-md p-6">
        <h2 className="text-lg font-semibold">Why was this lost?</h2>
        <p className="mb-2 text-xs text-[var(--text-muted)]">
          {lostValue ? <>Walking away from <strong className="tabular-nums">{lostValue}</strong>. </> : ''}Required — the reason powers win/loss analytics. Pick a suggestion or write your own.
        </p>
        {suggestions.length > 0 && (
          <div className="mb-2 flex flex-wrap gap-1.5">
            {suggestions.map((s) => (
              <button key={s} type="button" onClick={() => setReason(s)} className={`rounded-full border px-2.5 py-1 text-xs ${reason === s ? 'border-sky-600 bg-sky-100 text-sky-900 dark:bg-sky-900/40 dark:text-sky-100' : 'border-[var(--border)]'}`}>
                {s}
              </button>
            ))}
          </div>
        )}
        <textarea value={reason} onChange={(e) => setReason(e.target.value)} rows={3} placeholder="e.g. Chose competitor pricing" className="w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2 text-sm" />
        <label className="mt-2 block text-sm">Effective date <span className="text-xs text-[var(--text-muted)]">(defaults to today)</span>
          <input type="date" value={date} max={new Date().toISOString().slice(0, 10)} onChange={(e) => setDate(e.target.value)} className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2" />
        </label>
        <div className="mt-3 flex justify-end gap-2">
          <button onClick={onClose} className="rounded-lg border border-[var(--border)] px-4 py-2 text-sm">Cancel</button>
          <button disabled={!reason.trim()} onClick={() => onDone(reason.trim(), date || undefined)} className="rounded-lg bg-sky-600 px-4 py-2 text-sm text-white disabled:opacity-50">Mark lost</button>
        </div>
      </div>
    </div>
  );
}

function ContractModal({ dealId, onClose, onDone }: {
  dealId: string;
  onClose: () => void;
  onDone: (terms: { headcount: number; rate_per_head_centavos: number; contract_months: number; start_date: string }) => void;
}) {
  const qc = useQueryClient();
  const dealQ = useQuery({
    queryKey: ['opportunity', dealId],
    queryFn: async () => (await api.get(`/opportunities/${dealId}`)).data.data as Opp,
  });
  const [headcount, setHeadcount] = useState('');
  const [rate, setRate] = useState('');
  const [months, setMonths] = useState('12');
  const [start, setStart] = useState(new Date().toISOString().slice(0, 10));
  const [err, setErr] = useState('');
  const d = dealQ.data;
  useEffect(() => {
    if (d) {
      if (d.headcount) setHeadcount(String(d.headcount));
      if (d.rate_per_head_centavos) setRate(String(d.rate_per_head_centavos / 100));
      if (d.contract_months) setMonths(String(d.contract_months));
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [d?.id]);
  const monthly = (Number(headcount) || 0) * pesoToCentavos(rate || '0');
  const valid = Number(headcount) > 0 && pesoToCentavos(rate || '0') > 0 && Number(months) > 0 && !!start;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" role="dialog" aria-modal="true">
      <div className="card w-full max-w-md p-6">
        <h2 className="text-lg font-semibold">Sign contract{d ? ` — ${d.title}` : ''}?</h2>
        <p className="mb-2 text-xs text-[var(--text-muted)]">Records the agreed terms as the contract. Winning starts from here.</p>
        <div className="flex flex-col gap-2 text-sm">
          <div className="grid grid-cols-3 gap-2">
            <label>Heads *<input value={headcount} onChange={(e) => setHeadcount(e.target.value)} inputMode="numeric" placeholder="40" className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2" /></label>
            <label>Rate/head/mo (₱) *<input value={rate} onChange={(e) => setRate(e.target.value)} inputMode="decimal" placeholder="15000" className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2" /></label>
            <label>Months *<input value={months} onChange={(e) => setMonths(e.target.value)} inputMode="numeric" placeholder="12" className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2" /></label>
          </div>
          <label>Start date *<input type="date" value={start} onChange={(e) => setStart(e.target.value)} className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2" /></label>
          <p className="rounded-lg bg-slate-100 px-3 py-2 text-sm tabular-nums dark:bg-slate-800">
            {formatPHP(monthly)}/mo{Number(months) > 0 ? ` × ${months} mo = ${formatPHP(monthly * Number(months))}` : ''} total
          </p>
        </div>
        {err && <p className="mt-2 text-sm text-red-600">{err}</p>}
        <div className="mt-4 flex justify-end gap-2">
          <button onClick={onClose} className="rounded-lg border border-[var(--border)] px-4 py-2 text-sm">Cancel</button>
          <button disabled={!valid} onClick={() => {
            const h = Number(headcount);
            const r = pesoToCentavos(rate);
            const m = Number(months);
            if (!(h > 0 && r > 0 && m > 0 && start)) { setErr('Heads, rate, months, and start date are all required.'); return; }
            onDone({ headcount: h, rate_per_head_centavos: r, contract_months: m, start_date: start });
            qc.invalidateQueries({ queryKey: ['opportunities'] });
          }} className="rounded-lg bg-sky-600 px-4 py-2 text-sm text-white disabled:opacity-50">Sign contract</button>
        </div>
      </div>
    </div>
  );
}

function WinModal({ onClose, onDone }: { onClose: () => void; onDone: (effectiveDate?: string) => void }) {
  const [date, setDate] = useState('');
  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" role="dialog" aria-modal="true">
      <div className="card w-full max-w-md p-6">
        <h2 className="text-lg font-semibold">Mark as won?</h2>
        <p className="mb-2 text-xs text-[var(--text-muted)]">This creates the job order and draft invoice, and starts staffing.</p>
        <label className="block text-sm">Effective close date <span className="text-xs text-[var(--text-muted)]">(defaults to today)</span>
          <input type="date" value={date} max={new Date().toISOString().slice(0, 10)} onChange={(e) => setDate(e.target.value)} className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2" />
        </label>
        <div className="mt-3 flex justify-end gap-2">
          <button onClick={onClose} className="rounded-lg border border-[var(--border)] px-4 py-2 text-sm">Cancel</button>
          <button onClick={() => onDone(date || undefined)} className="rounded-lg bg-green-600 px-4 py-2 text-sm text-white">Confirm win</button>
        </div>
      </div>
    </div>
  );
}

function NewOppForm({ initialClientId = '', onClose, onDone }: { initialClientId?: string; onClose: () => void; onDone: () => void }) {
  const toast = useToast();
  const clientsQ = useQuery({
    queryKey: ['clients-mini'],
    queryFn: async () => (await api.get('/clients', { params: { per_page: 100 } })).data.data as { id: string; name: string }[],
  });
  const [f, setF] = useState({ client_id: initialClientId, title: '', value: '', headcount: '', rate: '', months: '12', expected_close_date: '' });
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState('');
  const set = (k: keyof typeof f) => (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => setF({ ...f, [k]: e.target.value });

  const submit = async (ev: React.FormEvent) => {
    ev.preventDefault();
    if (pesoToCentavos(f.value) <= 0) { setErr('A peso value is required — every deal must be worth something.'); return; }
    setBusy(true);
    setErr('');
    try {
      await api.post('/opportunities', {
        client_id: f.client_id, title: f.title,
        value_centavos: pesoToCentavos(f.value),
        headcount: f.headcount ? Number(f.headcount) : undefined,
        rate_per_head_centavos: f.rate ? pesoToCentavos(f.rate) : undefined,
        contract_months: f.months ? Number(f.months) : undefined,
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
          <label>Value (₱) *<input required value={f.value} onChange={set('value')} inputMode="decimal" placeholder="2400000" className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2" /></label>
          <div className="grid grid-cols-3 gap-2">
            <label>Heads<input value={f.headcount} onChange={set('headcount')} inputMode="numeric" placeholder="40" className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2" /></label>
            <label>Rate/head/mo (₱)<input value={f.rate} onChange={set('rate')} inputMode="decimal" placeholder="15000" className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2" /></label>
            <label>Months<input value={f.months} onChange={set('months')} inputMode="numeric" placeholder="12" className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2" /></label>
          </div>
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
