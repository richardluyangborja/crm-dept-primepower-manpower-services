import { useState } from 'react';
import { formatPHP } from '../../lib/format';

export interface RitualOpp {
  id: string;
  title: string;
  client_id: string | null;
  company_id: string | null;
  client_name?: string;
  value_centavos: number;
  headcount: number | null;
  rate_per_head_centavos: number | null;
  contract_months: number | null;
  monthly_billing_centavos: number | null;
  contract_total_centavos: number | null;
  probability: number;
}

export interface TouchPayload { type: string; outcome: string; notes: string }
export interface FollowupPayload { title: string; due: string }
export interface RitualPayload { touch?: TouchPayload; followup?: FollowupPayload }

const TOUCH_TYPES = ['call', 'meeting', 'email', 'site_visit', 'note'];
const OUTCOMES = ['connected', 'no_answer', 'voicemail', 'busy', 'gatekeeper', 'follow_up_needed'];

/** Live money strip shown on every stage popup (specs/05 rituals). */
export function MoneyStrip({ opp }: { opp: RitualOpp }) {
  const monthly = opp.monthly_billing_centavos
    ?? (opp.headcount !== null && opp.rate_per_head_centavos !== null ? opp.headcount * opp.rate_per_head_centavos : null);
  const total = opp.contract_total_centavos
    ?? (monthly !== null && opp.contract_months ? monthly * opp.contract_months : null);
  return (
    <div className="grid grid-cols-2 gap-2 rounded-lg bg-slate-100 p-3 text-sm tabular-nums md:grid-cols-4 dark:bg-slate-800">
      <div><p className="text-xs text-[var(--text-muted)]">Deal value</p><p className="font-semibold">{formatPHP(opp.value_centavos)}</p></div>
      <div><p className="text-xs text-[var(--text-muted)]">Monthly</p><p className="font-semibold">{monthly !== null ? `${formatPHP(monthly)}/mo` : '—'}</p></div>
      <div><p className="text-xs text-[var(--text-muted)]">Contract total</p><p className="font-semibold">{total !== null ? formatPHP(total) : '—'}</p></div>
      <div><p className="text-xs text-[var(--text-muted)]">Probability</p><p className="font-semibold">{opp.probability}%</p></div>
    </div>
  );
}

/**
 * Shared stage level-up shell (specs/05 rituals): deal header + money strip,
 * optional touchpoint log + follow-up blocks, stage-specific children.
 */
export function StageUpModal({
  opp,
  fromLabel,
  toLabel,
  backward = false,
  confirmLabel,
  touchDefault = false,
  followupDefault,
  children,
  extraValid = true,
  onClose,
  onConfirm,
}: {
  opp: RitualOpp;
  fromLabel: string;
  toLabel: string;
  backward?: boolean;
  confirmLabel: string;
  touchDefault?: boolean;
  followupDefault?: { title: string; due: string };
  children?: React.ReactNode;
  extraValid?: boolean;
  onClose: () => void;
  onConfirm: (payload: RitualPayload) => void;
}) {
  const [logTouch, setLogTouch] = useState(touchDefault);
  const [type, setType] = useState('call');
  const [outcome, setOutcome] = useState('connected');
  const [notes, setNotes] = useState('');
  const [addFollowup, setAddFollowup] = useState(!!followupDefault);
  const [fTitle, setFTitle] = useState(followupDefault?.title ?? '');
  const [fDue, setFDue] = useState(followupDefault?.due ?? '');
  const [err, setErr] = useState('');

  const confirm = () => {
    if (logTouch && !notes.trim()) {
      setErr('Add a short note — it becomes the timeline entry.');
      return;
    }
    if (addFollowup && (!fTitle.trim() || !fDue)) {
      setErr('Follow-up needs a title and a due time.');
      return;
    }
    if (!extraValid) return;
    onConfirm({
      touch: logTouch ? { type, outcome, notes: notes.trim() } : undefined,
      followup: addFollowup ? { title: fTitle.trim(), due: fDue } : undefined,
    });
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" role="dialog" aria-modal="true">
      <div className="card max-h-[90vh] w-full max-w-lg overflow-y-auto p-6">
        <h2 className="text-lg font-semibold">
          {backward ? 'Move back' : 'Level up'} — {opp.title}
        </h2>
        <p className="mb-3 text-sm text-[var(--text-muted)]">
          {fromLabel} → <strong className="text-inherit">{toLabel}</strong>
          {backward ? ' · moving back keeps the history truthful.' : ''} · {opp.client_name ?? 'client'}
        </p>
        <div className="mb-3"><MoneyStrip opp={opp} /></div>
        {children}
        <label className="mt-3 flex cursor-pointer items-center gap-2 text-sm font-medium">
          <input type="checkbox" checked={logTouch} onChange={(e) => setLogTouch(e.target.checked)} />
          Log this touchpoint to history
        </label>
        {logTouch && (
          <div className="mt-2 grid grid-cols-2 gap-2 text-sm">
            <label>Type<select value={type} onChange={(e) => setType(e.target.value)} className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2">
              {TOUCH_TYPES.map((t) => <option key={t} value={t}>{t}</option>)}
            </select></label>
            <label>Outcome<select value={outcome} onChange={(e) => setOutcome(e.target.value)} className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2">
              {OUTCOMES.map((t) => <option key={t} value={t}>{t}</option>)}
            </select></label>
            <label className="col-span-2">Notes *<textarea value={notes} onChange={(e) => setNotes(e.target.value)} rows={2} placeholder="What happened, what was agreed…" className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2" /></label>
          </div>
        )}
        <label className="mt-3 flex cursor-pointer items-center gap-2 text-sm font-medium">
          <input type="checkbox" checked={addFollowup} onChange={(e) => setAddFollowup(e.target.checked)} />
          Set a follow-up
        </label>
        {addFollowup && (
          <div className="mt-2 grid grid-cols-2 gap-2 text-sm">
            <label>Title *<input value={fTitle} onChange={(e) => setFTitle(e.target.value)} placeholder="e.g. Send the quotation" className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2" /></label>
            <label>Due *<input type="datetime-local" value={fDue} onChange={(e) => setFDue(e.target.value)} className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2" /></label>
          </div>
        )}
        {err && <p className="mt-2 text-sm text-red-600">{err}</p>}
        <div className="mt-4 flex justify-end gap-2">
          <button onClick={onClose} className="rounded-lg border border-[var(--border)] px-4 py-2 text-sm">Cancel</button>
          <button onClick={confirm} className="rounded-lg bg-sky-600 px-4 py-2 text-sm text-white">{confirmLabel}</button>
        </div>
      </div>
    </div>
  );
}

/** Default follow-up due date as datetime-local value, N days out at 09:00. */
export function defaultDue(days: number): string {
  const d = new Date();
  d.setDate(d.getDate() + days);
  d.setHours(9, 0, 0, 0);
  const p = (n: number) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}T${p(d.getHours())}:${p(d.getMinutes())}`;
}
