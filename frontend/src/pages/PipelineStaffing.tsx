import { Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import api from '../lib/apiClient';
import { DataTable } from '../components/ui/DataTable';
import { EmptyState } from '../components/ui/EmptyState';
import { InfoCallout } from '../components/ui/InfoCallout';
import { ClientPicker, useClientParam } from '../components/crm/ClientPicker';

interface StaffRow {
  client_id: string;
  client_name: string;
  owner_name?: string;
  deployed: number;
  site?: string | null;
  job_orders: number;
  by_status: Record<string, number>;
  mock: boolean;
}

/** Pipeline hub child: per-client staffing detail (via Client Management). Read-only. */
export function PipelineStaffingPage() {
  const { clientId, setClientId } = useClientParam();
  const staffingQ = useQuery({
    queryKey: ['staffing'],
    queryFn: async () => {
      const d = (await api.get('/staffing')).data.data;
      return { rows: d.data as StaffRow[], total: d.meta.total_deployed as number };
    },
  });
  const rows = (staffingQ.data?.rows ?? []).filter((r) => !clientId || String(r.client_id) === clientId);

  return (
    <div className="flex flex-col gap-4">
      <div>
        <h1 className="text-xl font-bold">Pipeline · Deployed Staff</h1>
      </div>
      <InfoCallout lead="One client at a time.">
        Deployed headcount, read-only via Client Management — progression happens on their side.
      </InfoCallout>
      <div className="flex gap-2">
        <ClientPicker clientId={clientId} onChange={setClientId} />
        {staffingQ.data && (
          <div className="card px-4 py-2 text-sm">
            Total deployed: <strong className="tabular-nums">{staffingQ.data.total}</strong>
          </div>
        )}
      </div>
      {staffingQ.isLoading ? <p className="text-sm text-[var(--text-muted)]">Loading staffing…</p>
        : staffingQ.isError ? <div className="card p-6 text-sm">Couldn't load staffing. <button className="text-sky-600 underline" onClick={() => staffingQ.refetch()}>Retry</button></div>
        : (
          <DataTable<StaffRow & { id: string }>
            rows={rows.map((r) => ({ ...r, id: r.client_id }))}
            columns={[
              { key: 'c', header: 'Client', render: (r) => <Link to={`/clients/${r.client_id}`} className="font-medium text-sky-700 hover:underline dark:text-sky-300">{r.client_name}</Link> },
              { key: 's', header: 'Site', render: (r) => r.site ?? '—' },
              { key: 'd', header: 'Deployed', render: (r) => <span className="font-semibold tabular-nums">{r.deployed}</span> },
              { key: 'j', header: 'Job orders', render: (r) => String(r.job_orders) },
              { key: 'o', header: 'Owner', render: (r) => r.owner_name ?? '—' },
            ]}
            empty={<EmptyState title="No staffing rows" hint={clientId ? 'This client has no deployment data yet.' : 'No deployment data in scope.'} />}
          />
        )}
      <p className="text-xs text-[var(--text-muted)]">Full board lives in <Link to="/operations" className="text-sky-700 hover:underline dark:text-sky-300">Operations</Link>. Read-only via Client Management.</p>
    </div>
  );
}
