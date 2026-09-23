import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import api from '../lib/apiClient';
import { formatPHP } from '../lib/format';
import { KpiCard } from '../components/ui/KpiCard';
import { DataTable } from '../components/ui/DataTable';
import { EmptyState } from '../components/ui/EmptyState';
import { ConfirmDialog } from '../components/ui/ConfirmDialog';
import { InfoCallout } from '../components/ui/InfoCallout';
import { StatusBadge } from '../components/ui/StatusBadge';
import { useToast } from '../components/ui/Toaster';
import { Check } from 'lucide-react';

interface Invoice {
  id: string;
  ref: string;
  title: string;
  client_id: string;
  client_name?: string;
  job_order_id: string | null;
  amount_centavos: number;
  balance_centavos: number;
  status: string;
  is_overdue: boolean;
  days_overdue: number;
  due_at: string | null;
}

interface Summary {
  outstanding_total_centavos: number;
  overdue_total_centavos: number;
  aging_buckets_centavos: { current: number; d1_30: number; d31_60: number; d61_90: number; d90plus: number };
  per_client: { client_id: string; client_name?: string; outstanding_centavos: number; invoices: number }[];
  mock: boolean;
}

function apiErr(e: unknown, fallback: string): string {
  if (typeof e === 'object' && e !== null && 'response' in e) {
    const r = (e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }).response;
    if (r?.data?.errors) return Object.values(r.data.errors).flat().join(' ');
    if (r?.data?.message) return r.data.message;
  }
  return fallback;
}

const pesoToCentavos = (v: string): number => Math.round((parseFloat(v) || 0) * 100);

