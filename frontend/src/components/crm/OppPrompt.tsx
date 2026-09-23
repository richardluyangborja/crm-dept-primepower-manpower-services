import { useState } from 'react';
import api from '../../lib/apiClient';
import { formatPHP } from '../../lib/format';
import { useToast } from '../ui/Toaster';
import { apiErr } from './ClientWidgets';

const pesoToCentavos = (v: string): number => Math.round((parseFloat(v) || 0) * 100);

/** Qualify → opportunity prompt (specs/04): one deal per qualified lead, skippable. */
export function OppPrompt({ companyId, companyName, headcount, onClose, onDone }: {
  companyId: string;
  companyName: string;
  headcount?: number | null;
  onClose: () => void;
  onDone: () => void;
}) {
  const toast = useToast();
  const [title, setTitle] = useState(`Opening — ${companyName}`);
  const [heads, setHeads] = useState(headcount ? String(headcount) : '');
  const [rate, setRate] = useState('');
  const [months, setMonths] = useState('12');
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState('');
  const monthly = (Number(heads) || 0) * pesoToCentavos(rate || '0');
  const total = monthly * (Number(months) || 0);

  const submit = async (ev: React.FormEvent) => {
    ev.preventDefault();
    if (!(Number(heads) > 0 && monthly > 0 && Number(months) > 0)) {
      setErr('Heads, rate, and months are all required — the value computes from them.');
      return;
    }
    setBusy(true);
    setErr('');
    try {
      await api.post('/opportunities', {
        company_id: companyId,
        title: title.trim() || `Opening — ${companyName}`,
        value_centavos: total,
        headcount: Number(heads),
        rate_per_head_centavos: pesoToCentavos(rate),
        contract_months: Number(months),
      });
      toast('success', {
        title: 'Deal opened.',
        body: `First deal for ${companyName} is on the board.`,
        action: { label: 'Open board', href: '/pipeline' },
      });
      onDone();
      onClose();
    } catch (e) {
      setErr(apiErr(e, 'Could not open the deal.'));
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" role="dialog" aria-modal="true">
      <form onSubmit={submit} className="card w-full max-w-md p-6">
        <h2 className="text-lg font-semibold">Open the first deal?</h2>
        <p className="mb-3 text-xs text-[var(--text-muted)]">{companyName} is qualified — put the requirement on the board, or skip for now.</p>
        <div className="flex flex-col gap-2 text-sm">
          <label>Deal title *<input required value={title} onChange={(e) => setTitle(e.target.value)} className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2" /></label>
          <div className="grid grid-cols-3 gap-2">
            <label>Heads *<input required value={heads} onChange={(e) => setHeads(e.target.value)} inputMode="numeric" placeholder="40" className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2" /></label>
            <label>Rate/head/mo (₱) *<input required value={rate} onChange={(e) => setRate(e.target.value)} inputMode="decimal" placeholder="15000" className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2" /></label>
            <label>Months *<input required value={months} onChange={(e) => setMonths(e.target.value)} inputMode="numeric" placeholder="12" className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2" /></label>
          </div>
          <p className="rounded-lg bg-slate-100 px-3 py-2 text-sm tabular-nums dark:bg-slate-800" aria-live="polite">
            {monthly > 0 ? `${formatPHP(monthly)}/mo × ${months || '?'} mo = ${formatPHP(total)} total` : 'Fill heads + rate + months — the value computes itself.'}
          </p>
        </div>
        {err && <p className="mt-2 text-sm text-red-600">{err}</p>}
        <div className="mt-4 flex justify-between gap-2">
          <button type="button" onClick={onClose} className="rounded-lg border border-[var(--border)] px-4 py-2 text-sm">Skip for now</button>
          <button disabled={busy} className="rounded-lg bg-sky-600 px-4 py-2 text-sm text-white disabled:opacity-50">{busy ? 'Opening…' : 'Open deal'}</button>
        </div>
      </form>
    </div>
  );
}
