import { Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import api from '../lib/apiClient';
import { formatPHP } from '../lib/format';
import { DataTable } from '../components/ui/DataTable';
import { EmptyState } from '../components/ui/EmptyState';
import { InfoCallout } from '../components/ui/InfoCallout';
import { SectionHead } from '../components/ui/SectionHead';
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

interface JobOrder {
  id: string;
  ref: string;
  title: string;
  client_id: string;
  client_name?: string;
  headcount: number | null;
  value_centavos: number;
  status: string;
  invoice_ref: string | null;
}

const JO_FLOW = ['draft', 'staffed', 'deployed', 'billed'] as const;

/** Pipeline hub child: per-client staffing detail + full board (via Client Management). Read-only. */
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

  const jobsQ = useQuery({
    queryKey: ['job-orders-all'],
    queryFn: async () => (await api.get('/job-orders', { params: { per_page: 100 } })).data.data as JobOrder[],
  });
  const jobs = jobsQ.data ?? [];
  const byStatus = (s: string) => jobs.filter((r) => r.status === s);
  const totalHeadcount = jobs
    .filter((r) => r.status === 'deployed')
    .reduce((a, r) => a + (r.headcount ?? 0), 0);

  return (
    <div className="flex flex-col gap-4">
      <div>
        <h1 className="text-xl font-bold">Pipeline · Deployed Staff</h1>
      </div>
      <InfoCallout lead="Staffing, one client or all of them.">
        Deployed headcount, read-only via Client Management — progression happens on their side.
      </InfoCallout>

      <section aria-label="Per client" className="flex flex-col gap-3">
        <SectionHead title="Per client" hint="Pick an account to see its deployments, sites, and job orders." />
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
      </section>

      <section aria-label="Full board" className="flex flex-col gap-3">
        <SectionHead title="Full board" hint="Every job order across every client, by execution stage." />
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
          <div className="card p-4">
            <p className="text-xs uppercase text-[var(--text-muted)]">Active job orders</p>
            <p className="text-2xl font-bold tabular-nums">{jobs.filter((r) => r.status !== 'billed').length}</p>
          </div>
          <div className="card p-4">
            <p className="text-xs uppercase text-[var(--text-muted)]">Deployed headcount ⓘ</p>
            <p className="text-2xl font-bold tabular-nums">{totalHeadcount}</p>
          </div>
          <div className="card p-4">
            <p className="text-xs uppercase text-[var(--text-muted)]">Billed (completed)</p>
            <p className="text-2xl font-bold tabular-nums">{byStatus('billed').length}</p>
          </div>
        </div>
        {jobsQ.isLoading ? <p className="text-sm text-[var(--text-muted)]">Loading board…</p>
          : jobsQ.isError ? <div className="card p-6 text-sm">Couldn't load the board. <button className="text-sky-600 underline" onClick={() => jobsQ.refetch()}>Retry</button></div>
          : jobs.length === 0 ? <EmptyState title="No job orders yet" hint="Win a deal on the Pipeline board and the staffing journey starts automatically." action={<Link to="/pipeline" className="mt-2 inline-block rounded-lg bg-sky-600 px-4 py-2 text-sm text-white">Go to Pipeline</Link>} />
          : (
            <div className="flex gap-3 overflow-x-auto pb-2">
              {JO_FLOW.map((s) => {
                const col = byStatus(s);
                return (
                  <div key={s} className="w-64 shrink-0 rounded-xl border border-[var(--border)] bg-[var(--bg-card)] p-2">
                    <div className="flex items-center justify-between px-1 py-1">
                      <p className="text-xs font-bold uppercase">{s} <span className="text-[var(--text-muted)]">{col.length}</span></p>
                    </div>
                    <div className="flex flex-col gap-2">
                      {col.map((j) => (
                        <div key={j.id} className="rounded-lg border border-[var(--border)] bg-[var(--bg-app)] p-2.5 text-sm">
                          <p className="font-medium">{j.ref}</p>
                          <p className="truncate text-xs">{j.title}</p>
                          <Link to={`/clients/${j.client_id}`} className="text-xs text-sky-700 dark:text-sky-300">{j.client_name ?? `#${j.client_id}`} →</Link>
                          <p className="mt-1 text-xs text-[var(--text-muted)] tabular-nums">
                            {j.headcount !== null ? `${j.headcount} headcount · ` : ''}{formatPHP(j.value_centavos)}
                          </p>
                        </div>
                      ))}
                      {col.length === 0 && <p className="px-1 py-4 text-center text-xs text-[var(--text-muted)]">Empty</p>}
                    </div>
                  </div>
                );
              })}
            </div>
          )}
      </section>
    </div>
  );
}