export function FinancePage() {
  const [status, setStatus] = useState('');
  const [payId, setPayId] = useState<string | null>(null);
  const [collectTarget, setCollectTarget] = useState<Invoice | null>(null);
  const toast = useToast();
  const qc = useQueryClient();

  const summaryQ = useQuery({
    queryKey: ['finance-summary'],
    queryFn: async () => (await api.get('/finance/summary')).data.data as Summary,
  });
  const invoicesQ = useQuery({
    queryKey: ['invoices', status],
    queryFn: async () => (await api.get('/invoices', { params: { status: status || undefined, per_page: 50 } })).data.data as Invoice[],
  });

  const collectMut = useMutation({
    mutationFn: async (id: string) => (await api.post(`/invoices/${id}/collect`)).data,
    onSuccess: () => {
      toast('success', 'Collection reminder created.');
      qc.invalidateQueries({ queryKey: ['followups'] });
    },
    onError: (e) => toast('error', apiErr(e, 'Could not create collection reminder.')),
  });

  const s = summaryQ.data;
  const buckets: [string, number][] = s
    ? [['Current', s.aging_buckets_centavos.current], ['1–30 days', s.aging_buckets_centavos.d1_30], ['31–60 days', s.aging_buckets_centavos.d31_60], ['61–90 days', s.aging_buckets_centavos.d61_90], ['90+ days', s.aging_buckets_centavos.d90plus]]
    : [];
  const maxBucket = Math.max(1, ...buckets.map(([, v]) => v));

  return (
    <div className="flex flex-col gap-4">
      <div>
        <h1 className="text-xl font-bold">Finance</h1>
      </div>
      <InfoCallout lead="Every client, full ledger." link={{ label: 'Per-client billing', href: '/pipeline/finance' }}>
        AR aging, payments, and collection follow-ups, via Finance — the CRM shows summaries, Finance owns the transactions.
      </InfoCallout>

      {summaryQ.isLoading ? <p className="text-sm text-[var(--text-muted)]">Loading receivables…</p>
        : summaryQ.isError ? <div className="card p-6 text-sm">Couldn't load AR summary. <button className="text-sky-600 underline" onClick={() => summaryQ.refetch()}>Retry</button></div>
        : s && (
          <>
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
              <KpiCard label="Outstanding AR" value={formatPHP(s.outstanding_total_centavos)} sub={`${s.per_client.length} owing clients`} />
              <KpiCard label="Overdue total" value={formatPHP(s.overdue_total_centavos)} sub="Past due date" />
              <KpiCard label="90+ days" value={formatPHP(s.aging_buckets_centavos.d90plus)} sub="Oldest bucket — collect first" />
              <KpiCard label="Clients owing" value={String(s.per_client.length)} sub="Across all teams in scope" />
            </div>

            <div className="card p-4">
              <h2 className="mb-2 font-semibold">Aging buckets</h2>
              <div className="flex flex-col gap-1.5">
                {buckets.map(([label, value]) => (
                  <div key={label} className="flex items-center gap-2 text-sm">
                    <span className="w-20 shrink-0 text-xs text-[var(--text-muted)]">{label}</span>
                    <div className="h-3 flex-1 overflow-hidden rounded bg-slate-100 dark:bg-slate-800">
                      <div className={`h-full rounded ${label === 'Current' ? 'bg-green-500' : 'bg-amber-500'}`} style={{ width: `${(value / maxBucket) * 100}%` }} />
                    </div>
                    <span className="w-24 shrink-0 text-right font-semibold tabular-nums">{formatPHP(value)}</span>
                  </div>
                ))}
              </div>
            </div>

            <div className="card p-4">
              <h2 className="mb-2 font-semibold">Outstanding per client</h2>
              <ul className="flex flex-col gap-1 text-sm">
                {s.per_client.length === 0 && <li className="text-xs text-[var(--text-muted)]">Books are clean — nothing outstanding.</li>}
                {s.per_client.map((c) => (
                  <li key={c.client_id} className="flex items-center justify-between border-b border-[var(--border)] pb-1 last:border-0">
                    <Link to={`/clients/${c.client_id}`} className="text-sky-700 dark:text-sky-300">{c.client_name ?? `#${c.client_id}`}</Link>
                    <span className="font-semibold tabular-nums">{formatPHP(c.outstanding_centavos)} <span className="font-normal text-xs text-[var(--text-muted)]">· {c.invoices} inv</span></span>
                  </li>
                ))}
              </ul>
            </div>
          </>
        )}

      <div className="flex gap-2">
        <select value={status} onChange={(e) => setStatus(e.target.value)} className="card px-3 py-2 text-sm" aria-label="Filter by status">
          <option value="">All statuses</option>
          {['draft', 'sent', 'paid', 'overdue'].map((st) => <option key={st} value={st}>{st}</option>)}
        </select>
      </div>

      {invoicesQ.isLoading ? <p className="text-sm text-[var(--text-muted)]">Loading invoices…</p> : (
        <DataTable<Invoice>
          rows={invoicesQ.data ?? []}
          columns={[
            { key: 'r', header: 'Invoice', render: (r) => <span className="font-medium">{r.ref}<br /><span className="text-xs font-normal text-[var(--text-muted)]">{r.title}</span></span> },
            { key: 'c', header: 'Client', render: (r) => <Link to={`/clients/${r.client_id}`} className="text-sky-700 dark:text-sky-300">{r.client_name ?? `#${r.client_id}`}</Link> },
            { key: 'a', header: 'Balance', render: (r) => <span className="tabular-nums">{formatPHP(r.balance_centavos)} <span className="text-xs text-[var(--text-muted)]">/ {formatPHP(r.amount_centavos)}</span></span> },
            { key: 's', header: 'Status', render: (r) => r.is_overdue ? <StatusBadge value="overdue" /> : <StatusBadge value={r.status} /> },
            { key: 'd', header: 'Due', render: (r) => (r.due_at ? new Date(r.due_at).toLocaleDateString('en-PH', { month: 'short', day: 'numeric' }) : '—') },
            {
              key: 'x', header: 'Actions', render: (r) => r.status === 'paid' ? <span className="text-[var(--text-muted)]"><Check size={12} className="mr-1 inline" />Paid</span> : (
                <span className="flex gap-1">
                  <button onClick={() => setPayId(r.id)} className="rounded border border-[var(--border)] px-2 py-0.5 text-xs">Record payment</button>
                  {(r.is_overdue || r.status === 'overdue') && <button onClick={() => setCollectTarget(r)} className="rounded border border-[var(--border)] px-2 py-0.5 text-xs">Collect →</button>}
                </span>
              ),
            },
          ]}
          empty={<EmptyState title="No invoices" hint="Win a deal and the draft invoice appears here automatically." action={<Link to="/pipeline" className="mt-2 inline-block rounded-lg bg-sky-600 px-4 py-2 text-sm text-white">Go to Pipeline</Link>} />}
        />
      )}

      {payId !== null && (
        <PayModal
          invoice={(invoicesQ.data ?? []).find((i) => i.id === payId) ?? null}
          onClose={() => setPayId(null)}
          onDone={() => {
            qc.invalidateQueries({ queryKey: ['invoices'] });
            qc.invalidateQueries({ queryKey: ['finance-summary'] });
            qc.invalidateQueries({ queryKey: ['dashboard'] });
          }}
        />
      )}
      <ConfirmDialog
        open={collectTarget !== null}
        tone="info"
        title={`Start collection on ${collectTarget?.ref ?? 'invoice'}?`}
        body="This creates a high-priority follow-up for the owner and flags the invoice for collection."
        details={collectTarget ? [
          `Balance: ${formatPHP(collectTarget.balance_centavos)} of ${formatPHP(collectTarget.amount_centavos)}`,
          `Client: ${collectTarget.client_name ?? '—'}`,
        ] : []}
        confirmLabel="Start collection"
        onCancel={() => setCollectTarget(null)}
        onConfirm={() => {
          if (collectTarget) collectMut.mutate(collectTarget.id);
          setCollectTarget(null);
        }}
      />
    </div>
  );
}

