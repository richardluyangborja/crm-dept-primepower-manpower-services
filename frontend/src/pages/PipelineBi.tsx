import { Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import api from '../lib/apiClient';
import { formatPHP } from '../lib/format';
import { DataTable } from '../components/ui/DataTable';
import { EmptyState } from '../components/ui/EmptyState';
import { StatusBadge } from '../components/ui/StatusBadge';
import { ClientPicker, useClientParam } from '../components/crm/ClientPicker';

interface BiRow {
  client_id: number;
  client_name: string;
  owner_name?: string;
  deals: number;
  open_value_centavos: number;
  won_value_centavos: number;
  surveys: number;
  nps_avg: number | null;
  outstanding_centavos: number;
  risk: string;
  risk_drivers: string[];
  mock: boolean;
}

/** Pipeline hub child: per-client BI drilldown (Dept-9 style aggregates). Manager+ only. */
export function PipelineBiPage() {
  const { clientId, setClientId } = useClientParam();
  const biQ = useQuery({
    queryKey: ['bi-breakdown'],
    queryFn: async () => (await api.get('/bi/client-breakdown')).data.data.data as BiRow[],
  });
  const rows = (biQ.data ?? []).filter((r) => !clientId || String(r.client_id) === clientId);

  return (
    <div className="flex flex-col gap-4">
      <div>
        <h1 className="text-xl font-bold">Pipeline · BI Drilldown</h1>
        <p className="text-sm text-[var(--text-muted)]">Per-client aggregates across pipeline, satisfaction, risk, and receivables. Managers and above. Mock data throughout.</p>
      </div>
      <div className="flex gap-2">
        <ClientPicker clientId={clientId} onChange={setClientId} />
      </div>
      {biQ.isLoading ? <p className="text-sm text-[var(--text-muted)]">Loading breakdown…</p>
        : biQ.isError ? (
          <div className="card p-6 text-sm">
            {(() => {
              const err = biQ.error as { response?: { status?: number } };
              return err?.response?.status === 403
                ? 'BI drilldown is available to managers and above — ask your manager for access.'
                : <>Couldn't load the breakdown. <button className="text-sky-600 underline" onClick={() => biQ.refetch()}>Retry</button></>;
            })()}
          </div>
        ) : (
          <DataTable<BiRow & { id: number }>
            rows={rows.map((r) => ({ ...r, id: r.client_id }))}
            columns={[
              { key: 'c', header: 'Client', render: (r) => <Link to={`/clients/${r.client_id}`} className="font-medium text-sky-700 hover:underline dark:text-sky-300">{r.client_name}</Link> },
              { key: 'd', header: 'Deals', render: (r) => String(r.deals) },
              { key: 'o', header: 'Open value', render: (r) => <span className="tabular-nums">{formatPHP(r.open_value_centavos)}</span> },
              { key: 'w', header: 'Won value', render: (r) => <span className="tabular-nums">{formatPHP(r.won_value_centavos)}</span> },
              { key: 'n', header: 'NPS', render: (r) => (r.nps_avg !== null ? String(r.nps_avg) : '—') },
              { key: 'a', header: 'Outstanding', render: (r) => <span className="tabular-nums">{formatPHP(r.outstanding_centavos)}</span> },
              { key: 'r', header: 'Risk', render: (r) => <StatusBadge value={r.risk} /> },
            ]}
            empty={<EmptyState title="No rows" hint={clientId ? 'This client has no activity in scope.' : 'No client activity in scope.'} />}
          />
        )}
      <p className="text-xs text-[var(--text-muted)]">Full packs with CSV and print live in <Link to="/reports" className="text-sky-700 hover:underline dark:text-sky-300">Reports</Link>.</p>
    </div>
  );
}
