import { Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import api from '../lib/apiClient';
import { formatPHP } from '../lib/format';
import { DataTable } from '../components/ui/DataTable';
import { EmptyState } from '../components/ui/EmptyState';
import { StatusBadge } from '../components/ui/StatusBadge';
import { ClientPicker, useClientParam } from '../components/crm/ClientPicker';

interface Invoice {
  id: string;
  ref: string;
  title: string;
  client_id: string;
  client_name?: string;
  amount_centavos: number;
  balance_centavos: number;
  status: string;
  is_overdue: boolean;
  due_at: string | null;
}

/** Pipeline hub child: per-client invoice detail (via Finance). */
export function PipelineFinancePage() {
  const { clientId, setClientId } = useClientParam();
  const invoicesQ = useQuery({
    queryKey: ['invoices', clientId],
    queryFn: async () =>
      (await api.get('/invoices', { params: { client_id: clientId || undefined, per_page: 100 } })).data.data as Invoice[],
  });
  const rows = invoicesQ.data ?? [];
  const outstanding = rows.filter((r) => r.status !== 'paid').reduce((a, r) => a + r.balance_centavos, 0);

  return (
    <div className="flex flex-col gap-4">
      <div>
        <h1 className="text-xl font-bold">Pipeline · Billing</h1>
        <p className="text-sm text-[var(--text-muted)]">Per-client invoice detail, via Finance — full ledger lives in <Link to="/finance" className="text-sky-700 hover:underline dark:text-sky-300">Finance</Link>.</p>
      </div>
      <div className="flex gap-2">
        <ClientPicker clientId={clientId} onChange={setClientId} />
        {outstanding > 0 && (
          <div className="card px-4 py-2 text-sm">
            Outstanding: <strong className="tabular-nums">{formatPHP(outstanding)}</strong>
          </div>
        )}
      </div>
      {invoicesQ.isLoading ? <p className="text-sm text-[var(--text-muted)]">Loading invoices…</p>
        : invoicesQ.isError ? <div className="card p-6 text-sm">Couldn't load invoices. <button className="text-sky-600 underline" onClick={() => invoicesQ.refetch()}>Retry</button></div>
        : (
          <DataTable<Invoice>
            rows={rows}
            columns={[
              { key: 'r', header: 'Invoice', render: (r) => <span className="font-medium">{r.ref}<br /><span className="text-xs font-normal text-[var(--text-muted)]">{r.title}</span></span> },
              { key: 'c', header: 'Client', render: (r) => <Link to={`/clients/${r.client_id}`} className="text-sky-700 hover:underline dark:text-sky-300">{r.client_name ?? `#${r.client_id}`}</Link> },
              { key: 'b', header: 'Balance', render: (r) => <span className="tabular-nums">{formatPHP(r.balance_centavos)} <span className="text-xs text-[var(--text-muted)]">/ {formatPHP(r.amount_centavos)}</span></span> },
              { key: 's', header: 'Status', render: (r) => r.is_overdue ? <StatusBadge value="overdue" /> : <StatusBadge value={r.status} /> },
              { key: 'd', header: 'Due', render: (r) => (r.due_at ? new Date(r.due_at).toLocaleDateString('en-PH', { month: 'short', day: 'numeric' }) : '—') },
            ]}
            empty={<EmptyState title="No invoices" hint={clientId ? 'This client has no invoices yet — win a deal to create the first draft.' : 'Pick a client to see their invoices.'} />}
          />
        )}
    </div>
  );
}
