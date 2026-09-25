import { useState } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import api from '../../lib/apiClient';
import { useToast } from '../ui/Toaster';
import { apiErr } from './ClientWidgets';

interface Member { id: number; name: string; role: string }

/** Ownership transfer dialog (specs/02): successor + reason → audited move. */
export function TransferDialog({ companyId, companyName, onClose, onDone }: {
  companyId: string;
  companyName: string;
  onClose: () => void;
  onDone: () => void;
}) {
  const toast = useToast();
  const qc = useQueryClient();
  const [toUserId, setToUserId] = useState('');
  const [reason, setReason] = useState('');
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState('');

  const membersQ = useQuery({
    queryKey: ['hr-directory', 'transfer'],
    queryFn: async () => (await api.get('/hr/directory', { params: { per_page: 100 } })).data.data as Member[],
  });
  const members = membersQ.data ?? [];

  const submit = async (ev: React.FormEvent) => {
    ev.preventDefault();
    if (!toUserId) { setErr('Pick who receives this account.'); return; }
    setBusy(true);
    setErr('');
    try {
      const r = await api.post(`/companies/${companyId}/transfer`, {
        to_user_id: Number(toUserId), reason: reason.trim() || undefined,
      });
      toast('success', r.data.message ?? 'Ownership transferred.');
      qc.invalidateQueries({ queryKey: ['client'] });
      qc.invalidateQueries({ queryKey: ['clients'] });
      qc.invalidateQueries({ queryKey: ['leads'] });
      qc.invalidateQueries({ queryKey: ['companies'] });
      onDone();
      onClose();
    } catch (e) {
      setErr(apiErr(e, 'Could not transfer ownership.'));
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" role="dialog" aria-modal="true">
      <form onSubmit={submit} className="card w-full max-w-md p-6">
        <h2 className="text-lg font-semibold">Transfer {companyName}?</h2>
        <p className="mb-3 text-xs text-[var(--text-muted)]">
          The company plus its open leads, deals, and reminders move together. Closed history keeps its original owner.
        </p>
        <div className="flex flex-col gap-2 text-sm">
          <label>New owner *<select required value={toUserId} onChange={(e) => setToUserId(e.target.value)} className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2">
            <option value="">Pick a teammate…</option>
            {members.map((m) => <option key={m.id} value={m.id}>{m.name} · {m.role.replace('_', ' ')}</option>)}
          </select></label>
          <label>Reason (optional)<input value={reason} onChange={(e) => setReason(e.target.value)} placeholder="e.g. Workload rebalance" className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2" /></label>
        </div>
        {err && <p className="mt-2 text-sm text-red-600">{err}</p>}
        <div className="mt-4 flex justify-end gap-2">
          <button type="button" onClick={onClose} className="rounded-lg border border-[var(--border)] px-4 py-2 text-sm">Cancel</button>
          <button disabled={busy} className="rounded-lg bg-sky-600 px-4 py-2 text-sm text-white disabled:opacity-50">{busy ? 'Moving…' : 'Transfer'}</button>
        </div>
      </form>
    </div>
  );
}
