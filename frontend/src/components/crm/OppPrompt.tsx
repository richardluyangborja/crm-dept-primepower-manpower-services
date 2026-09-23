import { useState } from 'react';
import api from '../../lib/apiClient';
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
  const [value, setValue] = useState('');
  const [heads, setHeads] = useState(headcount ? String(headcount) : '');
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState('');

  const submit = async (ev: React.FormEvent) => {
    ev.preventDefault();
    if (pesoToCentavos(value) <= 0) { setErr('A peso value is required — every deal must be worth something.'); return; }
    setBusy(true);
    setErr('');
    try {
      await api.post('/opportunities', {
        company_id: companyId,
        title: title.trim() || `Opening — ${companyName}`,
        value_centavos: pesoToCentavos(value),
        headcount: heads ? Number(heads) : undefined,
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
          <div className="grid grid-cols-2 gap-2">
            <label>Value (₱) *<input required value={value} onChange={(e) => setValue(e.target.value)} inputMode="decimal" placeholder="2400000" className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2" /></label>
            <label>Heads<input value={heads} onChange={(e) => setHeads(e.target.value)} inputMode="numeric" placeholder="40" className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2" /></label>
          </div>
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
