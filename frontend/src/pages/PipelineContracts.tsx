import { Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import api from '../lib/apiClient';
import { formatPHP } from '../lib/format';
import { DataTable } from '../components/ui/DataTable';
import { EmptyState } from '../components/ui/EmptyState';
import { StatusBadge } from '../components/ui/StatusBadge';
import { ClientPicker, useClientParam } from '../components/crm/ClientPicker';

interface Contract {
  id: string;
  ref: string;
  opportunity_id: string | null;
  client_id: string;
  client_name?: string;
  headcount: number | null;
  rate_per_head_centavos: number | null;
  contract_months: number | null;
  monthly_billing_centavos: number | null;
  contract_total_centavos: number | null;
  start_date: string | null;
  status: string;
}

/** Pipeline hub child: contract ledger (mock Core-3/Governance/Facilities). */
export function PipelineContractsPage() {
  const { clientId, setClientId } = useClientParam();
  const contractsQ = useQuery({
    queryKey: ['contracts', clientId],
    queryFn: async () =>
      (await api.get('/contracts', { params: { client_id: clientId || undefined, per_page: 100 } })).data.data as Contract[],
  });
  const rows = contractsQ.data ?? [];

  return (
    <div className="flex flex-col gap-4">
      <div>
        <h1 className="text-xl font-bold">Pipeline · Contracts</h1>
        <p className="text-sm text-[var(--text-muted)]">Signed terms per deal. Mock paper trail for Core-3 docs, Governance legal, and Facilities contracts — created at signing, read-only here.</p>
      </div>
      <div className="flex gap-2">
        <ClientPicker clientId={clientId} onChange={setClientId} />
      </div>
      {contractsQ.isLoading ? <p className="text-sm text-[var(--text-muted)]">Loading contracts…</p>
        : contractsQ.isError ? <div className="card p-6 text-sm">Couldn't load contracts. <button className="text-sky-600 underline" onClick={() => contractsQ.refetch()}>Retry</button></div>
        : (
          <DataTable<Contract>
            rows={rows}
            columns={[
              { key: 'r', header: 'Ref', render: (r) => <span className="font-medium">{r.ref}</span> },
              { key: 'c', header: 'Client', render: (r) => <Link to={`/clients/${r.client_id}`} className="text-sky-700 hover:underline dark:text-sky-300">{r.client_name ?? `#${r.client_id}`}</Link> },
              { key: 't', header: 'Terms', render: (r) => <span className="tabular-nums">{r.headcount ?? '?'} heads × {r.rate_per_head_centavos ? `${formatPHP(r.rate_per_head_centavos)}/mo` : '?'} × {r.contract_months ?? '?'} mo</span> },
              { key: 'm', header: 'Monthly', render: (r) => <span className="font-semibold tabular-nums">{r.monthly_billing_centavos ? formatPHP(r.monthly_billing_centavos) : '—'}</span> },
              { key: 's', header: 'Start', render: (r) => (r.start_date ? new Date(r.start_date).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' }) : '—') },
              { key: 'st', header: 'Status', render: (r) => <StatusBadge value={r.status} /> },
            ]}
            empty={<EmptyState title="No contracts" hint={clientId ? 'This client has no signed contracts yet — sign from the Pipeline board.' : 'Sign a deal from the Pipeline board to create the first contract.'} />}
          />
        )}
    </div>
  );
}