function PayModal({ invoice, onClose, onDone }: { invoice: Invoice | null; onClose: () => void; onDone: () => void }) {
  const toast = useToast();
  const [amount, setAmount] = useState('');
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState('');
  if (!invoice) return null;

  const submit = async (payFull: boolean) => {
    setBusy(true);
    setErr('');
    try {
      const body = payFull ? {} : { amount_centavos: pesoToCentavos(amount) };
      const r = await api.post(`/invoices/${invoice.id}/pay`, body);
      toast('success', r.data.message ?? 'Payment recorded.');
      onDone();
      onClose();
    } catch (e) {
      setErr(apiErr(e, 'Could not record payment.'));
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" role="dialog" aria-modal="true">
      <div className="card w-full max-w-sm p-6">
        <h2 className="text-lg font-semibold">Record payment — {invoice.ref}</h2>
        <p className="mb-2 text-xs text-[var(--text-muted)]">Mock only: updates the balance ledger + audit trail. Balance due {formatPHP(invoice.balance_centavos)} of {formatPHP(invoice.amount_centavos)}.</p>
        <label className="text-sm">Partial amount (₱)<input value={amount} onChange={(e) => setAmount(e.target.value)} inputMode="decimal" placeholder="e.g. 50000" className="mt-1 w-full rounded-lg border border-[var(--border)] bg-transparent px-3 py-2" /></label>
        {err && <p className="mt-2 text-sm text-red-600">{err}</p>}
        <div className="mt-4 flex justify-end gap-2">
          <button onClick={onClose} className="rounded-lg border border-[var(--border)] px-4 py-2 text-sm">Cancel</button>
          <button disabled={busy} onClick={() => submit(false)} className="rounded-lg border border-[var(--border)] px-4 py-2 text-sm disabled:opacity-50">Pay partial</button>
          <button disabled={busy} onClick={() => submit(true)} className="rounded-lg bg-sky-600 px-4 py-2 text-sm text-white disabled:opacity-50">Mark paid</button>
        </div>
      </div>
    </div>
  );
}
